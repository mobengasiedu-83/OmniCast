# Deploying OmniCast to Production

**Application:** SysUp360 OmniCast  
**Production URL:** https://omnicast.sysup360.com  
**Server:** sysup360-01  
**Deployment directory:** `/var/www/OmniCast`  
**Git branch:** `v1-merged`  
**PHP executable:** `/usr/bin/php8.3`  
**Web process user:** `www-data`

This runbook deploys the `v1-merged` branch to `/var/www/OmniCast` on the production host.

It preserves the live `.env`, database records, uploaded files, and existing customer data. It does not use database reset or seeding commands.

## Before You Start

- Connect using an authorized SSH key.
- Confirm the deployment directory is `/var/www/OmniCast`.
- Confirm a recent, recoverable database backup exists.
- Use `/usr/bin/php8.3` for Artisan commands. The server's default `php` may be PHP 7.4.
- Use Node.js 22.18 or newer for frontend builds.
- Run Artisan as `www-data` so generated application files have appropriate permissions.
- Do not share passwords, private keys, `.env` contents, or service credentials.
- Do not run `migrate:fresh`, `migrate:refresh`, `db:wipe`, or production seeders.

## 1. Check and Update the Code

Connect to the server:

```bash
cd /var/www/OmniCast

git status --short --branch
git branch --show-current
```

If the working tree contains tracked changes, stop and review them.

Preserve untracked `.env` backups and other server-side files. Do not discard them.

Fetch the latest release:

```bash
git fetch origin v1-merged

git log -1 --oneline
git log -1 --oneline origin/v1-merged
```

Confirm the remote commit is the intended production release.

If the working tree is safe to update:

```bash
git switch v1-merged
git pull --ff-only origin v1-merged
git log -1 --oneline
```

Do not use `git reset --hard` or `git clean -fd` to force deployment.

## 2. Back Up and Check the Live Environment

Preserve the existing `.env`. Do not replace it with `.env.example`.

Create a protected backup outside the application directory:

```bash
cd /var/www/OmniCast

sudo install -d -m 700 -o root -g root \
  /root/omnicast-backups

sudo cp -p .env \
  "/root/omnicast-backups/env-$(date +%Y%m%d-%H%M%S)"
```

Edit the live configuration:

```bash
sudo nano .env
```

Preserve all existing non-empty values, particularly:

- `APP_KEY`
- Database credentials
- Mail credentials
- Paystack credentials
- SMS provider credentials
- VitalPBX credentials
- Webhook secrets

Ensure these Laravel defaults are not blank:

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

Verify the cache configuration:

```dotenv
CACHE_STORE=database

DB_CACHE_CONNECTION=mysql
DB_CACHE_TABLE=cache
DB_CACHE_LOCK_CONNECTION=mysql
DB_CACHE_LOCK_TABLE=cache_locks
```

These settings are particularly important because an empty cache table name previously caused an HTTP 500 error on the OmniCast dashboard.

Ensure production debugging is disabled:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://omnicast.sysup360.com
```

If notification SMS settings are absent or blank, use safe defaults:

```dotenv
NOTIFICATIONS_SMS_ENABLED=false
NOTIFICATIONS_SMS_SENDER=OMNICAST
NOTIFICATIONS_SMS_MAX_LENGTH=320
```

These defaults leave platform SMS notifications disabled until properly configured.

Do not print `.env` or paste its contents into logs, tickets, or chat.

## 3. Verify PHP and Node.js

Check PHP:

```bash
/usr/bin/php8.3 -v
```

Check Node.js:

```bash
node -v
npm -v
```

**Requirement:** Node.js 22.18 or newer, using a release supported by the project's dependencies.

The server previously used Node.js 20.20.2, which produced dependency engine warnings.

If the server hosts other applications, use an isolated compatible Node.js installation rather than replacing the system-wide version without checking dependencies.

Confirm the correct Node.js executable is available before continuing.

## 4. Fix Wayfinder Filesystem Permissions

**This section addresses the deployment error encountered on the production server.**

The previous Wayfinder generation failed with:

```text
file_put_contents(
  /var/www/OmniCast/resources/js/actions/App/Http/Controllers/SitemapController.ts
): Failed to open stream: Permission denied
```

The error prevented Wayfinder from generating TypeScript modules required by the new Privacy and System Administration pages.

### 4.1 Inspect the Generated Directories

```bash
cd /var/www/OmniCast

ls -ld resources/js/actions
ls -ld resources/js/routes

ls -l \
  resources/js/actions/App/Http/Controllers/SitemapController.ts
```

Confirm that the directories exist.

### 4.2 Correct Ownership

For the current deployment workflow, Wayfinder generation runs as `www-data`.

Set ownership of the generated directories:

```bash
sudo chown -R www-data:www-data \
  /var/www/OmniCast/resources/js/actions \
  /var/www/OmniCast/resources/js/routes
