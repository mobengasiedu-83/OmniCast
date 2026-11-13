# SysUp360 OmniCast — Production Deployment and Update Runbook

**Server:** `sysup360-01`  
**Application:** `https://omnicast.sysup360.com`  
**Directory:** `/var/www/OmniCast`  
**Branch:** `v1-merged`  
**PHP:** `/usr/bin/php8.3`  
**Node:** 22.18+ (22.23.5 successfully used on 12 November 2026)  
**Web/Artisan user:** `www-data`

> **Current verified status (12 November 2026):** The `updateDetails` Wayfinder export was regenerated, `npm run build` succeeded, `public/build/manifest.json` exists, and the homepage returned HTTP 200. This confirms the web release is serving; it does **not** establish that scheduler jobs or end-to-end messaging and billing are healthy.

## 1. Safety and pre-deployment checks

Deploy with an authorized SSH account. Schedule a maintenance window for schema changes or potentially incompatible releases. Preserve `.env`, customer data, uploads, wallet balances, invoices, and campaign records. Never use `migrate:fresh`, `migrate:refresh`, `db:wipe`, `git reset --hard`, `git clean -fd`, or unapproved seeders on production.

```bash
cd /var/www/OmniCast
pwd
git status --short --branch
git branch --show-current
git fetch origin v1-merged
git log -1 --oneline
git log -1 --oneline origin/v1-merged
/usr/bin/php8.3 -v
node -v
npm -v
```

Review any tracked changes before updating. Untracked `.env` backups must not be deleted indiscriminately. Confirm disk space, recent database backups, and a rollback owner before continuing.

## 2. Protect the production environment and data

```bash
sudo install -d -m 700 -o root -g root /root/omnicast-backups
sudo cp -p /var/www/OmniCast/.env \
  "/root/omnicast-backups/env-$(date +%Y%m%d-%H%M%S)"
```

Verify a **restorable** database backup and an upload/media backup exist. Take a fresh consistent database backup using the approved database-specific method and securely store it outside the web root; verify its integrity. Do not paste database passwords into shell history or chat. Record the current Git commit and keep a copy of the current build artifacts for rollback if possible.

```bash
cd /var/www/OmniCast
git rev-parse HEAD
sudo -u www-data /usr/bin/php8.3 artisan migrate:status
```

## 3. Production `.env` review

Edit with `sudo nano /var/www/OmniCast/.env`. Preserve `APP_KEY`, database, Paystack, SMS, voice/PBX, email, webhook, and other provider secrets. Do **not** replace with `.env.example` or generate a new application key.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://omnicast.sysup360.com
VITE_APP_BASE_PATH=
```

The production site runs at the domain root; the ServBay local prefix `/gwosevo-omnicast/public` must **not** be used for production. Verify the frontend base-path helper handles an empty root prefix before building. If environment changes affect Vite, rebuild assets after editing.

Only where supported by the installed application's configuration, verify the following existing values; do not blindly add overrides that conflict with code defaults:

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
CACHE_STORE=database
DB_CACHE_CONNECTION=mysql
DB_CACHE_LOCK_CONNECTION=mysql
NOTIFICATIONS_SMS_ENABLED=false
NOTIFICATIONS_SMS_SENDER=OMNICAST
NOTIFICATIONS_SMS_MAX_LENGTH=320
```

Check that cache and lock table names are not empty. `CACHE_STORE=database` is the attached runbook's current setting, not a universal requirement; retain the configured driver unless changing it intentionally. SMS notification defaults above disable platform notifications until configured; they do not necessarily disable campaign SMS sending.

### WhatsApp sending and template approval

Keep WhatsApp sending off until the provider contract and sandbox checks are complete. The Pro Plus integration reads shared credentials and endpoint paths from the server environment; do not store provider credentials in MySQL or paste them into deployment notes.

```dotenv
WHATSAPP_ENABLED=false
WHATSAPP_API_BASE_URL=
WHATSAPP_API_CLIENT_ID=
WHATSAPP_API_KEY=
WHATSAPP_EVENTS_CONTRACT_ENABLED=false
```

Configure endpoint path variables only when the provider contract confirms their values. A successful connection check does not establish that sending, delivery callbacks, billing evidence, or template submission are supported.

