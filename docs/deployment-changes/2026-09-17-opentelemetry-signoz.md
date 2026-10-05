# Deployment Changes Log

## Change: Enable OpenTelemetry tracing to SigNoz on all deployments

**Date:** 2026-09-17
**Environment:** All (ART live/uat/demo, GIG live/uat, RCP dev/uat)
**Type:** Configuration (Secret Manager) + code

### Description

The OpenTelemetry packages added in April 2026 never sent data because the SDK
reads its `OTEL_*` configuration before Laravel loads `.env`. This release adds
`bootstrap/otel.php`, which exports those values first, plus request context
(user, tenant, trace id header), a browser error intake endpoint
(`POST /api/v1/client-errors`) and a liveness endpoint (`GET /api/health`).
Full runbook: `docs/observability/README.md`.

### Action Required by DevOps

1. **Update each environment's secret** (`RCP-*-BACKEND-*` in Secret Manager)
   with the block below, using the service name and attributes from the table in
   `docs/observability/README.md`. ART live already has most of it; add the
   missing `OTEL_PHP_TRACE_CLI_ENABLED`, `OTEL_PHP_EXCLUDED_URLS` and the
   `deployment.environment` / `tenant` resource attributes.

   ```ini
   OTEL_PHP_AUTOLOAD_ENABLED=true
   OTEL_PHP_TRACE_CLI_ENABLED=true
   OTEL_SERVICE_NAME=<see table>
   OTEL_RESOURCE_ATTRIBUTES=deployment.environment=<env>,tenant=<tenant>,host.name=<vm>
   OTEL_EXPORTER_OTLP_ENDPOINT=http://<signoz-internal-ip>:4318
   OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
   OTEL_TRACES_EXPORTER=otlp
   OTEL_METRICS_EXPORTER=none
   OTEL_LOGS_EXPORTER=none
   OTEL_PHP_EXCLUDED_URLS=api/health
   ```

2. **Confirm the `opentelemetry` PHP extension is loaded for PHP 8.4 on every VM**
   (`php8.4 -m | grep opentelemetry`). ART live has it; check the others.

3. **Deploy.** The workflow restarts the supervisor workers, which is required for
   queue tracing to start.

4. **Verify** per the runbook: the service appears under *Services* in SigNoz.

5. **Network:** change the endpoint to the SigNoz VM's internal IP and restrict
   port 4318 to the VPC. The receiver is currently reachable from the internet.

6. **SigNoz:** import `docs/observability/signoz/rcp-api-overview.json` as a
   dashboard and create the alert rules in `docs/observability/signoz/alerts.md`.

7. **GCP uptime checks** on `GET /api/health` for each deployment.

8. **ART live cleanup (optional):** remove the `env[OTEL_*]` lines added to
   `/etc/php/8.4/fpm/pool.d/www.conf` on 2026-09-17 so the secret is the single
   source of truth, then `systemctl reload php8.4-fpm`.

### Scope of Impact

- No schema changes. No downtime.
- Two new public routes: `GET /api/health`, `POST /api/v1/client-errors`
  (60 requests/minute per IP).
- Each accepted browser report writes up to ~20 KB (stack trace) to
  `storage/logs/laravel.log`. Production uses the `single` channel, which never
  rotates. Confirm logrotate covers that file on each VM, or switch
  `LOG_CHANNEL` to `daily` (see "Log rotation" in `docs/observability/README.md`).
- A new `X-Trace-Id` response header on API responses.
- Tracing adds a small per-request cost; export happens after the response is
  sent. If the collector is unreachable the exporter gives up after its timeout
  without affecting the response.

### Rollback

Set `OTEL_PHP_AUTOLOAD_ENABLED=false` in the secret and redeploy (or reload
php-fpm and restart workers). Everything else is inert without it.
