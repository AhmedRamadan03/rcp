# Observability: OpenTelemetry → SigNoz

How the RCP API reports errors, latency, usage and background work to SigNoz,
how to configure a deployment, how to check it is working, and what to look at.

## How it works

```
php-fpm / queue worker / scheduler
  └─ opentelemetry PHP extension
       └─ open-telemetry/opentelemetry-auto-laravel  (one span per request, job, command;
          open-telemetry/opentelemetry-auto-pdo       exceptions and log lines attached)
          open-telemetry/opentelemetry-auto-http-async
               └─ OTLP over HTTP (:4318) ──► SigNoz collector ──► SigNoz UI
```

Nothing in application code starts spans. The auto-instrumentation packages in
`composer.json` hook Laravel's HTTP kernel, queue worker, console commands and
PDO. The application only adds context:

| Piece | What it does |
| --- | --- |
| `bootstrap/otel.php` (+ `otel-env.php`) | Exports `OTEL_*` lines from `.env` into the process environment **before** Composer's autoloader runs. Without this the SDK never sees the configuration (see *The .env trap* below). |
| `App\Http\Middleware\EnrichTraceContext` | Adds `enduser.id`, `enduser.role`, `app.tenant`, `app.client.*` / `app.claim.id` (client view) to the request span and returns the trace id in the `X-Trace-Id` response header. |
| `POST /api/v1/client-errors` | Receives errors caught by the React app and records them as exception events on the trace, so browser errors appear on the SigNoz Exceptions page. Rate limited per IP. |
| `GET /api/health` | Liveness probe for uptime checks. Excluded from tracing via `OTEL_PHP_EXCLUDED_URLS`. |

### The .env trap

The SDK reads `OTEL_PHP_AUTOLOAD_ENABLED` while `vendor/autoload.php` is being
loaded. Laravel parses `.env` later. So `OTEL_*` values that exist only in
`.env` are invisible to the SDK and tracing stays silently off. That is what
happened between April and September 2026. `bootstrap/otel.php` closes the gap:
it is required by `public/index.php` and `artisan` before the autoloader.
Values already present in the process environment (php-fpm `env[]`, supervisor
`environment=`, a php.ini file) always take precedence over `.env`.

The shim reads `.env` on every request, including when `config:cache` is on.
That is deliberate: the SDK reads the process environment, never Laravel's
config cache, so there is nothing else to read from. The cost is one small file
read per process start.

## Configuring a deployment

All configuration lives in the environment's secret in GCP Secret Manager
(the file the deploy workflow writes as `.env`). No VM edits are needed.

```ini
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_PHP_TRACE_CLI_ENABLED=true            # queue workers and scheduled commands
OTEL_SERVICE_NAME=art-rcp-api-live
OTEL_RESOURCE_ATTRIBUTES=deployment.environment=live,tenant=art,host.name=art-alrajhi-live
OTEL_EXPORTER_OTLP_ENDPOINT=http://<signoz-internal-ip>:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_TRACES_EXPORTER=otlp
OTEL_METRICS_EXPORTER=none
OTEL_LOGS_EXPORTER=none
OTEL_PHP_EXCLUDED_URLS=api/health
```

Service names and attributes per deployment:

| Deployment (workflow) | `OTEL_SERVICE_NAME` | `deployment.environment` | `tenant` |
| --- | --- | --- | --- |
| ART live | `art-rcp-api-live` | `live` | `art` |
| ART uat | `art-rcp-api-uat` | `uat` | `art` |
| ART demo | `art-rcp-api-demo` | `demo` | `art` |
| GIG live | `gig-rcp-api-live` | `live` | `gig` |
| GIG uat | `gig-rcp-api-uat` | `uat` | `gig` |
| RCP dev | `rcp-api-dev` | `dev` | `rcp` |
| RCP uat | `rcp-api-uat` | `uat` | `rcp` |

Notes:

- `OTEL_PHP_TRACE_CLI_ENABLED=true` is required for the queue workers
  (supervisor) and the scheduler (cron). Without it only web requests are traced.
- The deploy workflow restarts the supervisor workers, so a secret change takes
  effect on the next deploy. php-fpm re-reads `.env` per request, so web traffic
  picks it up immediately.
- Point `OTEL_EXPORTER_OTLP_ENDPOINT` at the SigNoz VM's **internal** IP and
  restrict port 4318 to the VPC in the GCP firewall. The OTLP receiver has no
  authentication.
