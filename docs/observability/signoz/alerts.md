# SigNoz alert rules

Create these under **Alerts → New alert → Trace-based alert**. Each rule uses
the query builder with *Data source: Traces*. Send all of them to one
notification channel (Slack or email) configured under *Settings → Alert
channels*. Values are starting points; tune after two weeks of data.

| # | Alert | Query | Condition | Why |
| --- | --- | --- | --- | --- |
| 1 | High error rate | A: `count()` where `kind_string = 'Server' AND has_error = true`, group by `service.name`. B: `count()` where `kind_string = 'Server'`, group by `service.name`. Formula F1: `(A/B)*100` | F1 above **5** for **5 minutes** | A feature is broken for many users. |
| 2 | Slow API | `p95(duration_nano)` where `kind_string = 'Server'`, group by `service.name` | above **2000000000** (2 s) for **10 minutes** | The app feels slow for everyone. |
| 3 | Deployment silent | `count()` where `kind_string = 'Server'`, group by `service.name` | below **1** for **15 minutes**, and enable *Send a notification if data is missing* | php-fpm down, nginx broken, or tracing switched off. Pair with the GCP uptime check on `/api/health`. |
| 4 | Queue job failures | `count()` where `kind_string = 'Consumer' AND name LIKE 'process %' AND has_error = true`, group by `service.name` | above **0** for **5 minutes** | Exports, SMS and payment follow-ups run as jobs; a failing job is invisible to users. |
| 5 | Scheduled command failures | `count()` where `name LIKE 'Command app:%' AND has_error = true`, group by `service.name`, `name` | above **0** for **5 minutes** | Escalations, reminders and IVR checks run from the scheduler. |
| 6 | Browser error spike | `count()` where `frontend.error = true`, group by `service.name` | above **20** in **5 minutes** | A frontend release broke a page. |

## Outside SigNoz: uptime checks

In GCP **Monitoring → Uptime checks**, create one HTTPS check per deployment on
`GET https://<api-host>/api/health` with a 1 minute interval, expecting status
200 and the response containing `"status":"ok"`. Attach an alerting policy to
the same notification channel. This catches the case where the VM itself is
unreachable, which no SigNoz rule can see.
