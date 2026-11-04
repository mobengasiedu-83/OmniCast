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

## Campaign approval mode and sender ID rules

Campaigns launch on their assets, not on a reviewer's sign-off. A campaign approves itself as
soon as the sender ID it broadcasts under (SMS) or its audio (voice) is approved, and the campaign
is only held in the `/approvals` queue when the platform asks for a human decision. Three switches
in `.env` control this, and all three can be overridden per tenant through platform settings
(`campaigns.require_approval`, `campaigns.require_approved_sender_id`, `campaigns.require_approved_audio`):

| Key | Default | Description |
| --- | --- | --- |
| `CAMPAIGNS_REQUIRE_APPROVAL` | `false` | When `true`, every submitted campaign waits for a reviewer before it can launch |
| `CAMPAIGNS_REQUIRE_APPROVED_SENDER_ID` | `true` | Blocks submission until the selected SMS sender ID is approved |
| `CAMPAIGNS_REQUIRE_APPROVED_AUDIO` | `true` | Blocks launch until a reviewer approves the voice recording or script |

SMS sender IDs are allowed spaces, keep the case the client typed and are limited to 11 characters
including spaces. These limits are configuration, not code:

| Key | Default | Description |
| --- | --- | --- |
| `SMS_SENDER_ID_MIN_LENGTH` | `3` | Shortest accepted SMS sender ID |
| `SMS_SENDER_ID_MAX_LENGTH` | `11` | Longest accepted SMS sender ID, spaces included |
| `SMS_SENDER_ID_ALLOW_SPACES` | `true` | Whether spaces belong to the identity (`false` restricts it to letters, digits and symbols) |
| `EMAIL_SENDER_ID_MAX_LENGTH` | `120` | Longest accepted email from address (capped at the 120-character `sender_ids.name` column) |

SMS sender IDs are compared case-sensitively, so `OmniCast` and `omnicast` are two different
sender IDs on one account; email addresses are lower-cased before they are stored.

## Voice broadcasting (phase 8)

A voice campaign originates one call per recipient through a `VoiceGateway`. The driver is
`voice.driver` (platform setting) over `config/voice.php`:

| Key | Default | Description |
| --- | --- | --- |
| `VOICE_DRIVER` | `log` | `log` writes the originate to `storage/logs/laravel.log`, `ami` uses the Asterisk Manager Interface, `ari` uses the Asterisk REST Interface |
| `VOICE_BROADCAST_ENABLED` | `false` | Live PBX drivers refuse every call while this is off; `log` always dials |
| `VOICE_OUTBOUND_CALLER_ID` | — | The single platform DID every voice campaign dials from |
| `VITALPBX_AMI_HOST` / `_PORT` / `_USERNAME` / `_SECRET` / `_TIMEOUT` | `127.0.0.1` / `5038` / — / — / `5` | AMI account used for `Originate` |
| `VITALPBX_TRUNK_NAME` / `VITALPBX_BROADCAST_CONTEXT` | `SIP/omnicast` / `omnicast-outbound` | PJSIP endpoint and dialplan context of the broadcast trunk |
| `VITALPBX_ARI_URL` / `_USERNAME` / `_SECRET` / `_APP` / `_ENDPOINT` | `http://127.0.0.1:8088/ari` / — / — / `omnicast` / `PJSIP` | ARI originate settings |
| `VOICE_WEBHOOK_SECRET` | — | Shared secret for the switch callbacks; when empty every callback is refused |
| `VOICE_IVR_TIMEOUT_SECONDS` / `_MAX_ATTEMPTS` / `_OPT_OUT_DIGIT` | `6` / `3` / `9` | Defaults for the campaign keypad menu; a campaign can override timeout and repeats, never the opt-out digit |

The call reference OmniCast generates is sent to the switch as both `Exten` and `ActionID`, and the
dialplan reads the `OMNICAST_CALL`, `OMNICAST_CALLER_ID`, `OMNICAST_SCRIPT`, `OMNICAST_RECORDING`,
`OMNICAST_MAX_DURATION` and `OMNICAST_IVR_*` variables.

Call events come back on `POST /webhooks/voice`, signed with `X-Omnicast-Signature:
hash_hmac('sha256', $rawBody, VOICE_WEBHOOK_SECRET)` (a `sha256=` prefix is accepted):

```json
{ "reference": "01H…", "event": "dtmf", "digits": "9", "event_id": "switch-1234", "duration": 42, "hangup_cause": "NORMAL_CLEARING" }
```

`event` is one of `initiated`, `ringing`, `answered`, `dtmf`, `completed`, `failed`. The endpoint
answers `202` for an unknown reference, `403` for a missing/incorrect signature and `422` for an
unknown event, so a switch retry can never move a call that this platform did not dial. Call logs and
per-campaign call reports are available at `/reports/voice`.

## Verification commands

```bash
php artisan route:list
vendor/bin/pint --dirty --format agent
npm run types:check
npm run build
```