- ART live also has the same values in `/etc/php/8.4/fpm/pool.d/www.conf`
  (added 2026-09-17 to prove the pipeline). They are harmless but redundant once
  this code is deployed; remove them so the secret is the single source of truth.

### Local development

The `opentelemetry` extension is optional locally. Without it every PHP process
prints a warning at startup and Composer scripts fail. Either install it
(`pecl install opentelemetry`) or silence the instrumentation:

```bash
export OTEL_PHP_DISABLED_INSTRUMENTATIONS=all
```

Leave the `OTEL_*` block in `.env` commented out unless you run a local SigNoz.

## Checking that a deployment reports

From the VM:

```bash
# 1. the collector accepts traffic (expect 200)
curl -s -o /dev/null -w '%{http_code}\n' -X POST http://<signoz>:4318/v1/traces \
  -H 'Content-Type: application/json' -d '{}'

# 2. the extension is loaded for the version that serves traffic
php8.4 -m | grep -i opentelemetry

# 3. the shim sees the configuration
cd /var/www/html/live/backend && php -r 'require "bootstrap/otel.php"; var_dump(getenv("OTEL_PHP_AUTOLOAD_ENABLED"));'
```

Then make one API request and open **Services** in SigNoz. The service name
should appear within a minute. For workers, dispatch any job and look for a
`process <queue>` span; for the scheduler look for `Command app:...` spans.

## Where to look in SigNoz

- **Services**: request rate, error % and p99 per deployment. Click a service for
  the same per endpoint. This is the "is anything broken or slow" page.
- **Traces explorer**: filter `has_error = true` to see every failed request with
  the user (`enduser.id`), the tenant and the exception. Filter
  `frontend.error = true` for errors reported by the browser.
- **Exceptions**: grouped by exception type and message. Browser errors appear as
  `FrontendError.<source>`.
- **Dashboards**: eight focused dashboards, all tagged `app:rcp`; start at
  "RCP · Service health". See [Dashboards](#dashboards) below.
- **Alerts**: see [`signoz/alerts.md`](signoz/alerts.md) for the six rules to create.

When a user reports a problem, ask for the **Reference** shown on the error page.
It is the trace id: paste it into the Traces explorer search.

## Dashboards

One dashboard per question, one file per dashboard in [`signoz/`](signoz/), in
SigNoz's V2 dashboard format (`"schemaVersion": "v6"`, SigNoz 0.135 or newer;
this installation runs 0.142.1). Each dashboard is built from collapsible
sections: a row of single-number tiles for the selected period, then trends,
then the tables and span lists that say where to look.

The files are generated. Change
[`signoz/tools/build_dashboards.py`](signoz/tools/build_dashboards.py) and
regenerate from the repository root; panel ids are derived from the titles, so
the diff stays small:

```bash
python3 docs/observability/signoz/tools/build_dashboards.py docs/observability/signoz
```

[`signoz/tools/validate.sh`](signoz/tools/validate.sh) runs SigNoz's own import
validation on the generated files (needs Docker):

```bash
docs/observability/signoz/tools/validate.sh docs/observability/signoz
```

Import with *Dashboards → New dashboard → Import JSON*. SigNoz always imports a
file as a **new** dashboard, so keep the old one until the new one is confirmed.
A panel fixed in the SigNoz UI must be carried back into the generator,
otherwise the next import undoes the fix.

Every dashboard carries the tags `app:rcp` and `category:<category>`, which the
dashboard list can filter on.

