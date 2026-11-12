# OmniCast WhatsApp Integration

## Complete Technical & Functional Specification

Version 2.2 | 12 November 2026

Laravel 13 • Pro Plus and Meta Cloud API • Separate WhatsApp Wallet • Existing Media Storage

This document defines the architecture, implementation plan, operational controls, pricing model, and launch gates for integrating WhatsApp into the OmniCast platform.

## Executive decisions

- Keep WhatsApp inside the existing OmniCast Laravel application and reuse existing users, contacts, permissions, payments, media library, and infrastructure.
- Keep WhatsApp client balances and ledger separate from SMS, Voice, and Email balances. Continue using one OmniCast identity, client account, contacts, and central payment system.
- Offer WhatsApp wallet top-ups as credit rather than fixed message bundles. Charge by billable delivered message category and recipient market; preserve existing packages and balances unchanged.
- Retain the current Laravel storage model. Add WhatsApp media validation and provider-retrievable HTTPS URLs without exposing private client assets.
- Treat delivery tracking, webhook support, API throughput, provider fees, and billing evidence as dependencies that require confirmation from Pro Plus before production release.

## 1. Scope and operating principles

This scope includes provider connections, WABA and sender assignment, approved template synchronization, campaign creation, segmentation, opt-in evidence, queue-based sending, message logs, delivery events, reporting, wallet funding, rate-card management, markup, reconciliation, security, and operations.

The initial release supports outbound approved-template messaging documented by Pro Plus. Inbound chat and template-submission capabilities remain conditional on additional provider APIs and clear compliance controls.

## 2. Architecture and deployment

Browser/Admin/Client Portal → Laravel application and channel services → Redis/Horizon workers → Pro Plus or client-owned Meta Cloud API adapter → WhatsApp Business Platform.

MySQL stores campaign state, templates, message events, consent records, and immutable wallet entries. Existing Paystack funding, notifications, scheduler, and storage are reused.

| Layer | Implementation |
| --- | --- |
| Backend | Laravel 13, PHP 8.3, existing OmniCast authentication and RBAC |
| Database | Existing MySQL; WhatsApp tables namespaced as `whatsapp_*` |
| Async processing | Redis + Horizon; dedicated priority queues and per-provider throttles |
| Integration | Laravel HTTP client behind a `WhatsAppProvider` interface; Pro Plus shared credentials or client-owned Meta Embedded Signup tokens |
| Storage | Existing Laravel disk/media library with controlled HTTPS media delivery |
| Operations | Existing deployment, cron/scheduler, logging, alerts, and backups |

## 3. Provider API contract

The exported Postman collection `Pro Plus Whatsapp API` uses HTTP Basic authentication with `Client-id` as the username and `ApiKey` as the password. Requests use the `{{whatsapp}}` collection variable as the base URL, but the export contains no value for that variable; obtain the deployment-specific host from Pro Plus and set it as `WHATSAPP_API_BASE_URL`. The credentials belong in `WHATSAPP_API_CLIENT_ID` and `WHATSAPP_API_KEY`. Set the base URL to the origin only, with endpoint paths configured separately. Keep real credentials in an untracked deployment `.env` or secret store, never in `.env.example`.

Documented endpoints include:

- `GET /api/whatsapp/v1/Connector/GetAll`
- `GET /api/whatsapp/v1/Template/Get?MetaTemplateId=...`
- `GET /api/whatsapp/v1/Template/GetAll`
- `POST /api/whatsapp/v1/Send`

`Template/GetAll` accepts `WABAID` and optional `offset`, `limit` (maximum 25), and `templateStatus` values. The collection models these as form-data fields on GET requests; confirm how Pro Plus expects them encoded in sandbox testing:

- `0` draft
- `1` pending
- `2` rejected
- `3` approved

One saved request description says `2 = Approved`, conflicting with the collection's error-code/status table, which says `2 = Rejected` and `3 = Approved`. Treat this mapping as unconfirmed until Pro Plus clarifies it.

The collection's send examples use `POST /api/whatsapp/v1/Send` with JSON payloads using `SenderNumber`, `MetaTemplateId`, and either `BulkPayload` or `CustomPayload`. Individual custom recipients use the documented spelling `RecieverNumber`. A successful bulk response example has `statusCode: 0`, `status: "Success"`, and a `messages` array whose entries contain `messageId` and `receiverNumber`; accept a submission only when the response includes a provider message reference. The collection documents up to 100,000 valid recipients per request, but OmniCast must apply its own campaign chunking and rate limits rather than send that full amount as one request.

The collection documents these outbound variations:

- Fixed template
- Body variables
- Header variable
- Media header via `MediaFilePath`
- Location header via `LocationDetail`
- Per-recipient `CustomPayload`
- Authentication OTP template

Implementation requirement: build a typed adapter and payload validators for each supported variation.

## 4. API client and service layout

Recommended modules:

- `Domain/WhatsApp/Contracts/WhatsAppProvider`
- `Providers/ProPlusWhatsAppProvider`
- `Services/ConnectorService`
- `Services/TemplateService`
- `Services/CampaignService`
- `Services/RecipientService`
- `Services/DispatchService`
- `Services/StatusService`
- `Services/PricingService`
- `Services/WalletSettlementService`
- `Services/MediaService`
- `Jobs/PrepareCampaign`
- `Jobs/DispatchBatch`
- `Jobs/SendMessage`
- `Jobs/ProcessStatus`
- `Jobs/ReconcileUsage`
- `Models`, `Policies`, `Events`, and DTOs

Connection settings must include:

- Base URL
- Encrypted client ID and API key
- Timeouts
- Connector identifier
- Permitted clients
- Status
- `tested_at` timestamp

Implementation requirements:

- Request correlation IDs
- Redacted logs
- Response parsing
- Retry classification
- Circuit breaking
- No credential or full OTP logging

Map provider responses to internal statuses: `Accepted`, `Rejected`, and `Unknown`. `HTTP 200` alone is not delivery evidence.

Use a provider interface to support future Meta Cloud API or alternative BSP integrations without rewriting campaigns.

## 5. Database schema

The WhatsApp data model should be scoped under tenant-aware tables with foreign keys and indexes. Financial immutability and delivery-traceability must be enforced at the schema level.

