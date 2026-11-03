# GWO SEVO OmniCast

Laravel and Inertia application for client onboarding and voice/SMS campaign management.

## Local URL

Use the configured ServBay URL:

```text
https://localhost/gwosevo-omnicast/public
```

`.env` should set `APP_URL` to that URL. The frontend base path in `resources/js/lib/base-path.ts` must match `/gwosevo-omnicast/public`.

## Install and run

```bash
composer install
npm install
php artisan migrate
npm run dev
```

For production assets, run `npm run build`. Configure the database, session, mail, and application key in `.env` before running the app.

## Client account signup

Clients register at `/register-client` with company name, contact name, email, phone, country, timezone, requested services, and an OTP delivery choice. Country selection defaults the timezone to a valid timezone in that country; clients can select another timezone within the selected country.

The OTP can be delivered by email or SMS. It expires after 10 minutes, and verification by the selected channel must succeed before password creation. Email verification or phone verification independently satisfies the account verification gate; users are not asked to complete both. New client accounts and organisations begin in the pending review state. Staff approval and compliance activation still control broadcasting.

### Configure SMS OTP delivery

SMS OTP is configured in `.env` with `SMS_DRIVER`, `SMS_ENDPOINT`, `SMS_TOKEN`, and `SMS_SENDER_ID`. The TxtConnect defaults are selected in both environment templates; put the bearer token in the local `.env` and keep it out of source control. The same values can be overridden in `/admin/settings` under **providers** (platform settings take precedence over `.env`):

| Key | Description |
| --- | --- |
| `SMS_DRIVER` / `sms.driver` | `txtconnect`, `kannel`, or `generic_json` |
| `SMS_ENDPOINT` / `sms.endpoint` | Provider URL; TxtConnect defaults to `https://api.txtconnect.net/dev/api/sms/send` |
| `SMS_TOKEN` / `sms.token` | TxtConnect bearer token; platform UI values are encrypted as secrets |
| `sms.username` | Provider username, if needed for Kannel or generic JSON |
| `sms.password` | Provider password; platform UI values are encrypted as secrets |
| `SMS_SENDER_ID` / `sms.sender_id` | Sender name/number accepted by the provider; defaults to `OmniCast` |

TxtConnect sends a bearer-authenticated JSON request with `to`, `from`, `unicode: "1"`, and `sms` fields. It treats the message as accepted only when the API returns HTTP success, `data.status_code: "000"`, and `data.in_error: false`. The phone number is sent as digits without the leading `+`. The Kannel driver posts `username`, `password`, `to`, `from`, and `text` as form fields and expects a successful HTTP response beginning with `0:`. The `generic_json` driver posts `{ "to", "from", "message" }` as JSON, uses HTTP Basic authentication when credentials are set, and accepts a 2xx response with `success: true` (or an empty success body).

Outgoing email and SMS activity is written to `storage/logs/email.log` and `storage/logs/sms.log`. Logs record transport results and limited metadata; OTP values, message bodies, bearer tokens, and full recipient addresses/numbers are excluded.

If SMS provider settings are missing or the provider rejects a message, signup reports that SMS delivery is unavailable and does not advance to OTP entry. Email delivery continues to use Laravel's configured mail transport.

## Global and organisation settings (phase 4)

Platform staff can manage settings at `/admin/settings`; clients can manage their organisation preferences at `/settings/organisation` and request sender/caller/domain resources at `/settings/resource-requests`. Staff review requests at `/admin/resource-requests`.

Platform setting keys support global values, plan values, and client overrides. Resolution order is global → plan → client. Secret fields are encrypted at rest and omitted from serialized settings; changes are audited by setting key without recording secret contents.

Examples include `channels.sms.enabled`, `feature_flags.channels.sms`, `countries.enabled` (comma-separated ISO country codes), `rate_limits.login_attempts_per_minute`, `rate_limits.client_registrations_per_minute`, `storage.max_upload_kb`, and `storage.client_documents_disk`. Client document storage must use a configured private disk. Provider credentials and client overrides are tenant-scoped and resolved through the shared `PlatformConfiguration` service.

## Verification commands

```bash
php artisan route:list
vendor/bin/pint --dirty --format agent
npm run types:check
npm run build
```