Clients submit WhatsApp template copy against their own approved sender assignments. Client users see only their own submissions and provider-approved templates available through their approved senders. OmniCast Compliance reviewers approve or reject submitted copy. An internally approved template still cannot be used in a campaign until it is submitted through the provider, provider approval is confirmed by **Sync templates**, and the synced name, language, category, and body exactly match the reviewed submission. The current Pro Plus adapter does not create provider templates; provider-side submission is an external operational step. Do not enable campaign sending to test template setup.

## 4. Update release code and PHP dependencies

Only after confirming a safe working tree and backups:

```bash
cd /var/www/OmniCast
git switch v1-merged
git pull --ff-only origin v1-merged
/usr/bin/php8.3 /usr/local/bin/composer --version
```

If Composer is installed elsewhere, use `command -v composer` to locate it. Install PHP dependencies using the **actual Composer executable** and PHP 8.3; do not assume the path above exists:

```bash
cd /var/www/OmniCast
COMPOSER_BIN="$(command -v composer)"
test -n "$COMPOSER_BIN" || { echo 'Composer not found'; exit 1; }
/usr/bin/php8.3 "$COMPOSER_BIN" install \
  --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

If Composer scripts call `php` directly, make sure the build shell selects PHP 8.3 before running Composer (see next section). Review lockfile changes, platform requirements, and release notes. Stop if dependency installation fails.

## 5. PHP/Node runtime and build shell

Node.js 22.23.5 was installed successfully via NodeSource on this server. **Do not reinstall Node on every release.** Check versions and only upgrade during a controlled maintenance window, especially if other applications share the system Node binary.

```bash
node -v
npm -v
/usr/bin/php8.3 -v
```

The Vite/Wayfinder build may invoke `php` without an absolute path. Provide a deployment-local PHP 8.3 shim:

```bash
sudo install -d -m 755 /opt/omnicast-deploy-bin
sudo ln -sfn /usr/bin/php8.3 /opt/omnicast-deploy-bin/php
export PATH="/opt/omnicast-deploy-bin:$PATH"
php -v
node -v
```

The shim directory is administrator-managed and must not be writable by the web process. If your environment has a dedicated compatible Node binary, prepend its **verified** directory to PATH; do not assume `/opt/node22/bin` exists.

## 6. Wayfinder: permissions, route cache, and generated imports

The attached runbook documents a past permission error writing `SitemapController.ts`; an earlier release also omitted the `PackageController.updateDetails` export until route cache was cleared. Use one consistent deployment user for generated files. **Avoid recursively giving `www-data` ownership of the entire source tree.** If the existing deployment process requires `www-data` to generate Wayfinder, limit any ownership repair to the generated folders, after reviewing their Git ownership and deployment-account implications.

```bash
cd /var/www/OmniCast
ls -ld resources/js/actions resources/js/routes
sudo -u www-data /usr/bin/php8.3 artisan route:clear
sudo -u www-data /usr/bin/php8.3 artisan wayfinder:generate
```

If permission denied occurs, inspect the specific directory and file before modifying ownership:

```bash
namei -l resources/js/actions/App/Http/Controllers/SitemapController.ts
ls -ld resources/js/actions resources/js/routes
```

Where the **existing approved workflow** explicitly assigns generated directories to `www-data`, repair only these directories:

```bash
sudo chown -R www-data:www-data resources/js/actions resources/js/routes
sudo find resources/js/actions resources/js/routes -type d -exec chmod 755 {} \;
sudo find resources/js/actions resources/js/routes -type f -exec chmod 644 {} \;
```

Then regenerate and verify expected exports/files:

```bash
sudo -u www-data /usr/bin/php8.3 artisan wayfinder:generate
grep -n 'export const updateDetails' \
  resources/js/actions/App/Http/Controllers/Admin/PackageController.ts