| Table | Core purpose / fields |
| --- | --- |
| `whatsapp_providers` | `id`, `name`, `driver`, `status`, enabled state and request timeouts |
| `whatsapp_connectors` | `provider_id`, `external_id`, `waba_id`, `sender_number`, `status` |
| `whatsapp_client_connectors` | `client_id`, `connector_id`, `enabled`, `approval_status` |
| `whatsapp_templates` | `client_id`, `connector_id`, `meta_template_id`, `language`, `category`, `status` |
| `whatsapp_template_components` | `template_id`, `type`, `content`, `variables_json` |
| `whatsapp_template_versions` | `template_id`, `snapshot_json`, `synced_at` |
| `whatsapp_campaigns` | `client_id`, `connector_id`, `template_id`, `status`, `scheduled_at` |
| `whatsapp_campaign_recipients` | `campaign_id`, `contact_id`, `phone`, `variables_json`, `eligibility` |
| `whatsapp_messages` | `campaign_id`, `recipient_id`, `internal_uuid`, `provider_id`, `status`, timestamps |
| `whatsapp_message_attempts` | `message_id`, `attempt`, `http_status`, `provider_response`, `latency_ms` |
| `whatsapp_message_events` | `message_id`, `event_id`, `type`, `occurred_at`, `raw_payload` |
| `whatsapp_contact_consents` | `contact_id`, `client_id`, `purpose`, `source`, `granted_at`, `revoked_at` |
| `whatsapp_suppression_list` | `client_id`, `phone`, `reason`, `created_at` |
| `whatsapp_pricing_rates` | `country_market`, `category`, `currency`, `rate`, effective dates |
| `whatsapp_pricing_tiers` | `rate_id`, `volume_from`, `volume_to`, `discounted_rate` |
| `whatsapp_pricing_profiles` | `client_id`, `markup`, `fee`, `fx_buffer`, `minimum_price` |
| `whatsapp_fx_rates` | `currency_pair`, `rate`, `source`, `effective_at` |
| `whatsapp_wallets` | `client_id`, `currency`, `balance`, `reserved_balance`, `status`; isolated from other channel balances |
| `whatsapp_wallet_transactions` | `wallet_id`, `type`, `reference`, `amount`, `balance_after`, `posted_at`; immutable WhatsApp-only ledger |
| `whatsapp_wallet_reservations` | `wallet_id`, `message_id`, `amount`, `status`; atomic reservation and settlement lifecycle |
| `whatsapp_usage_records` | `message_id`, `country`, `category`, `provider_cost`, `selling_price`, `settlement_state` |
| `whatsapp_provider_payables` | `provider_id`, `period`, `currency`, `amount_due`, `amount_paid`, `status`; tracks Meta/Pro Plus liabilities separately from client credit |
| `whatsapp_webhook_events` | `provider_id`, `event_id`, `payload`, `verified`, `processed_at` |
| `whatsapp_provider_invoices` | `provider_id`, `period`, `totals`, `reconciliation_state` |
| `whatsapp_audit_logs` | `actor`, `action`, `target`, `before_after`, `timestamp` |

Schema rules:

- Enforce tenant scopes and foreign keys
- Unique internal message UUID and settlement reference
- Immutable financial ledger
- Indexed campaign, status, time, and provider message IDs
- E.164 phone normalization
- Decimal precision for rate computation
- Retention and encryption for sensitive payloads

## 6. Existing media storage integration

Retain the existing OmniCast Laravel filesystem disk and upload/media-library workflow. Add WhatsApp-specific metadata and template associations, file MIME and size validation, thumbnails or previews, malware screening where available, per-client authorization, and lifecycle cleanup.

Do not migrate to S3 or MinIO as a prerequisite.

For a media template, Pro Plus requires `MediaFilePath` to contain a publicly retrievable image, video, or document URL. Generate a dedicated HTTPS media-serving route or a controlled public copy.

Signed URLs are acceptable only when the provider can retrieve them and their expiry exceeds the fetch window. Test remote retrieval, redirects, TLS, content type, and maximum file size. Do not expose the entire private media library.

## 7. Campaign lifecycle and recipient processing

Campaign states should follow this lifecycle:

`Draft → Validate → Ready → Reserve funds → Scheduled/Queued → Sending → Submission complete → Delivery reconciliation → Completed / Completed with failures`

Controlled branches include:

- `Paused`
- `Cancelling`
- `Cancelled`

Campaign completion must not imply that all messages were delivered.

Recommended campaign wizard flow:

`Campaign details → Sender → Approved template → Audience → Variable mapping → Media preview → Consent/number validation → Estimated cost → Send or schedule`

Contact and recipient requirements:

- Shared contact records
- WhatsApp opt-in by purpose
- CSV/XLSX import
- Groups and tags
- Segmentation
- Deduplication
- Global and client suppression

At launch, create immutable recipient snapshots, validate each placeholder, and recheck suppression immediately before dispatch.

Support the following operational features:

- Test messages
- Cloning
- Time zones
- Sending windows
- Pause/resume
- Cancellation
- Per-recipient audit
- Export

## 8. Queues, workers, and retry strategy

| Queue | Responsibilities |
| --- | --- |
| `whatsapp-critical` | OTP and high-priority transactional jobs |
| `whatsapp-send` | Bulk marketing and utility dispatch |
| `whatsapp-status` | Trusted webhook events and status updates |
| `whatsapp-retry` | Retryable temporary failures |
| `whatsapp-template-sync` | Connector and template refresh |
| `whatsapp-billing` | Reservation settlement and reconciliation |
| `whatsapp-reports` | Exports and analytics |

Suggested worker tuning:

- 2 critical workers
- 4 send workers
- 2 status workers
- 2 retry workers
- 2 billing workers
- 1 sync/report worker

Then tune using provider TPS limits and latency.

Operational rules:

- Use Redis distributed rate limits per provider, connector, and client
- Apply exponential backoff and jitter to temporary failures
- For ambiguous timeouts, retain `Unknown` status until reconciliation
- Never blindly retry a possibly accepted message
- Scheduler cadence:
  - Due campaigns: every minute
  - Health checks: every 5 minutes
  - Billing reconciliation: every 15 minutes
  - Template sync: every 30 minutes
  - Daily reports and retention cleanup

## 9. Delivery status and analytics

Internal states include:

`Pending`, `Reserved`, `Queued`, `Submitting`, `Accepted`, `Sent`, `Delivered`, `Read`, `Failed`, `Unknown`, `Cancelled`

Persist raw events, deduplicate event IDs, correlate provider message IDs, and prevent late older events from overwriting newer states.

Delivery, read, and billable status must depend on provider-confirmed callbacks or usage records; the uploaded guide does not document such an endpoint.

KPIs to track:

- Eligible recipients
- Accepted submissions
- Confirmed delivered/read counts
- Failures by reason
- Retries
- API latency
- Queue delay
- Campaign spend
- Average cost
- Revenue
- Margin
- Wallet reservations
- Reconciliation variance

Filters should support client, sender, template, country, category, campaign, and date.

