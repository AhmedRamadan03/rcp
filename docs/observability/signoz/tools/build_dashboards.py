"""Builds the RCP SigNoz dashboards in the V2 format (schemaVersion v6, SigNoz >= 0.135).

Schema source: pkg/types/dashboardtypes/perses_*.go at tag v0.142.1. The server decodes with
DisallowUnknownFields, so every key written here exists in those structs. Panel ids are slugs of
the panel title, so regenerating produces a stable diff.

How SigNoz 0.142.1 renders a table (frontend Panels/kinds/TablePanel, backend pkg/querier/postprocess.go):
- Columns are the group-by keys in the order given, then the value columns in query-name order
  (A, B, ... then F1).
- Every column starts 180 px wide; only the viewer can resize it (kept in their browser). A table with
  more columns than its panel has room for scrolls sideways and hides the numbers. dashboard() refuses
  such a placement, see MAX_COLUMNS. The reference screen is the owner's laptop, not a phone: on a
  phone every table scrolls and no JSON setting changes that.
- Several queries in one table are joined on the group-by keys. A row that one query dropped through
  its limit comes back from the others with n/a, so a limit on one query of several is never a top-N.
  For a top-N table use ONE query with several aggregations: q([(expression, title), ...]).
- A formula treats a missing count()/sum() as 0, but only for single-aggregation queries, so formula
  inputs stay single-aggregation and unlimited. A plain query column shows n/a instead, so a "Failed"
  column is the formula total - successful (with_failures()), which reads 0 for a healthy row.
- Value columns sort by query name and formulas are F1, F2, ...; a query that must sit to the right of
  a formula is named P, Q, R (q(name=...)).

Step interval and colour (frontend queryV5/buildQueryRangeRequest.ts, Panels/utils/legendSeries.ts, backend
pkg/querier/querier.go):
- No query sets stepInterval. The backend then picks range/300, and for a bar panel the frontend picks a
  step that gives about 80 bars (20 min at 1 day). A fixed step switches both off: at 60 s a day of bars
  is 1440 hairlines, and past 25 hours the backend clamps it and hangs a warning on EVERY panel.
- A series colour is looked up in legend.customColors by its legend label; without an entry it is a hash
  of the label (which made 200 and 404 the same green). Colours that mean good/bad come from STATUS
  below, a set checked with the dataviz skill's validate_palette.js on SigNoz's dark surface (#121317),
  all pairs: colour-blind separation 9.9 (target 8), contrast 3:1 or better. It is the only clean
  four-colour set found; green next to red fails badly (4.1), so "good" is teal.
- visualization.fillSpans makes the backend fill empty buckets with 0, so a line drops to zero in an
  outage instead of bridging it (Traffic).
- An event that fires once a minute (schedule:run) is a BAR chart, not a line: the automatic line step
  is range/300 (285 s at 1 day), which is no multiple of 60 s, so buckets hold 4 or 5 ticks and the line
  becomes a comb. Bar steps are always whole minutes, so every bar is equally tall and a stopped cron is
  a hole.
- axes softMin = softMax = 0 means "automatic". A percentage that sits at exactly 100 then gets an axis of
  100 to 200, so such a chart sets soft=(99, 100); the axis still stretches down when the value drops.

Database spans (vendor/open-telemetry: opentelemetry-auto-laravel QueryWatcher, opentelemetry-auto-pdo):
- One statement is recorded by BOTH: Laravel writes one span "sql <OPERATION>" (db.namespace = the plain
  database name, db.query.text), PDO adds PDO::prepare, PDOStatement::execute and PDOStatement::fetchAll
  for the same statement (db.namespace in quotes, mostly no text). "db.system.name EXISTS" therefore counts
  a statement about four times; STATEMENTS is name LIKE 'sql%'.
- Laravel only records a statement that succeeded (QueryExecuted), so a FAILED statement exists only as a
  PDO span. Failures use DB_SPANS and show the PDO operation (PDO::connect, PDOStatement::execute, ...).
- Statements also come from scheduled commands and queue workers, so "statements per request" is not an
  N+1 signal here; the tile is total time in SQL instead.

Usage: python3 build_dashboards.py <output-folder>
"""
import json
import re
import sys