| Category | File | Question it answers | Data it needs |
| --- | --- | --- | --- |
| health | [`rcp-service-health.json`](signoz/rcp-service-health.json) | Landing page: is the API broken or slow right now? Requests, error rate, latency, status codes, the endpoints to look at, browser errors. | Traces |
| health | [`rcp-availability.json`](signoz/rcp-availability.json) | Are we up, and how available were we over 7 / 30 days? Availability % against the 99.5 % line, heartbeats for traffic, scheduler and queue workers, what was unavailable. | Traces. `OTEL_PHP_TRACE_CLI_ENABLED=true` for the scheduler and queue heartbeats. |
| application | [`rcp-endpoints.json`](signoz/rcp-endpoints.json) | Per route: usage, error %, p50 / p95 / p99, 4xx by route, slowest requests. | Traces |
| application | [`rcp-database.json`](signoz/rcp-database.json) | The database as the app sees it: statements per request, heaviest statements by total time, load per schema, failing and slowest statements. | Traces (`db.system.name`, `db.query.text`, `db.namespace`) |
| application | [`rcp-background-work.json`](signoz/rcp-background-work.json) | Queue jobs per class and scheduled commands: runs, failures, p95, longest run. | Traces with `OTEL_PHP_TRACE_CLI_ENABLED=true` |
| dependencies | [`rcp-integrations.json`](signoz/rcp-integrations.json) | Is a dependency failing or slow? Calls, error % and p95 per host, failed calls by status, most called operations, inbound payment webhook. | Traces. Transport level only, see the gaps below. |
| product | [`rcp-users-tenants.json`](signoz/rcp-users-tenants.json) | Active users and tenants, usage per tenant and role, debtor sessions, who is hitting errors. | Traces from a deployment that runs `EnrichTraceContext` (`enduser.id`, `app.tenant`, `app.client.id`). Empty otherwise. |
| security | [`rcp-security-access.json`](signoz/rcp-security-access.json) | 401 / 403 / 419 / 429 by route, client address and user; 4xx on the auth endpoints and on the debtor links. | Traces. The by-user table needs `EnrichTraceContext`. |

Planned, in the order they become possible:

| Dashboard | Question | Blocked on |
| --- | --- | --- |
| Uptime probes | Availability measured from outside, TLS certificate expiry | `httpcheck` receiver, see below |
| Hosts | CPU, memory, disk, network, php-fpm pool, nginx per Compute Engine VM | Collector agent on each app VM (`hostmetrics`, `nginx`, php-fpm exporter). Template: `hostmetrics` and `nginx` in github.com/SigNoz/dashboards |
| Cloud SQL | Connections, locks, InnoDB, slow queries; CPU and disk | `mysql` receiver with a read-only user; CPU and disk come from GCP Cloud Monitoring. Template: `mysql` in github.com/SigNoz/dashboards |
| Backlogs | Queue depth and oldest job, `failed_jobs`, files not yet synced to Cloud Storage | `sqlquery` receiver against Cloud SQL |
| Integration outcomes | SMS delivered / rejected by provider and reason, payment success, ELM, Urway, Cloud Storage calls | Code: outcome attributes on spans and manual spans for the untraced clients |
| Business KPIs | Claims imported, SMS sent, payment links created and paid, per tenant | Code: business attributes on spans |
| Logs | Warning and error volume, top messages, nginx and php-fpm logs | `OTEL_LOGS_EXPORTER=otlp` and the `filelog` receiver |
| Pipeline health | Spans received per deployment, refused or dropped data, ClickHouse disk | SigNoz collector's own metrics. Alerts on the SigNoz VM itself belong in GCP, not in SigNoz. |

The receivers named above (`httpcheck`, `mysql`, `sqlquery`, `hostmetrics`,
`nginx`, `filelog`, `googlecloudmonitoring`) are all compiled into the SigNoz
collector that already runs on the SigNoz VM, so only host metrics need a second
collector (on each app VM).

### Uptime probes

Request-based availability has a blind spot: a deployment that is completely
down produces no requests, so the percentage has nothing to drop from. Two
probes close it, and they do different jobs:

1. **GCP uptime checks** on `GET /api/health` (see [`signoz/alerts.md`](signoz/alerts.md)).
   They probe the public URL from several regions and keep alerting when the
   SigNoz VM is down.
2. **`httpcheck` in the SigNoz collector**, so the number sits next to the traces.
   On the SigNoz VM, add to `otel-collector-config.yaml`:

   ```yaml
   receivers:
     httpcheck:
       collection_interval: 60s
       targets:
         - endpoint: https://<art-live-api-host>/api/health
           method: GET
         - endpoint: https://<art-live-frontend-host>/
           method: GET
         # one api and one frontend line per deployment

   service:
     pipelines:
       metrics:
         receivers: [otlp, httpcheck]   # add httpcheck to the existing list, keep the rest
   ```

   Then restart the collector, which is the `signoz-ingester-1` container since
   the move to Foundry (ingestion pauses for a few seconds). Foundry regenerates
   its files on `forge`, so make the change in Foundry's `casting.yaml` on the
   SigNoz VM, not only in the generated copy.
   `/api/health` answers 503 when the database is unreachable, so a Cloud SQL
   outage counts as downtime. Uptime % per endpoint is the average of
   `httpcheck.status` where `http.status_class = 2xx`, grouped by `http.url`,
   times 100; response time is `httpcheck.duration`. SigNoz ships an *Uptime
   Monitoring* dashboard template for these metrics (needs SigNoz v0.135 or
   newer; this installation is on v0.142.1 since 2026-09-19). Import it from
   `uptime-monitoring/uptime-monitoring-dashboard.json` in
   github.com/SigNoz/dashboards.