## 10. Separate WhatsApp wallet, package units, and usage

Each client has a dedicated WhatsApp wallet denominated in GHS. SMS, Voice, and Email continue using their existing packages and balances. OmniCast retains one login, one client account, shared contacts, and the existing Paystack payment integration, but WhatsApp money is never drawn from or credited to another channel's wallet.

WhatsApp is pay-as-you-go from the dedicated wallet. Top-up denominations are funding conveniences, not fixed message bundles; support custom top-ups where the payment flow permits. A client purchasing GHS 500 receives GHS 500 of WhatsApp credit, subject only to separately disclosed payment fees or taxes.

SMS package-first rules remain unchanged. WhatsApp campaigns reserve funds only from the WhatsApp wallet and cannot reduce SMS, Voice, or Email balances.

Example:

- Client owns 55,000 SMS package units, GHS 500 in the existing non-WhatsApp wallet, and GHS 300 in the separate WhatsApp wallet
- SMS sends consume eligible SMS units first
- WhatsApp deliveries debit only WhatsApp wallet credit
- SMS units and the existing wallet remain unchanged by WhatsApp activity
- Voice usage follows existing billing rules and wallet fallback only when configured

Wallet accounting flow:

`Estimate charge → Reserve WhatsApp funds atomically → Dispatch → Obtain billable delivery evidence → Settle client charge at saved rate snapshot → Release unspent reservation → Accrue provider payable → Reconcile provider invoice`

Unknown outcomes retain WhatsApp wallet reservations subject to documented exception deadlines. Top-ups use the existing Paystack integration but credit only the WhatsApp wallet. Keep client wallet liability, client usage revenue, provider costs/payables, provider invoice settlements, and gross profit as separate accounting measures. Client top-ups are prepaid liabilities, not immediate revenue.

## 11. Official Meta pricing and markup model

Pricing should be based on the official WhatsApp Business Platform rate card for per-delivered-message category and recipient market, with applicable exceptions, volume tiers, and provider charges.

Maintain effective-dated rate cards and do not hardcode permanent prices. The following illustrative Ghana / Rest-of-Africa planning rates were used in the earlier design and must be verified against the live official Meta rate card before launch.

| Category | Illustrative Meta USD | Markup | Illustrative client GHS |
| --- | ---: | ---: | ---: |
| Marketing | 0.0225 | 40% | 0.378 |
| Utility | 0.0040 | 50% | 0.072 |
| Authentication | 0.0040 | 50% | 0.072 |

Important notes:

- `USD 1 = GHS 12` is only an example and not a live quote.
- Selling price is calculated as: `(Meta rate × effective FX + Pro Plus provider fee + direct per-message cost) × (1 + configured markup)`.
- Apply approved tax and rounding rules separately.
- Markup is not gross margin.
- Add recipient-country lookup, volume discounts, international authentication treatment, effective dates, approval gates, client overrides, minimum margins, and pricing snapshots.

Example:

- 10,000 Ghana marketing messages at GHS 0.378 each reserve GHS 3,780
- If 9,500 messages are confirmed billable, settle GHS 3,591 and release GHS 189
- Underlying illustrative Meta cost is GHS 2,565, and gross profit is GHS 1,026 before overheads and taxes

## 12. Admin portal menu and submenu matrix

| Main menu | Submenus / functionality |
| --- | --- |
| WhatsApp Dashboard | Overview, Live Activity, Delivery, Revenue, Provider Health |
| Providers | Provider Registry, API Credentials, Connection Tests, Rate Limits |
| Accounts & Senders | WABA Accounts, Connectors, Sender Numbers, Client Assignment |
| Templates | All, Approved, Pending, Rejected, Sync, Versions, Preview |
| Campaigns | All, Draft, Scheduled, Running, Paused, Completed, Failed |
| Messages | Message Logs, Attempts, Events, Retry Queue, Unknown Outcomes |
| Clients | WhatsApp Access, Limits, Connector Assignment, Pricing Profiles |
| Pricing & Billing | Meta Rates, Tiers, FX, Markups, Provider Fees, Wallet Ledger, Adjustments |
| Reports | Delivery, Usage, Cost, Revenue, Margin, Provider Reconciliation |
| Compliance | Consent, Opt-outs, Suppression, Policy Events |
| Operations | Horizon, API Health, Webhook Inbox, Alerts, Audit |
| Settings | Sending Windows, Retry Rules, Media Rules, Retention, Notifications |

## 13. Client portal menu and submenu matrix

| Main menu | Submenus / functionality |
| --- | --- |
| WhatsApp Overview | Wallet balance, campaigns, delivered/failed, usage, spending |
| Campaigns | Create, Drafts, Scheduled, Running, Completed, Duplicate |
| Templates | Approved Library, My Templates, Preview, Variables |
| Contacts | All, Groups, Segments, Import, Consent, Suppression |
| Sender Accounts | Assigned Numbers, WABA Details, Status |
| Messages | History, Delivery Reports, Errors, Search |
| Wallet & Billing | Unified Balance, Top Up, Usage by Channel, Transactions, Pricing, Invoices |
| Reports | Campaign Performance, Delivery, Spend, Exports |
| Media Library | Shared Media, WhatsApp Eligibility, Upload, Preview |
| Settings | Sending Defaults, Team Permissions, Alerts |

## 14. Access control, compliance, and security

Granular permissions should include:

- `whatsapp.view`
- `providers.manage`
- `connectors.manage`
- `templates.sync`
- `campaigns.create/control`
- `messages.retry`
- `pricing.manage`
- `wallet.adjust`
- `reports.financial`
- `compliance.manage`

Enforce client isolation in queries, policies, queues, file access, and exports. Encrypt credentials and sensitive data, redact logs, and apply API/webhook verification.

Record channel-specific opt-in source, wording, timestamp, and purpose; support opt-out and suppression. SMS opt-in does not automatically imply WhatsApp marketing consent.

Implement retention, deletion/export workflows, audit trails, backup/restore, secret rotation, least privilege, and review of Ghana Data Protection Act obligations.

Do not expose inbound chat or free-form reply features until provider confirmation, consent, and service-window controls are in place.

## 15. Phased implementation plan

Sections 2–14 define the target architecture, product behavior, and control requirements. The phases below turn those requirements into a sequenced delivery plan. Each phase has a completion gate; do not progress to production use of a capability until its gate and relevant acceptance tests pass.

Some work can proceed in parallel after its dependencies are met. Provider-dependent features must remain disabled until Pro Plus confirms the required API and commercial contracts.

### Phase 0 — Provider discovery and delivery planning

**Goal:** Resolve external dependencies early so implementation is based on verified provider behavior rather than assumptions.

