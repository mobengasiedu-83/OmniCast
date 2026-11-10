# Deploying to production

This runbook deploys the `v1-merged` branch to `/var/www/OmniCast` on the
production host. It preserves the live `.env` and database records. It does not
use database reset or seeding commands.

## Before you start

- Connect with an SSH key authorized for your server account. Do not put
  passwords, private keys, or `.env` values in shell commands or chat.
- Confirm the target is `/var/www/OmniCast` and that you have a recent database
  backup from the normal production backup process.
- Use `/usr/bin/php8.3` for Artisan commands. The server's default `php` may be
  an older version.
- Run Artisan as `www-data`, not root, so generated cache files remain readable
  by the web process.

## 1. Check and update the code

Connect to the host, then run:

```bash
cd /var/www/OmniCast
git status --short --branch
```

If the worktree has changes, stop and review them. Do not discard server-side
changes or backup files. If it is clean, update the branch using fast-forward
only:

```bash
git fetch origin v1-merged
git switch v1-merged
git pull --ff-only origin v1-merged
git log -1 --oneline
```

Confirm the branch and commit are the expected release before continuing.

## 2. Back up and check the live environment

Keep the existing `.env`; do not replace it with `.env.example`. Back it up
before editing:

```bash
cd /var/www/OmniCast
cp -p .env ".env.backup.$(date +%Y%m%d-%H%M%S)"
nano .env
```

Preserve all existing non-empty values, especially `APP_KEY`, database
credentials, mail settings, and service credentials. Ensure these Laravel
defaults are not blank:

```dotenv
AUTH_GUARD=web
AUTH_MODEL=App\Models\User
AUTH_PASSWORD_BROKER=users
AUTH_PASSWORD_RESET_TOKEN_TABLE=password_reset_tokens
AUTH_PASSWORD_TIMEOUT=10800
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
SESSION_TABLE=sessions
DB_CACHE_TABLE=cache
DB_CACHE_LOCK_TABLE=cache_locks
```

These settings must not be empty: an explicitly blank value overrides Laravel's
configuration fallback and can cause HTTP 500 errors, including an undefined
auth guard or invalid database table/charset errors.

If the notification SMS settings are absent or blank, use safe defaults. This
leaves platform SMS delivery disabled:

```dotenv
NOTIFICATIONS_SMS_ENABLED=false
NOTIFICATIONS_SMS_SENDER=OMNICAST
NOTIFICATIONS_SMS_MAX_LENGTH=320
```

Do not print `.env` or paste its contents into logs, tickets, or chat.

## 3. Install frontend dependencies and build assets

The Wayfinder Vite plugin runs Artisan during the build, so ensure `php` on the
build `PATH` resolves to PHP 8.3. The following temporary symlink does not alter
the system PHP:

```bash
mkdir -p /tmp/omnicast-bin
ln -sf /usr/bin/php8.3 /tmp/omnicast-bin/php
PATH="/tmp/omnicast-bin:$PATH" npm ci
PATH="/tmp/omnicast-bin:$PATH" npm run build
```

Use Node.js 22.18 or newer for the build dependencies. If the build fails, stop
and resolve the build error before applying migrations.

## 4. Apply migrations and refresh Laravel

Run only the normal forward migration command. The release migrations are
additive; do not run `migrate:fresh`, `db:wipe`, or seeders.

```bash
sudo -u www-data sh -c 'cd /var/www/OmniCast && /usr/bin/php8.3 artisan migrate --force'
sudo -u www-data sh -c 'cd /var/www/OmniCast && /usr/bin/php8.3 artisan optimize'
sudo -u www-data sh -c 'cd /var/www/OmniCast && /usr/bin/php8.3 artisan horizon:terminate'
sudo supervisorctl status omnicast-horizon
```

Confirm the migration output reports success and Supervisor reports Horizon
running. `horizon:terminate` signals Supervisor-managed workers to restart on
the new code; do not start a second Horizon master by hand.

## 5. Verify the release

Check the public endpoints:

```bash
curl -sS -o /dev/null -w 'Homepage: %{http_code}\n' https://omnicast.sysup360.com/
curl -sS -o /dev/null -w 'Sitemap: %{http_code}\n' https://omnicast.sysup360.com/sitemap.xml
curl -fsS https://omnicast.sysup360.com/robots.txt
```

Expect HTTP 200 for the homepage and sitemap. `robots.txt` should advertise
`https://omnicast.sysup360.com/sitemap.xml`. Also check the dashboard and the
notification pages in a browser.

If an endpoint returns HTTP 500, inspect the latest Laravel log without
printing `.env` values:

```bash
sudo -u www-data sh -c '
cd /var/www/OmniCast
log=$(ls -t storage/logs/laravel*.log 2>/dev/null | head -n 1)
echo "Latest log: $log"
grep -n -A 30 -B 3 "production.ERROR" "$log" | tail -n 70
'
```

Resolve the reported exception and repeat verification. Do not attempt to fix a
configuration error by clearing or recreating the database.

For Horizon, scheduler, and queue operations, see
[Running the services](./RUNNING-THE-SERVICES.md).