### Known gaps in the integrations view

- A gateway that answers HTTP 200 with an error code in the body (Msegat:
  invalid login, rejected number, duplicate message) shows as healthy. The span
  needs the provider's result code, see *Integration outcomes* above.
- ELM (`SoapClient`), Urway's `curl` call and the Cloud Storage SDK produce no
  spans at all.

## Retention

| Where | What | Kept for |
| --- | --- | --- |
| SigNoz | traces (and logs, once the logs exporter is on) | 30 days (set 2026-09-17 under *Settings → General*; was the 15-day default) |
| VM `storage/logs` | `laravel-YYYY-MM-DD.log` | `LOG_DAILY_DAYS` (recommended 90) |

So: anything up to a month old, use SigNoz. Older than that, only the log files
on the VM remain, and only unhandled errors are in both places.

## Log rotation

Production logs through the `stack` channel, which wraps `single`: one
`storage/logs/laravel.log` that grows forever. Browser error reports add up to
~20 KB per report to it. Pick one of these on each VM.

**Option A: let Laravel rotate (recommended).** Set `LOG_CHANNEL=daily` in the
environment's secret, and `LOG_DAILY_DAYS` to how long to keep them (default 14;
90 is a sensible production value, see the sizing note below). Laravel then
writes `laravel-YYYY-MM-DD.log` and deletes older files itself. No OS config,
no file-handle issues, takes effect on the next deploy. If you prefer to keep
`LOG_CHANNEL=stack`, change the stack's `channels` to `['daily']` in
`config/logging.php` instead.

**Option B: OS logrotate.** Keeps `single` and rotates the file from outside.
php-fpm and the workers hold the file open, so `copytruncate` is required or
they keep writing to the rotated file. Drop this in `/etc/logrotate.d/rcp-backend`:

```
/var/www/html/*/backend/storage/logs/*.log {
    daily
    rotate 14
    size 100M
    missingok
    notifempty
    compress
    delaycompress
    copytruncate
    su www-data www-data
}
```

`size 100M` makes it also rotate mid-day if the file gets large. `copytruncate`
can lose the handful of lines written between the copy and the truncate, which
is acceptable for an application log.

**Sizing.** ART live's `storage/logs` held about 365 MB in September 2026,
accumulated over months and including old backups, so 90 days of daily files
is on the order of 100 to 200 MB. Disk is not the constraint; pick the
retention the tenant's audit expectations need.

**Other files in that directory.** `queue-worker.log`, `laravel-queue.log`,
`schedule.log` and `reverb.log` are stdout captures from supervisor and cron,
not Laravel channels, so `LOG_CHANNEL` does not touch them. Supervisor rotates
its own with `stdout_logfile_maxbytes` / `stdout_logfile_backups` in the
program block; the cron redirect for `schedule.log` needs the logrotate config
from Option B. Old `laravel-YYYY-MM-DD.log` files from 2024 and the hand-made
`laravel-backup-*.log` / `laravel-Copy.log` copies can simply be deleted.

**Either way:** nginx and php-fpm logs are already rotated by their Debian
packages; this is only about the files Laravel and the workers write.

## What is deliberately not done yet

- **Logs are not exported** (`OTEL_LOGS_EXPORTER=none`). `Log::info()` and friends
  go only to `storage/logs/laravel.log`; the Laravel instrumentation emits them
  through the logs signal, which is switched off. Unhandled **exceptions** are
  different: they are attached to the request span as exception events and do
  reach SigNoz. To ship log lines too, set `OTEL_LOGS_EXPORTER=otlp` (the
  instrumentation's log watcher then exports every `Log::` call), or add
  `open-telemetry/opentelemetry-logger-monolog` as a log channel for finer control.
- **No sampling.** Every request is traced. If volume becomes a cost problem set
  `OTEL_TRACES_SAMPLER=parentbased_traceidratio` and
  `OTEL_TRACES_SAMPLER_ARG=0.25`. Sampling is head-based: a sampled-out request
  records nothing at all, including its errors, so keep 100% until volume forces
  the change and then watch the error counts drop proportionally.
- **Frontend traces** (browser SDK, page load timing) are not collected. Errors
  are, through `/api/v1/client-errors`.