**Work items:**

- Obtain sandbox credentials and the actual API base URL.
- Confirm authentication, request encoding, endpoint limits, response schemas, error codes, recipient limits, and throughput limits.
- Confirm connector and sender ownership/assignment rules and template status semantics.
- Request webhook or status-callback specifications, message ID semantics, idempotency behavior, and reconciliation options.
- Confirm media requirements, provider fetch timing, supported formats and limits.
- Obtain provider fees, invoice format, billing basis, and any inbound messaging or template-submission contracts.
- Verify current Meta rate-card sources, recipient-market mapping, applicable exceptions, and the intended FX source.
- Record unresolved items as launch blockers; do not infer missing contracts from successful HTTP responses.

**Exit gate:** A reviewed provider contract and sandbox test plan are available. Any unavailable callback, billing, inbound, or template-submission feature is explicitly out of initial-release scope.

Shared deployment configuration is supplied through `WHATSAPP_API_BASE_URL`, `WHATSAPP_API_CLIENT_ID`, and `WHATSAPP_API_KEY`. Endpoint paths can be overridden with `WHATSAPP_CONNECTORS_ENDPOINT`, `WHATSAPP_TEMPLATES_ENDPOINT`, and `WHATSAPP_SEND_ENDPOINT`. These values are consumed through `config/whatsapp.php` and are not stored in the provider database table or exposed as editable form fields.

### Phase 1 — Foundation and platform integration

**Goal:** Establish the WhatsApp module and shared platform foundations without changing existing channel behavior.

**Work items:**

- Add the WhatsApp domain structure, migrations, models, tenant relationships, and required indexes.
- Define the `WhatsAppProvider` contract and the Pro Plus adapter boundary.
- Add provider configuration with connection status, timeouts, and tested-at tracking. The shared base URL, client ID, API key, and provider endpoint paths live in environment variables and are never stored in MySQL.
- Add granular permissions, policies, audit events, and tenant-isolation rules.
- Establish request correlation, credential/OTP redaction, response normalization, and error classification.
- Configure WhatsApp queue names and worker conventions alongside existing infrastructure.
- Reuse existing users, clients, contacts, payments, storage, notifications, Redis, and Horizon where applicable.

**Applied in v2 (12 November 2026):**

- Added the `whatsapp_*` provider, connector, client assignment, and audit tables with foreign keys, uniqueness constraints, and lookup indexes; added typed Eloquent models and tenant relationships. Shared provider credentials are environment-only; a follow-up migration removes the earlier credential table and provider URL column.
- Added the `WhatsAppProvider` interface, Pro Plus HTTP adapter, normalized response DTO, and connection configuration screen. Shared endpoint and credential values are read from `config/whatsapp.php`, backed by `.env`; the provider table stores only provider metadata, enablement and timeouts.
- Added provider/connector permission values, role grants, policies, admin routes, and safe provider audit records. Connector assignment policy checks tenant ownership before allowing client access.
- Added request correlation IDs, bounded HTTP timeouts, recursive response redaction for credentials and OTP values, and normalized accepted/rejected/unknown response handling. A successful HTTP response without a provider message reference remains `Unknown` for submissions.
- Added a dedicated `whatsapp` queue policy and Horizon supervisor configuration. No message dispatch job or campaign channel was enabled in this phase.
- Set `WHATSAPP_ENABLED=false` by default. The adapter refuses sends unless both the platform flag and provider switch are enabled; provider contract/sandbox confirmation remains a prerequisite before turning this on.

**Verification recorded 12 November 2026:** The historical implementation record reports eight WhatsApp feature tests and ten queue feature tests passing (18 tests, 85 assertions) on isolated SQLite, and the foundation migration/schema inspected on local MySQL. These environment-specific database and role-seeder observations are historical; they do not establish that migrations have been applied to any current deployment. Current focused verification is summarized in Section 19.

**Remaining gate:** Phase 0's provider contract and sandbox confirmation remains open; this blocks real sending and does not block the Phase 1 foundation work.

### Phase 2 — Provider connection, connectors, templates, and media

**Goal:** Make approved sender accounts and template data available to the application.

**Implemented:**

- Pro Plus is configured through the shared base URL, Basic Auth client ID/API key, and endpoint paths from `.env`. A prior environment returned HTTP 200 for connector/template requests; that observation did not validate response correctness, current credentials, sandbox contract semantics, or provider-side behavior and must be repeated against the deployment sandbox.
- Added admin connector sync and client sender access requests, review, enable/disable controls, connector/WABA/number display, and audit records.
- Added template synchronization for metadata, language, category, status, components and immutable content versions. Sync uses `Template/Get` to fetch details when list items omit components. Unknown or numeric provider statuses remain non-approved; only an explicit `approved` status is exposed to clients.
- Added payload validation for Pro Plus `BulkPayload` and `CustomPayload` forms, ordered body/header variables, media/document URLs, location details, carousel cards, and authentication/OTP templates. Message submission remains separately gated by provider contract verification and the global sending switch.
- Added client template previews and history counts. Client queries include only templates from enabled and approved connector assignments.
- Registered WhatsApp in platform and tenant module availability. The global and tenant entitlement switches hide the client navigation and reject direct portal routes while leaving unrelated pages available; WhatsApp is not added as a campaign channel in this phase.
- Added WhatsApp beside SMS, Voice, and Email in the platform module availability screen and tenant module map. Client navigation, module status, and route middleware all read the same live platform switch plus client entitlement; changes take effect without stale in-process module state. Admin provider and connector management remains governed by its separate permissions.
- Added private WhatsApp media storage, MIME allowlist (JPEG, PNG, WebP, MP4, PDF), configurable `WHATSAPP_MEDIA_MAX_KILOBYTES` (16 MB default pending provider limit confirmation), tenant ownership checks, and signed HTTPS URLs that expire after 30 minutes. Signed media responses stream private bytes with `no-store` and `nosniff` headers.
- Added the Phase 2 migrations for template catalog, template versions, media assets, and connector names. Apply and inspect them in each target environment as part of deployment; the local historical result is not a deployment guarantee.

**Verification recorded 12 November 2026:** Historical tests covered signed media retrieval, expiry/signature rejection, catalog synchronization, version capture, application-level error handling, numeric-status safety, and module switches. The prior provider requests inspected only HTTP status and record counts. Current focused verification is summarized in Section 19; public provider retrieval and production schema state remain deployment checks.

**Deployment check:** The signed media route is implemented and covered locally. Provider-side retrieval over a public HTTPS deployment URL, including TLS, redirect, content type, size, and expiry behavior, must be confirmed after deployment. Real sending remains disabled until provider contract and sandbox gates pass.