```

Set normal directory permissions:

```bash
sudo find \
  /var/www/OmniCast/resources/js/actions \
  /var/www/OmniCast/resources/js/routes \
  -type d -exec chmod 755 {} \;
```

Set normal file permissions:

```bash
sudo find \
  /var/www/OmniCast/resources/js/actions \
  /var/www/OmniCast/resources/js/routes \
  -type f -exec chmod 644 {} \;
```

These commands affect only the generated Wayfinder directories.

Do not use `chmod -R 777`.

For future deployments, consider using a dedicated deployment account rather than giving the web process ownership of source files.

### 4.3 Generate Wayfinder Files

Run:

```bash
cd /var/www/OmniCast

sudo -u www-data \
  /usr/bin/php8.3 artisan wayfinder:generate
```

The command must complete without permission errors.

### 4.4 Verify the Required Files

The previous frontend build reported missing imports for:

```text
@/actions/App/Http/Controllers/Admin/PrivacyRequestController

@/actions/App/Http/Controllers/Admin/PrivacyRetentionController

@/routes/admin/system
```

Verify the corresponding generated files:

```bash
test -f \
  resources/js/actions/App/Http/Controllers/Admin/PrivacyRequestController.ts \
  && echo "Privacy Request Controller: OK"

test -f \
  resources/js/actions/App/Http/Controllers/Admin/PrivacyRetentionController.ts \
  && echo "Privacy Retention Controller: OK"

test -f \
  resources/js/routes/admin/system/index.ts \
  && echo "System Routes: OK"
```

If any file is missing, check route registration:

```bash
sudo -u www-data \
  /usr/bin/php8.3 artisan route:list --path=admin
```

Verify that routes for the following controllers are registered:

- `PrivacyRequestController`
- `PrivacyRetentionController`
- `SystemController`

These routes were present in the earlier production output.

If the generated files are still missing, inspect the actual Wayfinder output and import paths.

Do not create empty placeholder files.

### 4.5 Review Generated File Changes

Wayfinder generation may modify tracked files.

Run:

```bash
git status --short
```

Review any generated changes before the next deployment or Git update.

## 5. Install Frontend Dependencies and Build Assets

The Wayfinder Vite plugin invokes Artisan during frontend builds.

Ensure the build uses PHP 8.3.

Create a temporary PHP executable override:

```bash
cd /var/www/OmniCast

mkdir -p /tmp/omnicast-bin

ln -sf /usr/bin/php8.3 \
  /tmp/omnicast-bin/php
```

Confirm the correct Node.js runtime is selected.

If using an isolated Node.js installation at `/opt/node22`, configure the path:

```bash
export PATH="/opt/node22/bin:/tmp/omnicast-bin:$PATH"
```

Otherwise, use the actual location of your compatible Node.js installation.

Verify:

```bash
node -v
php -v
```

Install frontend dependencies:

```bash
npm ci
```

Build production assets:

```bash
npm run build
```

The build must finish successfully.

Verify the production manifest exists:

```bash
test -f public/build/manifest.json \
  && echo "Production build manifest: OK"
```

**If the frontend build fails, stop the deployment.**

Do not continue to database migrations or worker restarts until the build error is resolved.

## 6. Apply Migrations and Refresh Laravel

Confirm that a recent database backup exists before applying migrations.

Run only normal forward migrations.

```bash
cd /var/www/OmniCast

sudo -u www-data \
  /usr/bin/php8.3 artisan migrate:status
```

Review pending migrations.

Then run:

```bash
sudo -u www-data \
  /usr/bin/php8.3 artisan migrate --force
```

Do not run:

```bash
php artisan migrate:fresh
php artisan migrate:refresh
php artisan db:wipe
php artisan db:seed
```

The previously deployed privacy compliance migration completed successfully and should not be rerun if Laravel records it as applied.

Optimize Laravel:

```bash
sudo -u www-data \
  /usr/bin/php8.3 artisan optimize
```

Verify the application environment:

```bash
sudo -u www-data \
  /usr/bin/php8.3 artisan about --only=environment
```

Confirm:

```text
Environment: production
Debug Mode: DISABLED
```

## 7. Restart Horizon

Gracefully terminate the running Horizon master:

```bash
cd /var/www/OmniCast

sudo -u www-data \
  /usr/bin/php8.3 artisan horizon:terminate
```

Supervisor should automatically restart it.

Check:

```bash
sudo supervisorctl status omnicast-horizon
```

Then verify Horizon itself:

```bash
sudo -u www-data \
  /usr/bin/php8.3 artisan horizon:status
