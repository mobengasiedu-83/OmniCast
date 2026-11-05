# OmniCast — Running the services

What the **Running the engine** panel on `/admin/queues` expects to be running, what each of those
commands actually is, and the exact commands for this deployment. Two of the four are long-running
daemons, two are one-off maintenance commands that are already on the scheduler.

- Document owner: platform team
- Applies to: the production host `62.129.149.197` (`/var/www/OmniCast`) and local ServBay
  development
- Last verified: 2026-11-05 on `206f1c9` (Laravel 13.39.0, Horizon 5.50.1, PHP 8.3, Redis queue)

## The four commands at a glance

| Panel command                                | Kind                                          | Production status                                                                  |
| -------------------------------------------- | --------------------------------------------- | ---------------------------------------------------------------------------------- |
| `php artisan horizon`                        | **Daemon** — master supervisor over 5 queues  | ✅ supervised by Supervisor (`omnicast-horizon`, autostart + autorestart)          |
| `php artisan schedule:work`                  | **Daemon** — scheduler loop, cron alternative | ✅ not used — cron runs `schedule:run` every minute instead (use one or the other) |
| `php artisan campaigns:sweep --limit=10`     | One-off command (stalled-campaign rescue)     | ✅ scheduled every five minutes (`*/5 * * * *`)                                    |
| `php artisan queue:prune-failed --hours=168` | One-off command (failed-job retention)        | ✅ scheduled daily at 00:00                                                        |

Nothing needs to be started on the server: the panel is documentation, not a control surface.

## Production topology

### Horizon (queue workers)

Horizon runs as a Supervisor program, so it starts on boot and restarts after a crash:

```ini
# /etc/supervisor/conf.d/omnicast-horizon.conf
[program:omnicast-horizon]
process_name=%(program_name)s
command=/usr/bin/php8.3 /var/www/OmniCast/artisan horizon
directory=/var/www/OmniCast
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/OmniCast/storage/logs/horizon.log
stopwaitsecs=3600
environment=HOME="/var/www",USER="www-data"
```

`horizon:supervisors` then shows the five supervisors the `/admin/queues` page lists:

| Supervisor             | Queue       | Max processes (production) |
| ---------------------- | ----------- | -------------------------- |
| `supervisor-campaigns` | `campaigns` | 10                         |
| `supervisor-sms`       | `sms`       | 16                         |
| `supervisor-voice`     | `voice`     | 8                          |
| `supervisor-email`     | `email`     | 6                          |
| `supervisor-default`   | `default`   | 4                          |

The counts come from `config/horizon.php` → `environments.production`; local development uses
2/2/1/1/1 (`environments.local`). Workers auto-balance between their `maxProcesses` bounds.

### Scheduler

One cron entry is the heartbeat of the delivery engine:

```cron
# /etc/cron.d/omnicast
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * www-data cd /var/www/OmniCast && /usr/bin/php8.3 artisan schedule:run >> /var/www/OmniCast/storage/logs/schedule.log 2>&1
```

`php8.3 artisan schedule:list` reports what that entry runs (registered in `routes/console.php`):

| Expression    | Command                          | Purpose                                         |
| ------------- | -------------------------------- | ----------------------------------------------- |
| `* * * * *`   | `campaigns:dispatch-scheduled`   | Release campaigns whose start time has arrived  |
| `*/5 * * * *` | `campaigns:sweep`                | Re-queue campaigns that stopped making progress |
| `*/5 * * * *` | `horizon:snapshot`               | Record the metrics the Horizon dashboard charts |
| `0 0 * * *`   | `queue:prune-failed --hours=168` | Drop failed jobs past their retention window    |
| `0 0 * * *`   | `queue:prune-batches --hours=48` | Prune completed job batches                     |
| `0 * * * *`   | `reports:aggregate`              | Rebuild the daily delivery rollups              |
| `0 6 * * *`   | `reports:send-scheduled`         | Mail the scheduled period that just closed      |
| `0 0 * * *`   | `reports:prune-exports`          | Prune expired report exports                    |

`QUEUE_FAILED_PRUNE_HOURS` in `.env` feeds the `--hours` value of the failed-job prune.

## Two rules on the production box

1. **Use `php8.3`, never `php`.** The default `php` on this server is **7.4**, and any artisan or
   composer call aborts with _"Your Composer dependencies require a PHP version >= 8.3.0"_. The
   absolute path `/usr/bin/php8.3` is the safe form inside cron, Supervisor and scripts.