### Phase 3 — Consent, contacts, and campaign authoring

**Goal:** Prepare eligible, auditable campaign recipients and campaign definitions.

**Work items:**

- Record WhatsApp consent by client, purpose, source, wording, and timestamp; support revocation and suppression.
- Add contact import, groups, tags, segmentation, normalization, deduplication, and global/client suppression checks.
- Build campaign creation for sender, approved template, audience, variable mapping, optional media, schedule, and time zone.
- Create immutable recipient snapshots and validate required variables and phone numbers.
- Recheck consent and suppression immediately before dispatch.
- Add cost estimation and clear eligibility/error previews before the user schedules or sends.
- Support draft, clone, test-send, and schedule workflows.

**Applied in v2.2 (12 November 2026):**

- Added tenant-scoped WhatsApp consent records with purpose, exact opt-in wording, source, grant time, recorder, and revocation time. Consent evidence is immutable; revocation is retained and new grants are recorded as new rows. Consent capture and revocation are audited.
- Reused existing contacts, lists, tags, saved segments, imports, and the suppression registry. WhatsApp campaign audience preparation filters to the selected tenant and list/segment and checks E.164 formatting, DND, client/global WhatsApp suppression, and active WhatsApp consent for the template category.
- Added a separate WhatsApp campaign draft workspace. It requires a client-approved enabled connector assignment and an approved template belonging to that connector, and supports audience selection, variable mapping, optional client media, schedule metadata, and timezone.
- Draft creation produces immutable recipient snapshot rows with phone, contact fields, tags, variable values, consent evidence reference, template content hash, connector, and capture time. Each row records eligibility and human-readable exclusion reasons. Changes to source inputs are blocked after snapshots exist; create a new draft to change them.
- Added recipient placeholder checks against template body variables, eligible-recipient totals, and a cost estimate. Without an approved WhatsApp rate card the estimate is explicitly unavailable; a submitted unit price produces an indicative estimate only and is never treated as a billable price.
- Explicitly blocked `whatsapp` in the legacy generic campaign form and guarded the existing dispatcher against WhatsApp. This prevents the Phase 3 authoring path from bypassing the new consent snapshots or the later Phase 4 dispatch gate.
- Added feature tests for consent grant/revocation, consent and suppression exclusions, eligibility snapshot evidence, indicative estimate calculation, and cross-tenant sender rejection.

**Verification recorded 12 November 2026:** Phase 3 coverage includes tenant consent, suppression, eligibility snapshots, pricing estimate behavior, and sender isolation. Later focused suite results are in Section 19. Migration state must be applied and checked independently per target environment.

**Remaining gate:** Phase 3 is implemented for consent capture, audience validation, immutable draft snapshots, and authoring. Contact import, groups/tags, and segmentation reuse existing contact-management workflows. Clone and dedicated test-send workflows are not evidenced as complete. Scheduled dispatch is implemented in Phase 4. Consent and suppression are rechecked immediately before dispatch. Production estimates still require approved current rate inputs.

**Exit gate:** Campaign validation produces a reproducible recipient snapshot; ineligible, suppressed, or non-consenting recipients cannot be dispatched.

### Phase 4 — Dispatch engine and operational controls

**Goal:** Send eligible messages reliably while avoiding accidental duplicate sends.

**Work items:**

- Implement prepare, batch-dispatch, and send jobs on the agreed priority queues.
- Add per-provider, connector, and client rate limits using Redis.
- Implement timeouts, retry classification, exponential backoff with jitter, and idempotency protections.
- Represent ambiguous provider outcomes as `Unknown` and hold them for reconciliation instead of blindly retrying.
- Add pause, resume, cancellation, sending windows, and per-recipient attempt history.
- Add queue health checks, API latency and queue-delay metrics, and operational alerts.
- Initially exercise dispatch in the sandbox and with controlled test sends.

**Applied in v2.3 (12 November 2026):**

- Added a WhatsApp-only prepare and batch-send pipeline on the dedicated `whatsapp` queue, plus scheduled-campaign pickup every minute. This pipeline uses the Phase 3 snapshots and does not call the legacy multi-channel campaign dispatcher.
- Added provider/connector/client rolling-window throttle settings, bounded dispatch batches, and counters for accepted, unknown, ineligible, rate-limited, stalled-preparation, and API latency observations. Defaults remain zero/unlimited for provider TPS until Pro Plus confirms contractual limits; sending is still controlled by `WHATSAPP_ENABLED`.
- Added per-recipient attempt rows with unique attempt numbers and correlation IDs, redacted request/response data, HTTP status, provider reference, error category, latency, and timestamps. Recipient state is atomically claimed before calling the provider, so duplicate queue deliveries cannot submit a row already claimed by another worker.
- Added explicit retry classification: only definitive temporary rejections in the provider, rate-limited, or transport categories can be retried with capped exponential backoff and jitter. Validation/authentication rejections fail without retry. Any exception, timeout, missing/ambiguous provider correlation, or other uncertain outcome becomes `Unknown` and is never automatically retried.
- Hardened provider response parsing to retain per-recipient provider references for bulk responses. Dispatch currently submits one recipient per provider request because the published contract does not confirm idempotency or an unambiguous partial-acceptance mapping for bulk retries. This favors duplicate prevention over throughput until Pro Plus confirms those semantics.
- Rechecks current contact ownership, phone stability, consent revocation, DND, and client/global suppression immediately before each provider call. Stale or newly ineligible rows are held and excluded.
- Added tenant-authorized dispatch/test, pause, resume, and cancel controls with audit entries; cancellation prevents queued-but-not-submitted rows from sending. Scheduled campaign claims use a conditional database update to prevent duplicate scheduler passes.
- Added provider enablement, connector availability, template approval, assignment, and global WhatsApp flag checks before sending. The generic campaign dispatcher and form remain blocked for WhatsApp.
- Added targeted tests for send-time consent revocation, unknown outcomes with exactly one attempt, scheduler double-run claiming, and tenant campaign isolation.

**Verification recorded 12 November 2026:** Historical tests covered send-time consent checks, unknown outcomes, scheduler claiming, tenant control, and provider behavior. Current focused verification is summarized in Section 19. Database migrations still require deployment-environment application and inspection.

**Remaining exit gate:** Pro Plus sandbox and controlled test-send verification has not been evidenced. Provider TPS, bulk correlation/idempotency behavior, and response/error classifications remain unconfirmed. Dispatch submits individually to avoid relying on unconfirmed bulk partial-acceptance semantics. Production sending must remain disabled until external checks pass. Webhook/delivery reconciliation and billing settlement are implemented behind Phase 6 contract gates; an accepted submission is not delivery confirmation.