OUT_DIR = sys.argv[1]
GRID = 12
# Table columns that fit a panel of a given grid width without sideways scrolling. Measured on the owner's
# laptop (viewport about 2090 px): a width-6 panel is about 1020 px, so 170 px per grid column; a table
# column is 180 px. Width 4 -> 3 columns, 5 -> 4, 6 -> 5, 7 -> 6, 8 -> 7, 12 -> 11.
MAX_COLUMNS = {width: (width * 170 - 12) // 180 for width in range(1, GRID + 1)}

# Field context per key (SigNoz 0.142.1). Anything not listed is a span attribute.
CONTEXT = {"service.name": "resource", "name": "span", "kind_string": "span", "has_error": "span",
           "duration_nano": "span", "http_method": "span", "response_status_code": "span",
           "timestamp": "span"}
NUMBERS = {"duration_nano"}


def field(name):
    return {"name": name, "signal": "", "fieldContext": CONTEXT.get(name, "attribute"),
            "fieldDataType": "number" if name in NUMBERS else "string"}


# ------------------------------------------------------------------------------------------------
# Queries
# ------------------------------------------------------------------------------------------------
def q(agg, flt, by=(), legend="", hidden=False, order=False, limit=None, name=None):
    """One builder query on traces.

    agg is one expression, or a list of (expression, column title) for several value columns that share
    one ORDER BY ... LIMIT (a true top-N; order=True sorts by the first expression, descending).
    hidden=True keeps the query out of the panel but usable in a formula. name replaces the automatic
    letter; table value columns sort by it, so "P" puts a column to the right of the formulas.
    """
    aggs = agg if isinstance(agg, list) else [(agg, "")]
    return {"aggs": aggs, "flt": flt, "by": list(by), "legend": legend, "hidden": hidden,
            "order": order, "limit": limit, "name": name}


def col(query_name, expression):
    """Key of a value column from a several-aggregations query, for units= and red_above=."""
    return f"{query_name}.{expression}"


def f(expression, legend=""):
    return {"formula": expression, "legend": legend}


def _builder(name, spec, raw_order=None, raw_limit=None):
    out = {"name": name, "signal": "traces", "source": "",
           "disabled": spec["hidden"], "filter": {"expression": spec["flt"]},
           "groupBy": [field(k) for k in spec["by"]], "order": [], "having": {"expression": ""},
           "functions": [], "legend": spec["legend"]}
    if raw_order:
        out["order"] = [{"key": {"name": raw_order, "signal": "", "fieldContext": "", "fieldDataType": ""},
                         "direction": "desc"}]
        out["limit"] = raw_limit
        return out
    out["aggregations"] = [{"expression": e, "alias": a} if a else {"expression": e} for e, a in spec["aggs"]]
    if spec["order"]:
        out["order"] = [{"key": {"name": spec["aggs"][0][0], "signal": "", "fieldContext": "",
                                 "fieldDataType": ""}, "direction": "desc"}]
    if spec["limit"]:
        out["limit"] = spec["limit"]
    return out


def _queries(kind, specs):
    """A panel holds exactly one query; several builder queries and formulas go in a CompositeQuery."""
    builders = [s for s in specs if "formula" not in s]
    formulas = [s for s in specs if "formula" in s]
    letters = iter("ABCDEFGH")
    names = [s["name"] or next(letters) for s in builders]
    assert len(set(names)) == len(names), names
    assert not formulas or all(len(s["aggs"]) == 1 for s in builders), "formula inputs must be single-aggregation"
    if len(builders) == 1 and not formulas:
        return [{"kind": kind, "spec": {"name": names[0], "plugin": {
            "kind": "signoz/BuilderQuery", "spec": _builder(names[0], builders[0])}}}]
    envelopes = [{"type": "builder_query", "spec": _builder(n, s)} for n, s in zip(names, builders)]
    for i, s in enumerate(formulas):
        envelopes.append({"type": "builder_formula", "spec": {
            "name": f"F{i + 1}", "expression": s["formula"], "disabled": False,
            "having": {"expression": ""}, "legend": s["legend"]}})
    return [{"kind": kind, "spec": {"plugin": {"kind": "signoz/CompositeQuery", "spec": {"queries": envelopes}}}}]


# ------------------------------------------------------------------------------------------------
# Panels
# ------------------------------------------------------------------------------------------------
def _panel(title, description, plugin_kind, plugin_spec, queries):
    return {"kind": "Panel", "spec": {"display": {"name": title, "description": description},
                                      "plugin": {"kind": plugin_kind, "spec": plugin_spec},
                                      "queries": queries, "links": []}}


def _axes_legend(colors=None, soft=(0, 0)):
    return {"axes": {"softMin": soft[0], "softMax": soft[1], "isLogScale": False},
            "legend": {"position": "bottom", "mode": "list", "customColors": colors}}


def _line_thresholds(unit, threshold):
    if threshold is None:
        return None
    value, label = threshold
    return [{"value": value, "unit": unit, "color": "Red", "label": label}]


def timeseries(title, description, specs, unit="none", threshold=None, fill="none", step=False, zero_fill=False,
               colors=None, soft=(0, 0)):
    """zero_fill=True draws an empty interval as 0 (counts only; never a percentage or a latency).
    soft=(low, high) is the axis range while the data stays inside it; (0, 0) is automatic."""
    spec = {"visualization": {"timePreference": "global_time", "fillSpans": zero_fill},
            "formatting": {"unit": unit, "decimalPrecision": "2"},
            "chartAppearance": {"lineInterpolation": "step_after" if step else "linear", "showPoints": False,
                                "lineStyle": "solid", "fillMode": fill,
                                "spanGaps": {"fillOnlyBelow": False, "fillLessThan": ""}},
            **_axes_legend(colors, soft), "thresholds": _line_thresholds(unit, threshold)}
    return _panel(title, description, "signoz/TimeSeriesPanel", spec, _queries("time_series", specs))


def bars(title, description, specs, unit="none", stacked=True, colors=None, zero_fill=False):
    """colors: {legend label: hex}. SigNoz sizes the bars itself (about 80 per range).
    zero_fill: for sparse small counts. Without a threshold SigNoz ignores softMin (getYAxisScale.ts returns
    auto), so the axis starts at the smallest value and a bar worth 1 is drawn with no height; filled empty
    buckets bring the axis down to 0."""
    spec = {"visualization": {"timePreference": "global_time", "fillSpans": zero_fill, "stackedBarChart": stacked},
            "formatting": {"unit": unit, "decimalPrecision": "0"}, **_axes_legend(colors), "thresholds": None}
    return _panel(title, description, "signoz/BarChartPanel", spec, _queries("time_series", specs))


def number(title, description, specs, unit="none", precision="0", bad_above=None, bad_below=None):
    """Single value for the selected period. bad_above / bad_below paint the tile red past the limit."""
    thresholds = None
    if bad_above is not None:
        thresholds = [{"value": bad_above, "operator": "above", "unit": unit, "color": "Red", "format": "background"},
                      {"value": bad_above, "operator": "below_or_equal", "unit": unit, "color": "Green",
                       "format": "text"}]
    if bad_below is not None:
        thresholds = [{"value": bad_below, "operator": "below", "unit": unit, "color": "Red", "format": "background"},
                      {"value": bad_below, "operator": "above_or_equal", "unit": unit, "color": "Green",
                       "format": "text"}]
    spec = {"visualization": {"timePreference": "global_time"},
            "formatting": {"unit": unit, "decimalPrecision": precision}, "thresholds": thresholds}
    return _panel(title, description, "signoz/NumberPanel", spec, _queries("scalar", specs))


def pie(title, description, specs, colors=None):
    spec = {"visualization": {"timePreference": "global_time"},
            "formatting": {"unit": "none", "decimalPrecision": "0"},
            "legend": {"position": "right", "mode": "list", "customColors": colors}}
    return _panel(title, description, "signoz/PieChartPanel", spec, _queries("scalar", specs))


def table(title, description, specs, units=None, red_above=None):
    """units: {"C": "ns"}. red_above: {"B": 0} paints a column's cells red above the value.
    Both are keyed by query name, or by col(query name, expression) for a several-aggregations query."""
    thresholds = [{"value": v, "operator": "above", "unit": (units or {}).get(col, "none"), "color": "Red",
                   "format": "text", "columnName": col} for col, v in (red_above or {}).items()] or None
    spec = {"visualization": {"timePreference": "global_time"},
            "formatting": {"columnUnits": units or {}, "decimalPrecision": "2"}, "thresholds": thresholds}
    return _panel(title, description, "signoz/TablePanel", spec, _queries("scalar", specs))


def spans(title, description, flt, columns, order="timestamp", limit=20):
    """List of spans. order='timestamp' is newest first, order='duration_nano' is slowest first."""
    query = {"kind": "raw", "spec": {"name": "A", "plugin": {"kind": "signoz/BuilderQuery", "spec": _builder(
        "A", q(None, flt), raw_order=order, raw_limit=limit)}}}
    return _panel(title, description, "signoz/ListPanel", {"selectFields": [field(c) for c in columns]}, [query])


def note(markdown):
    spec = {"mode": "markdown", "text": markdown,
            "presentation": {"textAlign": "left", "verticalAlign": "top"}, "headerOptions": {"hide": True}}
    return _panel("Note", "", "signoz/TextPanel", spec, [])


# ------------------------------------------------------------------------------------------------
# Dashboard
# ------------------------------------------------------------------------------------------------
def service_variable():
    return {"kind": "ListVariable", "spec": {
        "display": {"name": "service.name", "description": "Deployment(s) to show"},
        "allowAllValue": True, "allowMultiple": True, "customAllValue": "", "capturingRegexp": "",
        "sort": "alphabetical-asc",
        "plugin": {"kind": "signoz/DynamicVariable", "spec": {"name": "service.name", "signal": "traces"}},
        "name": "service.name"}}


def _column_count(panel):
    """Columns a table or list panel renders; 0 for every other kind."""
    plugin = panel["spec"]["plugin"]
    if plugin["kind"] == "signoz/ListPanel":
        return 1 + len(plugin["spec"]["selectFields"])  # SigNoz puts the timestamp first
    if plugin["kind"] != "signoz/TablePanel":
        return 0
    inner = panel["spec"]["queries"][0]["spec"]["plugin"]
    envelopes = inner["spec"]["queries"] if inner["kind"] == "signoz/CompositeQuery" else [
        {"type": "builder_query", "spec": inner["spec"]}]
    builders = [e["spec"] for e in envelopes if e["type"] == "builder_query"]
    values = sum(len(b["aggregations"]) for b in builders if not b["disabled"])
    values += sum(1 for e in envelopes if e["type"] == "builder_formula")
    return len(builders[0]["groupBy"]) + values


def slug(text):
    return re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")[:60]


def dashboard(filename, title, description, category, sections):
    """sections: [(section title, open?, [row, ...])]; row: (height, [(panel, width), ...]), widths sum to 12."""
    panels, layouts = {}, []
    for section_title, is_open, rows in sections:
        items, y = [], 0
        for height, row in rows:
            assert sum(w for _, w in row) == GRID, f"{title} / {section_title}: row is not {GRID} wide"
            x = 0
            for p, width in row:
                name = p["spec"]["display"]["name"]
                assert _column_count(p) <= MAX_COLUMNS[width], (
                    f"{title} / {name}: {_column_count(p)} columns need a wider panel than {width} "
                    f"(max {MAX_COLUMNS[width]})")
                pid = slug(f"{section_title}-{name}")
                assert pid not in panels, f"{title}: duplicate panel id {pid}"
                panels[pid] = p
                items.append({"x": x, "y": y, "width": width, "height": height,
                              "content": {"$ref": f"#/spec/panels/{pid}"}})
                x += width
            y += height
        layouts.append({"kind": "Grid", "spec": {
            "display": {"title": section_title, "collapse": {"open": is_open}}, "items": items}})

    doc = {"schemaVersion": "v6", "image": "/assets/Icons/eight-ball", "generateName": True,
           "tags": [{"key": "app", "value": "rcp"}, {"key": "category", "value": category}],
           "spec": {"display": {"name": title, "description": description},
                    "variables": [service_variable()], "panels": panels, "layouts": layouts,
                    "duration": "", "refreshInterval": "", "links": []}}
    path = f"{OUT_DIR}/{filename}"
    with open(path, "w", encoding="utf-8") as fh:
        fh.write(json.dumps(doc, indent=2) + "\n")
    print(f"{path}: {len(panels)} panels in {len(layouts)} sections")


# ------------------------------------------------------------------------------------------------
# Shared filters
# ------------------------------------------------------------------------------------------------
SVC = "service.name IN $service.name"
SERVER = f"{SVC} AND kind_string = 'Server'"
FAILED = f"{SERVER} AND has_error = true"
ROUTED = f"{SERVER} AND http.route EXISTS"
BROWSER = f"{SVC} AND frontend.error = true"
# Outbound HTTP made through Laravel's Http:: client. SQL spans are Client kind too, but carry no HTTP method.
OUTBOUND = f"{SVC} AND kind_string = 'Client' AND http.request.method EXISTS"
WEBHOOK = f"{SERVER} AND http.route LIKE '%payments/webhook%'"
STATEMENTS = f"{SVC} AND name LIKE 'sql%'"          # one span per executed statement (Laravel)
DB_SPANS = f"{SVC} AND db.system.name EXISTS"       # every database span, PDO included; failures live here
JOBS = f"{SVC} AND kind_string = 'Consumer' AND name LIKE 'process %'"
TICK = f"{SVC} AND name = 'Command schedule:run'"
# schedule:run is the scheduler itself and queue:work the worker process; neither is a scheduled command.
COMMANDS = (f"{SVC} AND name LIKE 'Command %' AND name != 'Command schedule:run' "
            "AND name != 'Command queue:work'")
USERS = f"{SERVER} AND enduser.id EXISTS"
DEBTORS = f"{SERVER} AND app.client.id EXISTS"
DENIED = f"{SERVER} AND response_status_code IN ('401', '403', '419', '429')"

BY_SVC = ["service.name"]
ROUTE = ["service.name", "http_method", "http.route"]
REQUEST_COLUMNS = ["service.name", "name", "response_status_code", "duration_nano"]
CALL_COLUMNS = ["service.name", "name", "url.full", "response_status_code", "duration_nano"]
SOURCE = " Generated by docs/observability/signoz/tools/build_dashboards.py; edit the generator, not the JSON."

KPI, CHART, TABLE, LIST = 3, 7, 8, 8
P95, TOTAL = "p95(duration_nano)", "sum(duration_nano)"

# The one validated four-colour set (see the docstring). Used wherever a colour means something.
OK, INFO, WARN, BAD = "#1baf7a", "#3987e5", "#c98500", "#d03b3b"
STATUS_CLASS_COLORS = {"2xx": OK, "3xx": INFO, "4xx": WARN, "5xx": BAD}
# Refusals by how much they matter: expired session (benign), throttled, not signed in, not allowed.
REFUSAL_COLORS = {"419": OK, "429": INFO, "401": WARN, "403": BAD}


def with_failures(total, base, by, error_pct=False, available_pct=False):
    """Total, Failed and optionally a percentage for a table: A = all, B = successful (hidden),
    F1 = A-B. As a formula a group without failures reads 0; as a plain query it would read n/a."""
    specs = [q("count()", base, by=by, legend=total, order=True),
             q("count()", f"{base} AND has_error = false", by=by, hidden=True),
             f("A-B", "Failed")]
    if error_pct:
        specs.append(f("((A-B)/A)*100", "Error %"))
    if available_pct:
        specs.append(f("(B/A)*100", "Availability %"))
    return specs


def named(label, flt):
    """A count() series with a fixed label (the alias pins it), so a colour can be keyed to it."""
    return q([("count()", label)], flt, legend=label)


def status_classes(base):
    """2xx / 3xx / 4xx / 5xx as four series. A span without a status code is in none of them."""
    return [named(label, f"{base} AND response_status_code LIKE '{label[0]}%'") for label in STATUS_CLASS_COLORS]


def error_rate(base, by, legend, hidden_legend=""):
    """A = failed, B = all, F1 = A/B*100. Both inputs stay hidden."""
    return [q("count()", f"{base} AND has_error = true", by=by, legend=hidden_legend, hidden=True),
            q("count()", base, by=by, legend=hidden_legend, hidden=True),
            f("(A/B)*100", legend)]


# ================================================================================================
# HEALTH
# ================================================================================================
dashboard(
    "rcp-service-health.json", "RCP · Service health",
    "Landing page: is the API broken or slow right now? Traffic, errors and latency only. Every other "
    "question has its own dashboard (tag app:rcp)." + SOURCE,
    "health",
    [
        ("Right now", True, [
            (KPI, [
                (number("Requests", "API requests in the selected period.", [q("count()", SERVER)]), 2),
                (number("Error rate", "Requests that ended in a 5xx or an unhandled exception. Alert 1 fires at 5 %.",
                        error_rate(SERVER, [], "Error rate"), unit="percent", precision="2", bad_above=5), 2),
                (number("Failed requests", "Absolute number behind the error rate.",
                        [q("count()", FAILED)], bad_above=0), 2),
                (number("p95 latency", "95 % of requests were faster than this. Alert 2 fires at 2 s.",
                        [q("p95(duration_nano)", SERVER)], unit="ns", precision="2", bad_above=2_000_000_000), 2),
                (number("p99 latency", "The slow tail.", [q("p99(duration_nano)", SERVER)], unit="ns",
                        precision="2"), 2),
                (number("Browser errors", "JavaScript errors reported by the frontend. Alert 6 watches a spike.",
                        [q("count()", BROWSER)]), 2),
            ]),
        ]),
        ("Trends", True, [
            (CHART, [
                (timeseries("Requests", "Per interval, by deployment.",
                            [q("count()", SERVER, by=BY_SVC, legend="{{service.name}}")], fill="gradient"), 6),
                (timeseries("Error rate %", "On a quiet deployment one failure moves this a lot; read it with "
                            "the request volume.", error_rate(SERVER, BY_SVC, "{{service.name}}"),
                            unit="percent", threshold=(5, "alert at 5 %")), 6),
            ]),
            (CHART, [
                (timeseries("Latency p50 / p95 / p99", "Server-side duration of API requests.",
                            [q("p50(duration_nano)", SERVER, by=BY_SVC, legend="p50 {{service.name}}"),
                             q("p95(duration_nano)", SERVER, by=BY_SVC, legend="p95 {{service.name}}"),
                             q("p99(duration_nano)", SERVER, by=BY_SVC, legend="p99 {{service.name}}")],
                            unit="ns", threshold=(2_000_000_000, "alert at 2 s")), 6),
                (bars("Responses by status class", "Green is served, amber (4xx) is a client or permission "
                      "problem, red (5xx) is ours. Single codes: RCP · Endpoints and RCP · Security & access.",
                      status_classes(SERVER), colors=STATUS_CLASS_COLORS), 6),
            ]),
        ]),
        ("Where to look", True, [
            (TABLE, [
                (table("Endpoints with errors", "Most failing first. Counts every failed request, so it adds up to "
                       "the red tile at the top; a request that failed before it reached a route shows n/a as "
                       "its route. Details: RCP · Endpoints.",
                       [q("count()", FAILED, by=ROUTE + ["response_status_code"], legend="Failed", order=True,
                          limit=10)]), 6),
                (table("Slowest endpoints", "The 10 slowest by p95, with the number of requests so one slow call "
                       "does not mislead.",
                       [q([(P95, "p95"), ("count()", "Requests")], ROUTED, by=ROUTE, order=True, limit=10)],
                       units={col("A", P95): "ns"}), 6),
            ]),
            (LIST, [
                (spans("Latest failed requests", "Newest first. Click a row to open the trace.", FAILED,
                       REQUEST_COLUMNS), 12),
            ]),
        ]),
        ("Browser errors", False, [
            (CHART, [
                (bars("Browser errors over time", "Reported by the React app through POST /client-errors.",
                      [q("count()", BROWSER, by=BY_SVC, legend="{{service.name}}")], zero_fill=True), 4),
                (spans("Latest browser errors", "Newest first.", BROWSER, ["service.name", "name", "enduser.id"]), 8),
            ]),
        ]),
    ],
)

dashboard(
    "rcp-availability.json", "RCP · Availability",
    "Are the deployments up, and how available were they over the selected period (set 7 or 30 days for the "
    "SLO view)? Request-based: a request fails when it ends in a 5xx or an unhandled exception. A full outage "
    "produces no requests at all, so read the heartbeats together with the GCP uptime checks on /api/health."
    + SOURCE,
    "health",
    [
        ("Selected period", True, [
            (KPI, [
                (number("Availability", "Successful requests / all requests. Health-check probes are not traced, "
                        "so they do not inflate this.",
                        [q("count()", f"{SERVER} AND has_error = false", hidden=True),
                         q("count()", SERVER, hidden=True), f("(A/B)*100", "Availability")],
                        unit="percent", precision="3", bad_below=99.5), 3),
                (number("Requests", "", [q("count()", SERVER)]), 3),
                (number("Failed (5xx)", "", [q("count()", FAILED)], bad_above=0), 3),
                (number("Dependency availability", "Outbound HTTP calls that succeeded at HTTP level.",
                        [q("count()", f"{OUTBOUND} AND has_error = false", hidden=True),
                         q("count()", OUTBOUND, hidden=True), f("(A/B)*100", "Availability")],
                        unit="percent", precision="2", bad_below=99), 3),
            ]),
            (CHART, [
                (timeseries("Availability % over time", "Below the red line the SLO of 99.5 % is being burned.",
                            [q("count()", f"{SERVER} AND has_error = false", by=BY_SVC, hidden=True),
                             q("count()", SERVER, by=BY_SVC, hidden=True), f("(A/B)*100", "{{service.name}}")],
                            unit="percent", threshold=(99.5, "SLO 99.5 %"), soft=(99, 100)), 7),
                (table("By deployment", "", with_failures("Requests", SERVER, BY_SVC, available_pct=True),
                       units={"F2": "percent"}, red_above={"F1": 0}), 5),
            ]),
        ]),
        ("Heartbeats", True, [
            (CHART, [
                (timeseries("Traffic", "A line that drops to zero: the deployment is down or stopped reporting. "
                            "Alert 3 (Deployment silent) watches this.",
                            [q("count()", SERVER, by=BY_SVC, legend="{{service.name}}")], fill="gradient",
                            zero_fill=True), 4),
                (bars("Scheduler: schedule:run", "Cron starts the scheduler every minute, so a deployment's bars "
                      "are all equally tall; a hole or a short bar means cron stopped. Twice the expected height "
                      "means two crons. Empty without OTEL_PHP_TRACE_CLI_ENABLED=true.",
                      [q("count()", TICK, by=BY_SVC, legend="{{service.name}}")]), 4),
                (bars("Queue workers: jobs processed", "No processed jobs while users export or send SMS "
                      "means the workers are down, or were never restarted after tracing was switched on.",
                      [q("count()", JOBS, by=BY_SVC, legend="{{service.name}}")]), 4),
            ]),
        ]),
        ("What was unavailable", True, [
            (TABLE, [
                (table("Endpoints returning 5xx", "Counts every failed request; one that failed before it reached a "
                       "route (maintenance mode, a global middleware) shows n/a as its route.",
                       [q("count()", FAILED, by=ROUTE + ["response_status_code"], legend="Failed", order=True,
                          limit=20)]), 6),
                (table("Dependency availability by host", "A gateway that answers 200 with an error in the body "
                       "still counts as available here.",
                       with_failures("Calls", OUTBOUND, ["server.address"], available_pct=True),
                       units={"F2": "percent"}, red_above={"F1": 0}), 6),
            ]),
            (LIST, [(spans("Latest failed requests", "Newest first.", FAILED, REQUEST_COLUMNS), 12)]),
        ]),
    ],
)

# ================================================================================================
# APPLICATION
# ================================================================================================
dashboard(
    "rcp-endpoints.json", "RCP · Endpoints",
    "Per route: which features are used, which are slow, which fail. Drill-down for RCP · Service health."
    + SOURCE,
    "application",
    [
        ("All endpoints", True, [
            (12, [
                (table("Endpoint summary", "One row per route, busiest first. Click a header to sort.",
                       with_failures("Requests", ROUTED, ROUTE, error_pct=True) + [
                           q("p50(duration_nano)", ROUTED, by=ROUTE, legend="p50", name="P"),
                           q("p95(duration_nano)", ROUTED, by=ROUTE, legend="p95", name="Q"),
                           q("p99(duration_nano)", ROUTED, by=ROUTE, legend="p99", name="R")],
                       units={"P": "ns", "Q": "ns", "R": "ns", "F2": "percent"}, red_above={"F1": 0}), 12),
            ]),
        ]),
        ("Busiest and slowest over time", True, [
            (CHART, [
                (timeseries("Requests: top 10 routes", "", [q("count()", ROUTED, by=["http.route"],
                            legend="{{http.route}}", order=True, limit=10)]), 6),
                (timeseries("p95 latency: 10 slowest routes", "", [q("p95(duration_nano)", ROUTED, by=["http.route"],
                            legend="{{http.route}}", order=True, limit=10)], unit="ns"), 6),
            ]),
        ]),
        ("Client errors (4xx)", True, [
            (TABLE, [
                (pie("Responses by status class", "Same colours as on RCP · Service health.",
                     status_classes(SERVER), colors=STATUS_CLASS_COLORS), 4),
                (table("4xx by route and status", "Adds up to the 4xx slice. Route n/a is a path that matched no "
                       "route, mostly scanners. 422 is validation, 404 on a listed route is a missing record. "
                       "401 / 403 / 419 / 429 are on RCP · Security & access.",
                       [q("count()", f"{SERVER} AND response_status_code LIKE '4%'",
                          by=ROUTE + ["response_status_code"], legend="Responses", order=True, limit=30)]), 8),
            ]),
        ]),
        ("Slowest requests", False, [
            (LIST, [(spans("Slowest requests", "Slowest first. Click a row to open the trace.", SERVER,
                           REQUEST_COLUMNS, order="duration_nano"), 12)]),
        ]),
    ],
)

dashboard(
    "rcp-database.json", "RCP · Database",
    "The database as the application sees it: how many statements, how slow, which ones. Requests, scheduled "
    "commands and queue workers are all in here. Cloud SQL's own CPU, connections and locks need the mysql "
    "receiver and are not here." + SOURCE,
    "application",
    [
        ("Selected period", True, [
            (KPI, [
                (number("Statements", "Executed SQL statements.", [q("count()", STATEMENTS)]), 3),
                (number("Time in SQL", "All statement durations added up: what the application asked of the "
                        "database. The table below says which statements it went to.",
                        [q(TOTAL, STATEMENTS)], unit="ns", precision="1"), 3),
                (number("p95 duration", "", [q("p95(duration_nano)", STATEMENTS)], unit="ns", precision="2"), 3),
                (number("Failed statements", "Failed connects, prepares and executes.",
                        [q("count()", f"{DB_SPANS} AND has_error = true")], bad_above=0), 3),
            ]),
        ]),
        ("Trends", True, [
            (CHART, [
                (bars("Statements by operation", "Span name: sql SELECT, sql INSERT, ...",
                      [q("count()", STATEMENTS, by=["name"], legend="{{name}}")]), 6),
                (timeseries("Duration p50 / p95", "",
                            [q("p50(duration_nano)", STATEMENTS, by=BY_SVC, legend="p50 {{service.name}}"),
                             q("p95(duration_nano)", STATEMENTS, by=BY_SVC, legend="p95 {{service.name}}")],
                            unit="ns"), 6),
            ]),
        ]),
        ("Statements", True, [
            (10, [
                (table("Heaviest statements", "The 30 that cost the most total time, which is what loads the "
                       "database: a fast statement that runs a million times beats one slow report.",
                       [q([(TOTAL, "Total time"), ("count()", "Runs"), (P95, "p95")], STATEMENTS,
                          by=["db.query.text"], order=True, limit=30)],
                       units={col("A", TOTAL): "ns", col("A", P95): "ns"}), 12),
            ]),
            (TABLE, [
                (table("Load by database", "Which deployment works on which database. A database you did not "
                       "expect under a service means two applications share one service name.",
                       [q([(TOTAL, "Total time"), ("count()", "Statements")], STATEMENTS,
                          by=["db.namespace", "service.name"], order=True, limit=30)],
                       units={col("A", TOTAL): "ns"}), 6),
                (table("Failing statements", "A failed statement exists only as a PDO span, so the operation is "
                       "PDO::connect, PDOStatement::execute, ... and the text can be n/a.",
                       [q("count()", f"{DB_SPANS} AND has_error = true",
                          by=["service.name", "name", "db.query.text"], legend="Failed", order=True,
                          limit=30)]), 6),
            ]),
            (LIST, [(spans("Slowest statements", "Slowest first. Click a row to see which request or job ran it.",
                           STATEMENTS, ["service.name", "name", "db.namespace", "db.query.text", "duration_nano"],
                           order="duration_nano"), 12)]),
        ]),
    ],
)

JOB = ["service.name", "messaging.message.job_name"]
CMD = ["service.name", "name"]
dashboard(
    "rcp-background-work.json", "RCP · Background work",
    "Queue jobs and scheduled commands: running, failing or slow? Needs OTEL_PHP_TRACE_CLI_ENABLED=true on "
    "the deployment, otherwise everything here is empty." + SOURCE,
    "application",
    [
        ("Selected period", True, [
            (KPI, [
                (number("Jobs processed", "", [q("count()", JOBS)]), 2),
                (number("Jobs failed", "Alert 4 fires on any.", [q("count()", f"{JOBS} AND has_error = true")],
                        bad_above=0), 2),
                (number("Job p95 duration", "", [q("p95(duration_nano)", JOBS)], unit="ns", precision="2"), 2),
                (number("Command runs", "Scheduled and manual commands, without schedule:run and queue:work.",
                        [q("count()", COMMANDS)]), 2),
                (number("Commands failed", "Alert 5 fires on any.",
                        [q("count()", f"{COMMANDS} AND has_error = true")], bad_above=0), 2),
                (number("Scheduler ticks", "One per minute per deployment when cron is healthy.",
                        [q("count()", TICK)]), 2),
            ]),
        ]),
        ("Queue jobs", True, [
            (CHART, [
                (bars("Processed vs failed", "",
                      [named("processed", f"{JOBS} AND has_error = false"),
                       named("failed", f"{JOBS} AND has_error = true")],
                      colors={"processed": OK, "failed": BAD}), 6),
                (timeseries("p95 duration by queue", "Span name is 'process <queue>'.",
                            [q("p95(duration_nano)", JOBS, by=["name"], legend="{{name}}")], unit="ns"), 6),
            ]),
            (TABLE, [
                (table("Jobs by class", "",
                       with_failures("Runs", JOBS, JOB) + [
                           q("p95(duration_nano)", JOBS, by=JOB, legend="p95", name="P"),
                           q("max(duration_nano)", JOBS, by=JOB, legend="Longest", name="Q")],
                       units={"P": "ns", "Q": "ns"}, red_above={"F1": 0}), 12),
            ]),
            (LIST, [(spans("Latest failed jobs", "Newest first.", f"{JOBS} AND has_error = true",
                           ["service.name", "name", "messaging.message.job_name", "duration_nano"]), 12)]),
        ]),
        ("Scheduled commands", True, [
            (CHART, [
                (bars("Scheduler heartbeat", "schedule:run, once a minute per cron: a deployment's bars are all "
                      "equally tall, a hole means cron stopped, twice the height means two crons.",
                      [q("count()", TICK, by=BY_SVC, legend="{{service.name}}")]), 4),
                (bars("Command runs", "", [q("count()", COMMANDS, by=["name"], legend="{{name}}")]), 8),
            ]),
            (TABLE, [
                (table("Commands", "Every artisan command except schedule:run and queue:work. An everyMinute() "
                       "command runs 1,440 times a day per cron.",
                       with_failures("Runs", COMMANDS, CMD) + [
                           q("p95(duration_nano)", COMMANDS, by=CMD, legend="p95", name="P"),
                           q("max(duration_nano)", COMMANDS, by=CMD, legend="Longest", name="Q")],
                       units={"P": "ns", "Q": "ns"}, red_above={"F1": 0}), 12),
            ]),
            (LIST, [(spans("Latest failed commands", "Newest first.", f"{COMMANDS} AND has_error = true",
                           ["service.name", "name", "duration_nano"]), 12)]),
        ]),
    ],
)

# ================================================================================================
# DEPENDENCIES
# ================================================================================================
HOST = ["server.address"]
OPERATION = ["server.address", "http.request.method", "url.path"]
dashboard(
    "rcp-integrations.json", "RCP · Integrations",
    "Outbound calls to the payment middleware, SMS gateways (Msegat, Unifonic, GIG, AlRajhi Takaful), IVR and "
    "other HTTP dependencies, plus the inbound payment webhook. Transport level only: a gateway that answers "
    "200 with an error in the body counts as a success. ELM (SOAP), Urway's curl call and Cloud Storage are "
    "not traced yet." + SOURCE,
    "dependencies",
    [
        ("Selected period", True, [
            (KPI, [
                (number("Outbound calls", "", [q("count()", OUTBOUND)]), 3),
                (number("Error rate", "Connection failures and 4xx / 5xx answers.",
                        error_rate(OUTBOUND, [], "Error rate"), unit="percent", precision="2", bad_above=5), 3),
                (number("p95 latency", "How long users and jobs wait for a dependency.",
                        [q("p95(duration_nano)", OUTBOUND)], unit="ns", precision="2"), 3),
                (number("Failed payment webhooks", "Payments made while this fails are not reflected on the claim.",
                        [q("count()", f"{WEBHOOK} AND has_error = true")], bad_above=0), 3),
            ]),
        ]),
        ("By host", True, [
            (CHART, [
                (timeseries("Calls", "", [q("count()", OUTBOUND, by=HOST, legend="{{server.address}}")]), 4),
                (timeseries("Error rate %", "Empty means no call failed.",
                            error_rate(OUTBOUND, HOST, "{{server.address}}"), unit="percent"), 4),
                (timeseries("p95 latency", "", [q("p95(duration_nano)", OUTBOUND, by=HOST,
                            legend="{{server.address}}")], unit="ns"), 4),
            ]),
            (TABLE, [
                (table("Dependency summary", "One row per deployment and host.",
                       with_failures("Calls", OUTBOUND, ["service.name", "server.address"], error_pct=True) + [
                           q("p95(duration_nano)", OUTBOUND, by=["service.name", "server.address"], legend="p95",
                             name="P")],
                       units={"P": "ns", "F2": "percent"}, red_above={"F1": 0}), 12),
            ]),
        ]),
        ("Failures and operations", True, [
            (TABLE, [
                (table("Failed calls by host and status", "An empty status means the connection itself failed "
                       "(timeout, DNS, TLS).",
                       [q("count()", f"{OUTBOUND} AND has_error = true", by=["server.address", "response_status_code"],
                          legend="Failed", order=True, limit=30)]), 5),
                (table("Most called operations", "The 30 most called. Paths that contain ids appear as separate rows.",
                       [q([("count()", "Calls"), (P95, "p95")], OUTBOUND, by=OPERATION, order=True, limit=30)],
                       units={col("A", P95): "ns"}), 7),
            ]),
            (LIST, [(spans("Latest failed calls", "Newest first.", f"{OUTBOUND} AND has_error = true",
                           CALL_COLUMNS), 12)]),
            (LIST, [(spans("Slowest calls", "Slowest first. Click a row to see which request or job was waiting.",
                           OUTBOUND, CALL_COLUMNS, order="duration_nano"), 12)]),
        ]),
        ("Payment webhook (inbound)", True, [
            (CHART, [
                (bars("Received vs failed", "Callbacks from the payment middleware to POST /api/v1/payments/webhook.",
                      [named("ok", f"{WEBHOOK} AND has_error = false"),
                       named("failed", f"{WEBHOOK} AND has_error = true")],
                      colors={"ok": OK, "failed": BAD}, zero_fill=True), 6),
                (timeseries("Webhook p95 duration", "", [q("p95(duration_nano)", WEBHOOK, by=BY_SVC,
                            legend="{{service.name}}")], unit="ns"), 6),
            ]),
        ]),
    ],
)

# ================================================================================================
# PRODUCT
# ================================================================================================
TENANT = ["app.tenant"]
USER = ["app.tenant", "enduser.id", "enduser.role"]
dashboard(
    "rcp-users-tenants.json", "RCP · Users & tenants",
    "Who uses the system: active users, usage per tenant and role, debtor (client view) sessions, and who is "
    "hitting errors." + SOURCE,
    "product",
    [
        ("Read this first", True, [
            (2, [(note("**Empty panels are a deployment gap, not a dashboard bug.** User and tenant attributes "
                       "(`enduser.id`, `app.tenant`, `app.client.id`) come from the `EnrichTraceContext` middleware. "
                       "A deployment that does not run it yet shows nothing here. Check with `app.tenant EXISTS` "
                       "in the Traces explorer."), 12)]),
        ]),
        ("Selected period", True, [
            (KPI, [
                (number("Active users", "Distinct signed-in users.", [q("count_distinct(enduser.id)", USERS)]), 3),
                (number("Active tenants", "", [q("count_distinct(app.tenant)", f"{SERVER} AND app.tenant EXISTS")]), 3),
                (number("Debtor sessions", "Distinct debtors who opened the client view.",
                        [q("count_distinct(app.client.id)", DEBTORS)]), 3),
                (number("Users who hit an error", "", [q("count_distinct(enduser.id)",
                        f"{USERS} AND has_error = true")], bad_above=0), 3),
            ]),
        ]),
        ("Tenants", True, [
            (CHART, [
                (timeseries("Requests by tenant", "", [q("count()", f"{SERVER} AND app.tenant EXISTS", by=TENANT,
                            legend="{{app.tenant}}")]), 6),
                (timeseries("Active users by tenant", "Distinct users per interval.",
                            [q("count_distinct(enduser.id)", USERS, by=TENANT, legend="{{app.tenant}}")]), 6),
            ]),
            (TABLE, [
                (table("Tenant summary", "",
                       with_failures("Requests", USERS, TENANT + BY_SVC) + [
                           q("count_distinct(enduser.id)", USERS, by=TENANT + BY_SVC, legend="Active users",
                             name="P"),
                           q("p95(duration_nano)", USERS, by=TENANT + BY_SVC, legend="p95", name="Q")],
                       units={"Q": "ns"}, red_above={"F1": 0}), 12),
            ]),
        ]),
        ("Users", True, [
            (TABLE, [
                (table("Most active users", "", [q("count()", USERS, by=USER, legend="Requests", order=True,
                       limit=30)]), 5),
                (pie("Requests by role", "", [q("count()", USERS, by=["enduser.role"], legend="{{enduser.role}}")]), 3),
                (table("Errors by user", "Someone near the top is stuck; call them before they call you.",
                       [q("count()", f"{USERS} AND has_error = true", by=["app.tenant", "enduser.id"],
                          legend="Errors", order=True, limit=30)]), 4),
            ]),
        ]),
        ("Client view (debtors)", True, [
            (TABLE, [
                (timeseries("Debtor sessions", "Distinct debtors per interval.",
                            [q("count_distinct(app.client.id)", DEBTORS, by=TENANT, legend="{{app.tenant}}")]), 5),
                (table("Errors in client view", "A debtor who cannot pay.",
                       [q("count()", f"{DEBTORS} AND has_error = true",
                          by=["app.tenant", "app.client.type", "app.client.id", "app.claim.id"], legend="Errors",
                          order=True, limit=30)]), 7),
            ]),
        ]),
    ],
)

AUTH = f"{SERVER} AND http.route LIKE '%/auth/%' AND response_status_code LIKE '4%'"
CLIENT_VIEW = f"{SERVER} AND http.route LIKE '%claim-info%' AND response_status_code LIKE '4%'"
dashboard(
    "rcp-security-access.json", "RCP · Security & access",
    "Refused requests: 401 not signed in, 403 not allowed, 419 expired session / CSRF, 429 throttled. Who is "
    "being refused, where from, and is someone probing the login or the debtor links?" + SOURCE,
    "security",
    [
        ("Selected period", True, [
            (KPI, [
                (number("401 Unauthenticated", "", [q("count()", f"{SERVER} AND response_status_code = '401'")]), 3),
                (number("403 Forbidden", "", [q("count()", f"{SERVER} AND response_status_code = '403'")]), 3),
                (number("419 Session expired", "", [q("count()", f"{SERVER} AND response_status_code = '419'")]), 3),
                (number("429 Throttled", "", [q("count()", f"{SERVER} AND response_status_code = '429'")]), 3),
            ]),
            (CHART, [
                (bars("Refused requests by status", "A sudden wall of one colour is an attack or a broken release. "
                      "Red is 403, amber 401, blue 429, green 419.",
                      [q("count()", DENIED, by=["response_status_code"], legend="{{response_status_code}}")],
                      colors=REFUSAL_COLORS, zero_fill=True), 12),
            ]),
        ]),
        ("Who and where", True, [
            (TABLE, [
                (table("By route", "", [q("count()", f"{DENIED} AND http.route EXISTS",
                       by=ROUTE + ["response_status_code"], legend="Refused", order=True, limit=30)]), 6),
                (table("By client address", "Many refusals from one address: a script or a scanner.",
                       [q("count()", DENIED, by=["client.address", "response_status_code"], legend="Refused",
                          order=True, limit=30)]), 6),
            ]),
            (TABLE, [
                (table("By user", "403 for a signed-in user is a missing permission or a user trying things. "
                       "Needs the EnrichTraceContext middleware on the deployment.",
                       [q("count()", f"{DENIED} AND enduser.id EXISTS",
                          by=["app.tenant", "enduser.id", "enduser.role", "response_status_code"], legend="Refused",
                          order=True, limit=30)]), 12),
            ]),
        ]),
        ("Login and debtor links", True, [
            (TABLE, [
                (table("Auth endpoints answering 4xx", "Failed logins, password resets and OTP checks under /auth/.",
                       [q("count()", AUTH, by=["http.route", "response_status_code", "client.address"],
                          legend="Responses", order=True, limit=30)]), 6),
                (table("Client view answering 4xx", "Wrong verification codes and guessed links under /claim-info/.",
                       [q("count()", CLIENT_VIEW, by=["http.route", "response_status_code", "client.address"],
                          legend="Responses", order=True, limit=30)]), 6),
            ]),
            (LIST, [(spans("Latest refused requests", "Newest first.", DENIED,
                           ["service.name", "name", "response_status_code", "client.address", "enduser.id"]), 12)]),
        ]),
    ],
)