test -f resources/js/actions/App/Http/Controllers/Admin/PrivacyRequestController.ts
test -f resources/js/actions/App/Http/Controllers/Admin/PrivacyRetentionController.ts
test -f resources/js/routes/admin/system/index.ts
git status --short
```

If any file is absent, check registered routes and actual import paths; do not create placeholder files. Review tracked generated-file changes before the next Git pull.

## 7. Frontend dependencies and production build

```bash
cd /var/www/OmniCast
export PATH="/opt/omnicast-deploy-bin:$PATH"
npm ci
npm run build
test -s public/build/manifest.json && echo 'Manifest OK'
```

**Stop on any build error.** A missing Vite manifest previously caused HTTP 500. The successful 12 November build transformed 2,590 modules and generated the manifest. An optional `fontaine` warning was not a build failure. Do not delete the last known-good build artifacts until the replacement is verified.

## 8. Database migrations and Laravel caches

Only proceed after a successful build and a verified database backup. Review pending migrations for destructive operations and compatibility with running workers.

```bash
cd /var/www/OmniCast
sudo -u www-data /usr/bin/php8.3 artisan migrate:status
sudo -u www-data /usr/bin/php8.3 artisan migrate --force
sudo -u www-data /usr/bin/php8.3 artisan optimize:clear
sudo -u www-data /usr/bin/php8.3 artisan optimize
sudo -u www-data /usr/bin/php8.3 artisan about --only=environment
```

Confirm `Environment: production` and `Debug Mode: DISABLED`. The attached runbook's privacy migration should remain applied if recorded as run; Laravel will not reapply it during normal `migrate --force`.

This release adds the `whatsapp_template_submissions` table; confirm its migration is recorded as run before opening the WhatsApp workspace. It also adds the `whatsapp.templates.approve` permission for Compliance reviewers. `RoleSeeder` synchronizes complete built-in role grants using `syncPermissions`, so review its effects on any manually adjusted built-in roles before running it in production. Run it only through the approved database change process, then confirm the Compliance Officer can access **Admin → WhatsApp → Template approvals** and client roles cannot.

After that review and approval, synchronize the built-in permissions:

```bash
sudo -u www-data /usr/bin/php8.3 artisan db:seed --class=RoleSeeder --force
```

## 9. Redis, Horizon, and queue health

```bash
redis-cli ping
sudo supervisorctl status
sudo -u www-data /usr/bin/php8.3 artisan horizon:terminate
sudo -u www-data /usr/bin/php8.3 artisan horizon:status
sudo -u www-data /usr/bin/php8.3 artisan config:show queue
```

Expected Redis response: `PONG`. Horizon should be supervised and return to `running` after graceful termination. If `horizon:status` is initially inactive, check Supervisor and logs; do not start a duplicate master. Confirm queue connection, job timeout, `retry_after` (previously 960 seconds), and failed-job handling are consistent. Redact secrets from configuration output.

## 10. Scheduler: verify before installing

```bash
sudo -u www-data /usr/bin/php8.3 artisan schedule:list
sudo crontab -l
sudo crontab -u www-data -l
sudo systemctl list-timers --all
sudo systemctl list-units --type=service --all | grep -Ei 'omnicast|schedule'
```

The earlier check showed **no `www-data` crontab**, but that alone does not prove the scheduler is absent: verify root cron, `/etc/cron.d`, and systemd services/timers first. If there is no existing scheduler, install **one** `www-data` cron entry with `sudo crontab -u www-data -e`:

```cron
* * * * * cd /var/www/OmniCast && /usr/bin/php8.3 artisan schedule:run >> /dev/null 2>&1
```

For initial verification, run `schedule:run` manually during an approved window, since it may dispatch real campaigns or billing jobs. Monitor scheduled task logs and failed jobs. Do not add duplicate scheduler triggers.

## 11. Web server, TLS, and permissions

Ensure the Apache virtual host document root points to `/var/www/OmniCast/public`, HTTPS is enabled, and `.env`, storage internals, and project source files are not publicly served. Verify application runtime writable paths (`storage` and `bootstrap/cache`) are writable by the application user without granting blanket write access to source code.

```bash
sudo apache2ctl configtest
sudo systemctl status apache2 --no-pager
ls -ld storage bootstrap/cache public/build
```

Do not run broad recursive `chmod 777` or chown the whole repository to `www-data`.

## 12. Smoke tests and release acceptance

```bash
curl -sS -o /dev/null -w 'Homepage: %{http_code}\n' \
  https://omnicast.sysup360.com/
curl -sS -o /dev/null -w 'Sitemap: %{http_code}\n' \
  https://omnicast.sysup360.com/sitemap.xml