**Exit gate:** Sandbox and controlled test sends demonstrate throttling, safe retry behavior, no duplicate dispatch on retry, and effective pause/cancel controls.

### Phase 5 — Pricing, separate WhatsApp wallet, and reservations

**Goal:** Quote and reserve customer funds accurately while preserving existing package balances.

**Work items:**

- Implement effective-dated rate cards, recipient-market lookup, category mapping, FX snapshots, provider fees, markup, rounding, and approval controls.
- Clearly mark illustrative rates as unverified until the current official rate card and provider charges are confirmed.
- Create a dedicated WhatsApp wallet, immutable transaction ledger, and reservation records; keep the existing Paystack integration but credit only the WhatsApp wallet.
- Preserve all existing SMS, Voice, and Email package balances and wallet behavior. Do not transfer funds between channel wallets implicitly.
- Separate client credit/liability, billable client usage, Meta/Pro Plus costs and payables, provider invoice settlements, and OmniCast gross profit in financial reporting.
- Implement atomic WhatsApp wallet reservations, immutable ledger entries, unique settlement references, top-up idempotency, and financial adjustment permissions.
- Snapshot the applied price inputs for every estimate and billable message.
- Define reservation handling and exception deadlines for unresolved `Unknown` outcomes.

**Exit gate:** Pricing calculations are reviewed against approved rate inputs; concurrent reservation tests prevent overspending; WhatsApp activity does not consume unrelated package or wallet balances; provider payable records reconcile to the selected billing arrangement.

**Applied — November 12, 2026:** Implemented effective-dated platform and client-specific rate inputs by recipient market, template category, and volume band. Administrators with rate permissions can submit rates into a pending state and approve them separately. The selling price is calculated from the Meta USD rate, FX conversion, provider fee, direct cost, markup, and minimum price, then rounded to four decimal places. Campaign estimates and recipient prices retain the approved rate inputs used to calculate them; client-provided unit prices are ignored.

**Implementation status and required change:** The current repository implementation reserves and settles WhatsApp usage against the shared OmniCast `Wallet` and records channel-tagged transactions there. That behavior does not meet the newly selected separate-wallet requirement. Phase 5 must add a WhatsApp-only wallet and top-up crediting path, migrate or close existing WhatsApp reservations safely, and update client/admin balance, ledger, privacy export/erasure, and financial reporting screens before this phase can be considered complete against version 2.2. Preserve the current wallet history; do not silently move or duplicate balances.

The target flow locks the campaign and the client's WhatsApp wallet, checks available WhatsApp funds, creates a reservation and immutable WhatsApp ledger entries, and blocks dispatch when the dedicated balance is insufficient. Unused funds are released after definitive outcomes; unresolved `Unknown` outcomes remain reserved and are flagged for manual review after the configured default deadline. Settlement requires verified billable-delivery evidence and is idempotent by ledger reference.

### Provider billing ownership is an unresolved launch decision

Choosing direct Meta Cloud API as the messaging transport does not determine who pays Meta. There are two distinct commercial models:

1. **Client pays Meta directly:** Meta bills the client's own WABA payment arrangement. OmniCast's WhatsApp wallet can then charge only separately disclosed OmniCast services or margin. Do not also debit Meta pass-through costs from the client's wallet unless the customer agreement explicitly describes that additional charge.
2. **OmniCast pays Meta or Pro Plus:** OmniCast collects client WhatsApp wallet credit, incurs provider costs, records provider payables, and pays the resulting Meta credit-line invoice or Pro Plus invoice. This requires the relevant Meta partner/credit-line arrangement or a confirmed Pro Plus wholesale contract, plus invoice-backed reconciliation.

Meta's Embedded Signup guidance includes optional/required credit-line sharing steps for partner-managed billing; it describes the case where the business pays the solution partner and the partner receives an aggregated Meta invoice. This credit-line workflow is not implemented in the current connector flow. Until OmniCast chooses the payer model and confirms its commercial and accounting treatment, keep production billing/sending gated and do not publish the illustrative selling prices as final.

**Phase 5 operational inputs still required:** No production rate card is included in this implementation. An authorized rates manager must enter and approve provider/Meta costs, FX, fees, markup, and effective dates from current commercial sources. Phase 6 must supply provider-backed billable evidence before delivery-based capture is enabled. Invoice ingestion and reconciliation remain Phase 6 work; production billing stays gated until provider evidence and commercial inputs are validated.

### Phase 6 — Delivery events, settlement, and reconciliation

**Goal:** Tie message and financial outcomes to evidence from the provider.

**Work items:**

- Implement verified webhook/status ingestion only when the provider supplies and supports the contract.
- Persist and deduplicate provider events; correlate provider IDs and prevent stale events from regressing message state.
- Map provider evidence to internal statuses such as `Accepted`, `Sent`, `Delivered`, `Read`, `Failed`, and `Unknown`.
- Settle billable usage using the saved rate snapshot and the confirmed billing evidence.
- Release unused reservations and retain unresolved reservations according to the agreed exception policy.
- Ingest provider invoices or usage records and report reconciliation differences for review.
- Add traceable message, event, usage, ledger, and invoice histories.

**Exit gate:** Delivery and billable records have traceable provider evidence; duplicate events cannot double-settle; invoice variances are visible and never silently written off. If provider callbacks or usage evidence are unavailable, delivery-dependent billing remains blocked.

**Applied — November 12, 2026:** Added durable provider event, billable usage, provider invoice, and invoice reconciliation records, with admin history for invoices, events, usage costs, and open variances. Provider events are correlated to message IDs scoped to their configured provider; event IDs are unique per provider. Status updates advance through accepted, sent, delivered, read, failed, and unknown without allowing lower-ranked stale events to regress a message. Billable events settle from the recipient's saved rate and create a usage record; duplicate event IDs and duplicate provider message usage cannot create another debit. Wallet ledger metadata links the charge to the persisted provider event.

The webhook endpoint requires a constant-time HMAC-SHA256 signature check over the raw request body, is rate limited, and is disabled by default. Its canonical adapter payload supports event ID, message ID, event type, status, occurrence time, and explicit billable evidence. The invoice import command accepts a provider export normalized to this shape:

```json
{
  "invoice": {
    "invoice_number": "PROVIDER-INV-123",
    "period_start": "2026-11-01",
    "period_end": "2026-11-30",
    "currency": "GHS",
    "total_amount": 12.5
  },
  "lines": [
    { "reference": "provider-usage-id-1", "amount": 0.25 }
  ]
}
```

