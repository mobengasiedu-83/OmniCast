# OmniCast Operations Runbook

This runbook covers first-line production checks for application readiness,
queue workers, the scheduler, and failed jobs. Run commands from the deployed
application root as the application service account, using the PHP 8.3 binary
configured for the host.

## Readiness and system checks

- `GET /up` is Laravel's lightweight liveness check.
- `GET /api/v1/health` confirms that the public API is responding.
- `GET /api/v1/health/ready` checks the database, cache, private storage, queue
  backend, and (for Redis queues) Horizon. A failed dependency returns HTTP
  `503`. The public response includes only check names and states; detailed
  diagnostics are restricted to platform operators at `/admin/system`.
- `/admin/system` refreshes its service checks every 30 seconds. The
  `/admin/queues` page shows queue depths, failed-job counts, worker topology,
  throughput ceilings, and links to the Horizon dashboard.

## Triage a delivery backlog

1. Open `/admin/system`. Note failed or unavailable checks and the timestamp.
2. Open `/admin/queues` and identify which queue is accumulating jobs. Queue
   depth is not a delivery confirmation; inspect campaign delivery reports and
   provider receipts before declaring messages delivered.
3. Use Horizon to inspect the matching supervisor, worker state, recent
   exceptions, and failed jobs.
4. Check `storage/logs/laravel.log`, `storage/logs/horizon.log`, and the
   provider-specific logs for the first failure. Fix the dependency or
   configuration issue before retrying jobs.
5. Retry only jobs whose side effects are safe to repeat and whose original
   failure cause has been resolved. Avoid `queue:retry all` for an unexplained
   incident.

## Verify workers and scheduler

```bash
php artisan horizon:status
php artisan horizon:supervisors
php artisan schedule:list
php artisan queue:monitor redis:campaigns,redis:sms,redis:voice,redis:email,redis:default
```

In production, Horizon should be owned by the process supervisor and the
scheduler should be invoked once per minute by cron. Do not start another
Horizon master or a second scheduler loop on the production host. Follow
[Running the services](./RUNNING-THE-SERVICES.md) for this installation's
process ownership and deployment-specific commands.

## Inspect and recover failed work

```bash
php artisan queue:failed
php artisan queue:retry <job-id>
php artisan campaigns:sweep --limit=10
```

Inspect the failed job and logs first. The campaign sweep only re-queues
campaigns that stopped making progress; it does not repair provider failures.
Use `queue:retry` only after correcting the cause and checking whether the
operation already reached its provider.

## Maintenance and escalation

- Use the deployment procedure in [Deploying to production](./DEPLOYING-TO-PRODUCTION.md)
  for releases, migrations, and rollback decisions.
- Do not enable maintenance mode as a troubleshooting shortcut; it interrupts
  customer traffic and can hide a still-running queue incident.
- Escalate unresolved database, Redis, SMTP, SMS, voice, or payment-provider
  outages to the service owner with the check name, time window, queue name,
  and relevant redacted exception/reference. Never include credentials,
  message bodies, or unmasked recipient data in an incident report.