curl -fsS https://omnicast.sysup360.com/robots.txt
test -s /var/www/OmniCast/public/build/manifest.json
sudo -u www-data /usr/bin/php8.3 artisan horizon:status
```

A homepage `200` is necessary but insufficient. In an authenticated browser session, verify login, dashboard, wallet, packages, campaigns, contacts, templates, notifications, admin queues, system pages, privacy requests/retention, and legal DPA pages. Confirm assets and API calls load without 404/500 errors. Use approved **test accounts and non-chargeable/sandbox flows** for Paystack, SMS, voice, email, and WhatsApp; do not send real campaigns during smoke testing without approval. Check queue processing, scheduler execution, webhooks, and balances.

For WhatsApp template acceptance, submit a template as a client user and confirm it appears only in that client's submission list. Confirm another client cannot view it or submit against the first client's sender assignment. Approve and reject separate submissions as a Compliance Officer, then submit the approved copy through the provider and sync the sender's templates. Confirm provider status becomes `approved` only for an exact content match and campaign authoring offers the template only after both approvals. Leave `WHATSAPP_ENABLED=false` and verify no real message is sent during these checks.

## 13. Troubleshooting

**HTTP 500 / Vite manifest missing:** verify `public/build/manifest.json`, inspect `storage/logs`, and rebuild only after Wayfinder exports and Node/PHP versions are correct.

**Missing `updateDetails` export:** run `artisan route:clear` before `wayfinder:generate`, then confirm the generated TypeScript export.

**Wayfinder permission denied:** inspect the exact file path and directory ownership; fix only the required generated directories.

**Node engine warnings:** check `node -v`; use supported Node 22.18+.

**Wrong production links:** ensure `APP_URL` uses HTTPS domain root, `VITE_APP_BASE_PATH` is empty for root deployment, and rebuild Vite assets.

**Cache table error:** inspect effective cache configuration and database cache tables; never wipe the production database to fix configuration.

**WhatsApp template is not available for campaigns:** verify the client's sender assignment is enabled and approved, the OmniCast review status is approved, the provider reports the template as approved, and **Sync templates** found an exact match for name, language, category, and body. Resolve a content mismatch by correcting and resubmitting the provider template; do not bypass the checks by enabling sending.

**Horizon inactive:** check Supervisor process and Horizon logs; avoid duplicate masters.

**Campaigns not dispatching:** verify scheduler trigger, `schedule:list`, Horizon, Redis, campaign time windows, account credits, and provider connectivity.

Useful commands:

```bash
cd /var/www/OmniCast
sudo tail -n 100 storage/logs/laravel.log
sudo tail -n 100 /var/log/apache2/error.log
sudo supervisorctl status
sudo -u www-data /usr/bin/php8.3 artisan schedule:list
sudo -u www-data /usr/bin/php8.3 artisan horizon:status
```

## 14. Rollback and recovery

1. Stop and document the failed release. Preserve error logs and the failed commit ID.
2. Restore the **previous approved code release and matching compiled assets** through your release management procedure. Avoid hard resets over unreviewed changes.
3. Restore the prior `.env` only if it was changed incorrectly; keep secrets protected.
4. Reinstall dependencies from the restored lockfiles if required, clear/rebuild Laravel caches, and gracefully restart Horizon.
5. **Do not automatically reverse database migrations.** Schema rollback requires an explicit, tested recovery plan; restoring a database backup can discard legitimate transactions created since the backup.
6. Re-test public and authenticated pages, queues, scheduler, and financial integrity before declaring recovery complete.

For higher availability, move toward immutable release directories, shared `.env`/storage, atomic symlink switch, and separate build artifacts; the current in-place Git workflow can briefly expose a mixed release during deployment.

## 15. Final deployment checklist

- [ ] Correct branch and approved commit; clean/reviewed Git status
- [ ] Verified database and media backups; `.env` protected
- [ ] PHP 8.3 and compatible Node.js; Composer dependencies installed
- [ ] Production `APP_DEBUG=false` and correct root base path
- [ ] Route cache cleared; Wayfinder generated; privacy/system imports verified
- [ ] `npm ci` and `npm run build` passed; Vite manifest exists
- [ ] Migrations reviewed and applied; Laravel caches rebuilt
- [ ] Redis healthy; Horizon supervised and running
- [ ] Exactly one scheduler configured and verified
- [ ] HTTPS homepage, sitemap, robots, authenticated pages tested
- [ ] Test campaigns, webhooks, wallets, billing, and provider flows checked
- [ ] Logs reviewed; rollback release and recovery owner recorded

**Related internal document:** `RUNNING-THE-SERVICES.md`.