Run it with `php artisan whatsapp:invoices:import PROVIDER_ID FILE_PATH`. The import records provider line items, compares GHS provider charges with the saved expected provider cost, and exposes unmatched lines and variances. A rates manager must record a review note to close each variance; reviewing does not adjust or write off the ledger.

**Provider contract gate remains open:** The current Pro Plus API contract available in this repository documents send and template/connector operations, but not webhook signing, callback fields, delivery semantics, billable flags, usage exports, or invoice schema. Therefore `WHATSAPP_EVENTS_CONTRACT_ENABLED` defaults to `false`, and the callback must stay disabled until Pro Plus supplies its supported contract and a provider-specific adapter/signature arrangement is verified. Non-GHS invoices are retained for review because no approved conversion input is defined. Until the signed callback and invoice feed are validated with the provider, the Phase 6 exit gate and production delivery-based billing remain blocked.

### Phase 7 — Portals, reporting, compliance, and launch readiness

**Goal:** Expose the validated capabilities to administrators and clients and prepare for production operation.

**Work items:**

- Add admin views for providers, connectors, templates, campaigns, messages, pricing, wallet activity, compliance, and operations.
- Add client views for campaign management, approved templates, contacts, assigned senders, messages, wallet usage, reports, and eligible media.
- Add delivery, usage, cost, revenue, margin, provider-health, and reconciliation reports with tenant-safe exports.
- Complete retention, deletion/export, audit, backup/restore, secret rotation, least-privilege, and alerting procedures.
- Run tenant-isolation, load, concurrency, failure-recovery, media-access, and end-to-end billing tests.
- Pilot with approved clients, review operational metrics and reconciliation, and complete a documented go/no-go.
- Keep unsupported inbound, read-status, delivery-status, or template-submission features hidden behind feature flags.

**Exit gate:** All applicable production release checklist items in Section 16 pass; provider and commercial blockers are resolved or the affected features remain disabled.

**Applied — November 12, 2026:** Expanded the tenant WhatsApp workspace with message status history, delivery/usage/cost/margin totals, WhatsApp-tagged wallet balance and ledger activity, and a client-scoped CSV message export. The balance still reads from the shared OmniCast wallet; it must be moved to the dedicated WhatsApp wallet in revised Phase 5. Export queries are tenant-scoped, record an audit entry, stream in bounded batches, and neutralize spreadsheet formula cells. The platform sidebar now links provider, connector, pricing, reconciliation, and operations views. The operations page summarizes provider health, campaign/message outcomes, consent counts, unresolved holds, event/reconciliation exceptions, usage margins, and disabled/enabled launch gates.

The platform operations view exposes campaign and message histories without recipient phone numbers. Client pages expose only that client’s contacts, messages, wallet transactions, and usage. The existing approved privacy access export now includes WhatsApp message snapshots, attempts, provider events, consent, billable usage, invoice reconciliation, and account wallet entries. Approved erasure revokes the contact’s active WhatsApp consent, replaces copied recipient phone/variable data, removes copied attempt payloads, cancels a client’s unfinished WhatsApp campaigns, disables sender assignments, removes client-owned WhatsApp media, and anonymizes campaign names/mappings while preserving financial and provider evidence.

Provider callback, inbound messaging, and provider template submission remain gated or absent. Sending and the provider event contract remain disabled by default. The operations page links operators to the existing privacy request and data-retention approval workflows. WhatsApp request/response/callback payload copies are redacted by a daily job only after a `WhatsApp sensitive payloads` retention period is approved; event IDs, statuses, usage records, and ledger links remain available for reconciliation.

**Phase 7 release work still required:** The code and focused automated checks do not establish production readiness by themselves. Complete tenant-isolation and load/concurrency testing, recovery drills, provider-controlled end-to-end sends and event/invoice reconciliation, media URL retrieval checks, and alert verification. Record and test backup/restore, secret rotation, incident response, and retention schedules with the operational owners. Keep sending disabled until the Phase 6 provider contract gate and the release checklist are satisfied.

**Indicative delivery:** 9–10 weeks with an experienced focused team, subject to provider access, commercial agreements, and the outcomes of Phase 0. Parallel work may change calendar duration but must not bypass phase exit gates.

## 16. Acceptance tests and production release checklist

The following must pass before production launch:

- Connection and template API calls work with correct auth and fail safely with invalid credentials.
- A client cannot read or modify another client’s messages, contacts, wallet, sender, or media.
- Only approved templates and eligible opted-in recipients enter dispatch.
- Rate limits and retries do not create duplicate sending or duplicate settlement.
- Wallet cannot overspend under concurrent jobs; package units are not reduced by WhatsApp.
- Message statuses and billable records have traceable provider evidence.
- Provider invoices and internal usage reconcile; discrepancies are reported rather than silently written off.
- Media URLs are retrievable by the provider while private assets remain protected.
- Queue workers, scheduler, backups, alerting, disaster recovery, and exports are verified.

## 17. Provider dependencies and open decisions

### Client-owned Meta account connection

The client WhatsApp portal supports Facebook Login for Business Embedded Signup. A client administrator completes Meta's signup, and the server exchanges the returned authorization code, verifies access to the selected WABA and phone number through Graph API, subscribes the WABA to app webhooks, stores the token encrypted on that tenant's connector, and synchronizes its templates. Campaign submissions for these connectors use the Meta Cloud API directly; existing Pro Plus connectors continue through the Pro Plus adapter. Client ownership is enforced on the connector and the corresponding client assignment.

Platform setup remains required before enabling `META_WHATSAPP_ONBOARDING_ENABLED`: configure a Meta app and Embedded Signup login configuration, supply the app ID and secret, permitted Graph API version, and optional solution ID; obtain Meta app review/advanced access for `business_management`, `whatsapp_business_management`, and `whatsapp_business_messaging`; configure the Meta Webhooks product to call `/webhooks/whatsapp/meta` with the matching verify token and subscribe to WhatsApp message status events; and serve the application over public HTTPS. The Meta webhook uses `X-Hub-Signature-256` verification with the app secret. Do not enable real sends until controlled-number delivery, token expiry/reconnection, and billing evidence have been validated.

The initial template import reads the first page (up to 100 templates); pagination and an in-product token renewal/reconnect flow remain follow-up work. Meta can revoke access or tokens can expire, so operators must be able to ask a client admin to reconnect. Meta app review, production credentials, external callback delivery, and controlled send acceptance are deployment gates rather than completed repository work.

Before production launch, obtain from Pro Plus:

- Actual API base URL
- Sandbox credentials
- Complete success/failure response schemas
- Connector ownership model
- Max recipients per request and TPS
- Webhook and delivery/read callback contract
- Message ID semantics
- Idempotency and reconciliation support
- Provider wholesale fees and invoice structure
- Inbound messaging support
- Template submission API
- Media limits and retrieval timing