```

Expected: Horizon reports that it is running.

Do not start a second Horizon master manually.

## 8. Verify Redis and Queue Configuration

Check Redis:

```bash
redis-cli ping
```

Expected:

```text
PONG
```

Inspect Laravel's effective queue configuration:

```bash
sudo -u www-data \
  /usr/bin/php8.3 artisan config:show queue
```

Verify that:

- The queue connection is Redis.
- The Redis queue name is not empty.
- The Redis retry interval is appropriate for your longest job.
- Failed-job storage is correctly configured.

Your earlier production configuration used a Redis `retry_after` value of 960 seconds.

Verify that Horizon worker timeouts and job-level timeouts remain compatible with that value.

Do not share unredacted configuration output if it contains credentials.

## 9. Verify the Scheduler

Check the registered scheduled tasks:

```bash
sudo -u www-data \
  /usr/bin/php8.3 artisan schedule:list
```

Check existing scheduler configuration:

```bash
sudo crontab -l

sudo crontab -u www-data -l

sudo systemctl list-timers --all
```

If the Laravel scheduler is not already configured, add a single scheduler entry for `www-data`:

```bash
sudo crontab -u www-data -e
```

Add:

```cron
* * * * * cd /var/www/OmniCast && /usr/bin/php8.3 artisan schedule:run >> /dev/null 2>&1
```

Do not create duplicate scheduler entries.

## 10. Verify the Production Release

Check the public endpoints:

```bash
curl -sS -o /dev/null \
  -w 'Homepage: %{http_code}\n' \
  https://omnicast.sysup360.com/

curl -sS -o /dev/null \
  -w 'Sitemap: %{http_code}\n' \
  https://omnicast.sysup360.com/sitemap.xml

curl -fsS \
  https://omnicast.sysup360.com/robots.txt
```

Expect HTTP 200 for the homepage and sitemap.

`robots.txt` should advertise:

```text
Sitemap: https://omnicast.sysup360.com/sitemap.xml
```

Also verify the authenticated application pages:

- Dashboard
- Notifications
- Admin System
- Admin Queues
- Privacy Requests
- Privacy Retention
- Legal DPA Management

Check that all pages load without HTTP 500 errors.

## 11. Troubleshooting HTTP 500 Errors

If a page returns HTTP 500, inspect the latest Laravel log.

```bash
sudo -u www-data sh -c '
cd /var/www/OmniCast
log=$(ls -t storage/logs/laravel*.log 2>/dev/null | head -n 1)
echo "Latest log: $log"
if [ -n "$log" ]; then
    grep -n -A 30 -B 3 "production.ERROR" "$log" | tail -n 70
fi
'
```

Review and redact any sensitive information before sharing logs.

For the previously encountered database cache error, verify:

```bash
sudo -u www-data \
  /usr/bin/php8.3 artisan config:show cache
```

Ensure the database cache table and lock table are configured correctly.

For frontend errors, verify:

```bash
test -f public/build/manifest.json

sudo -u www-data \
  /usr/bin/php8.3 artisan wayfinder:generate
```

Do not attempt to resolve configuration errors by recreating or clearing the production database.

## 12. Final Deployment Checklist

- [ ] Correct Git branch and release commit
- [ ] Live `.env` preserved and backed up
- [ ] Database backup verified
- [ ] PHP 8.3 available
- [ ] Node.js 22.18 or newer available
- [ ] Wayfinder directory permissions corrected
- [ ] Wayfinder generation successful
- [ ] Privacy Request controller generated
- [ ] Privacy Retention controller generated
- [ ] System routes generated
- [ ] `npm ci` successful
- [ ] `npm run build` successful
- [ ] Production asset manifest exists
- [ ] Database migrations successful
- [ ] Laravel optimization successful
- [ ] Production debugging disabled
- [ ] Redis responding
- [ ] Horizon running
- [ ] Scheduler configured
- [ ] Homepage and sitemap return HTTP 200
- [ ] Dashboard and admin pages load
- [ ] No unresolved critical errors in logs

## 13. Important Deployment Rules

1. Never replace the production `.env` with `.env.example`.
2. Never reset or wipe the production database.
3. Never run production seeders without an approved, verified reason.
4. Never force Git changes over unreviewed server modifications.
5. Never use `chmod -R 777` to fix permission problems.
6. Never proceed after a failed frontend build.
7. Always use PHP 8.3 for OmniCast Artisan commands.
8. Always verify the Node.js version before building.
9. Always regenerate Wayfinder after updating routes or controllers when the generated files are required.
10. Always verify Horizon and the scheduler after deployment.

---

**Related documentation:** `RUNNING-THE-SERVICES.md`

**Deployment completion criteria:** The release is complete only when the frontend build succeeds, database migrations are applied, Laravel is healthy, Horizon is running, scheduled tasks are configured, and the public and authenticated application pages have been verified.