2. **Run artisan as `www-data`, never as root.** Root writes `storage/` and `bootstrap/cache/` files
   that the web server can no longer read or rewrite, which surfaces as 500s until the ownership is
   repaired. Change into the project first — artisan needs the application root as its cwd.

The `php` 7.4 default also breaks `npm run build`: the Wayfinder Vite plugin shells out to
`php artisan wayfinder:generate`, so the build needs a `php` that resolves to 8.3 on `PATH`
(`ln -s /usr/bin/php8.3 /tmp/omnicast-bin/php`, then `PATH=/tmp/omnicast-bin:$PATH npm run build`).

## Command reference (production)

### Queue workers (Horizon)

```bash
sudo supervisorctl status omnicast-horizon
sudo supervisorctl restart omnicast-horizon          # after code, .env or config/ changes

sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan horizon:status'
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan horizon:list'
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan horizon:supervisors'
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan horizon:pause'       # horizon:continue to resume
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan horizon:terminate'   # Supervisor starts it again

tail -f /var/www/OmniCast/storage/logs/horizon.log
```

### Scheduler

```bash
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan schedule:list'
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan schedule:run'   # fire the due tasks once
tail -f /var/www/OmniCast/storage/logs/schedule.log
```

### One-off maintenance

```bash
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan campaigns:sweep --limit=10'
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan queue:prune-failed --hours=168'
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan queue:failed'   # list; queue:retry all to replay
```

### Queue depth

The `queue:monitor` argument is `connection:queue`, not a bare queue name:

```bash
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan queue:monitor redis:default,redis:sms,redis:voice,redis:email,redis:campaigns'
```

A healthy idle installation answers `[0] OK` per queue with 0 pending, 0 delayed and 0 reserved. The
same numbers, with the failed count and the ceilings in force, are on `/admin/queues`.

## What never to run by hand in production

- **`php artisan horizon`** — a second master supervisor duplicates workers and both fight over the
  pause flag. Restart the Supervisor program instead.
- **`php artisan schedule:work`** — it would run every scheduled task a second time alongside the
  cron entry. Use `schedule:run` (cron) _or_ `schedule:work`, never both.
- **`php artisan queue:work`** — only for installations on the `database` queue; with
  `QUEUE_CONNECTION=redis` the Horizon supervisors own those queues.

## Local development (ServBay)

One command starts everything the panel asks for:

```bash
composer run dev        # == php artisan dev
```

`php artisan dev:list` shows the bundle:

```text
horizon         php artisan horizon
schedule:work   php artisan schedule:work
server          php artisan serve
logs            php artisan pail --timeout=0
vite            npm run dev
```

If you prefer separate terminals: `php artisan horizon` in one, `php artisan schedule:work` in
another, `npm run dev` in a third. Local needs `QUEUE_CONNECTION=redis` with Redis running
(`redis-cli ping` → `PONG`).

## When a queue stops draining

1. `sudo supervisorctl status` — is `omnicast-horizon` `RUNNING`?
2. `tail -50 storage/logs/horizon.log`, then `storage/logs/laravel.log`.
3. `redis-cli ping` — must answer `PONG` (`QUEUE_CONNECTION=redis` in `.env`).
4. `php8.3 artisan horizon:status` — a `PAUSED` master needs `horizon:continue`.
5. `sudo supervisorctl restart omnicast-horizon`.
6. Failed jobs: `/admin/queues`, or `php8.3 artisan queue:failed` / `queue:retry all`.

## Throughput ceilings

Every channel reads **Untrottled** until a ceiling is set. `DeliveryPacer` takes the client's
`rate_limits.<channel>_cps` first, then the platform setting, then `SMS_BROADCAST_GLOBAL_CPS` /
`VOICE_BROADCAST_GLOBAL_CPS` / `EMAIL_BROADCAST_GLOBAL_CPS`; `0` (or unset) means unthrottled. After
changing them in `.env`:

```bash
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan config:cache'
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan horizon:terminate'
```

## After a deploy

A release changes code that the long-lived workers have already loaded, so every deploy ends with:

```bash
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan optimize'           # config, events, routes, views
sudo -u www-data sh -c 'cd /var/www/OmniCast && php8.3 artisan horizon:terminate'  # workers reload the new code
sudo supervisorctl status omnicast-horizon
```

The cron entry needs no restart — `schedule:run` boots a fresh process every minute.