The included 24-page API guide documents outbound template sending and template retrieval, but it does not fully establish these missing contracts.

Meta's Embedded Signup documentation describes the optional partner credit-line flow: participating businesses can request access to the partner's Meta credit line, after which the partner receives an aggregated Meta invoice and collects payment from the business. This differs from client-owned WABAs that retain their own direct Meta billing arrangement. OmniCast must confirm eligibility, legal entity, currency, and payment terms before selecting this flow. The current application only embeds signup and calls account verification, template retrieval, and WABA webhook subscription; it does not attach a credit line or complete the other partner provisioning steps.

## 18. References

- Pro Plus WhatsApp API Reference Guide (uploaded, 24 pages): authentication (p. 3), connector (p. 3), template retrieval (pp. 4–7), outbound template formats (pp. 8–16), custom recipient payloads and OTP (pp. 17–24)
- Meta official WhatsApp Business Platform pricing: https://whatsappbusiness.com/products/platform-pricing/
- Meta WhatsApp Business Platform Embedded Signup and credit-line setup: https://www.postman.com/meta/whatsapp-business-platform/documentation/du6gzjv/embedded-signup
- OmniCast | WhatsApp Integration Technical Specification | 2026


## 19. Phase completion and remaining work register

“Implemented” below means application code and local automated coverage exist. It does not mean a phase exit gate has passed in production. Phases 1–7 have implementation work recorded in this document; none should be marked production-ready until its external, deployment, and acceptance gates are closed.

| Area | Implemented in repository | Remaining before production |
| --- | --- | --- |
| Phase 0 — provider discovery | Configuration keys, adapter boundary, documented known API surface and explicit feature gates | Pro Plus sandbox credentials/host and signed contract; confirm status mapping, limits, idempotency, delivery/billing callbacks, media limits, invoices, fees, and supported endpoints. |
| Phase 1 — foundation | Provider interface/adapter, models, migrations, permissions, tenant checks, redaction, queue/Horizon configuration, client-owned Meta connector token encryption | Apply migrations and seed roles in each environment; complete Meta app review and configuration; review production secrets, least privilege, worker/scheduler deployment and existing-channel regression behavior. |
| Phase 2 — connectors/templates/media | Connector and template sync, approval-safe catalog, client assignment, validators, private signed media, Meta Embedded Signup and direct Cloud API adapter | Validate Meta Embedded Signup in the approved app and Graph API version; implement template pagination and token renewal/reconnect UX; revalidate Pro Plus API semantics; run public-origin media retrieval test and confirm provider limits. |
| Phase 3 — consent/authoring | Consent evidence, revocation, suppression checks, campaign drafts, recipient snapshots, variable validation and estimate | Confirm legal wording/retention with the data controller; add/confirm clone and test-send UX if required; verify import/list/segment flows at target scale. |
| Phase 4 — dispatch | Queue jobs, scheduled dispatch, reservations, rate-limit hooks, attempt audit, retry/unknown handling and controls | Sandbox/controlled-number sends; set contractual TPS limits; test concurrency, worker loss, provider timeouts and recovery; configure alert delivery. |
| Phase 5 — pricing/wallet | Effective-dated approved rate inputs, saved snapshots, atomic reservation and settlement guards; current implementation uses shared OmniCast wallet | Implement dedicated WhatsApp wallet/top-ups/ledger and migrate screens/reports safely; choose client-direct Meta billing or OmniCast/Pro Plus payable model; approve costs, FX, markup/tax/rounding; verify Paystack, concurrency, and reconciliation. |
| Phase 6 — delivery/reconciliation | Signature-gated Pro Plus and Meta webhook paths, deduplication, status progression, usage settlement, invoice import and variance review | Verify signed callbacks and invoices end to end for each live provider; obtain Pro Plus callback/invoice contract; define non-GHS FX and exception closure. |
| Phase 7 — portals/compliance/operations | Client usage/history/export; admin pricing, reconciliation and operations pages; privacy export/erasure and approval-gated payload redaction | Validate the complete menu matrix and permissions; run backup/restore, secret rotation, incident and retention drills; verify alerts, audit monitoring, public media, production exports and go/no-go. |

### Verified in the current workspace (12 November 2026)

- Pint passed.
- Focused WhatsApp and privacy suite passed: 45 tests and 253 assertions across Phase 2–7, provider adapter, and privacy compliance coverage.
- TypeScript check passed (`npm run types:check`).
- Vite production build passed (2,611 modules transformed). The build reported that optional `fontaine` is not installed; this did not fail the build.
- `git diff --check` passed.

### Remaining launch blockers and their impact

1. **Billing owner and dedicated wallet:** Direct Meta Cloud API transport does not say whether Meta bills each client's WABA or OmniCast pays through a shared credit line. Choose and document that commercial model, complete the separate WhatsApp wallet work, and ensure client wallet charges cannot duplicate direct Meta charges.
2. **Provider contract and sandbox validation:** Without documented delivery/billable event semantics, idempotency, supported throughput, and invoice mapping, real sending and delivery-based charging can duplicate work or charge without evidence. Keep `WHATSAPP_ENABLED=false` and `WHATSAPP_EVENTS_CONTRACT_ENABLED=false`.
3. **Commercial pricing inputs:** The implementation has no production-approved rate card. Quotes, margins, and reserves are not authoritative until current Meta rates, any Pro Plus fees, FX, markup, taxes, and rounding are approved and entered.
4. **Deployment readiness:** Migrations, role grants, queues, scheduler, secrets, backup/restore, alert routing, public HTTPS media delivery, and retention approvals must be configured and verified in each environment.
5. **Operational/scale acceptance:** Tests currently prove selected deterministic paths, not production concurrency, load, outage recovery, real provider integration, or recovery point/time objectives. Run the Section 16 checklist and record evidence before the pilot.
6. **Product scope completeness:** The specification’s full admin/client menu matrices are broader than the WhatsApp-specific screens currently evidenced. Confirm which entries are satisfied by shared platform pages; implement or explicitly defer missing items such as dedicated WhatsApp campaign/template/message management screens, clone/test-send UX, client billing/invoice views, and richer reporting filters before representing the entire matrix as delivered.
7. **Compliance governance:** Code supports consent evidence, export/erasure, and approved payload retention, but the data controller must approve consent wording, purposes, lawful retention periods, legal holds, Ghana Data Protection Act review, and evidence of operational handling.
---

This specification should be treated as a working blueprint for design and implementation. Final production decisions must be validated with the provider and aligned to the billing, compliance, and operational requirements of the OmniCast platform.
