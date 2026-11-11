# OmniCast WhatsApp Integration

## Complete Technical & Functional Specification

Version 2.0 | 11 November 2026

Laravel 12 • Pro Plus WhatsApp API • Unified Wallet • Existing Media Storage

This document defines the architecture, implementation plan, operational controls, pricing model, and launch gates for integrating WhatsApp into the OmniCast platform.

## Executive decisions

- Keep WhatsApp inside the existing OmniCast Laravel application and reuse existing users, contacts, permissions, payments, media library, and infrastructure.
- Use one unified OmniCast wallet for pay-as-you-go usage across SMS, Voice, Email, and WhatsApp. Existing package-specific units remain separate and are consumed first according to current rules.
- Do not require clients to purchase separate WhatsApp bundles. Wallet top-ups may use permitted denominations; WhatsApp is charged by delivered message category and recipient market.
- Retain the current Laravel storage model. Add WhatsApp media validation and provider-retrievable HTTPS URLs without exposing private client assets.
- Treat delivery tracking, webhook support, API throughput, provider fees, and billing evidence as dependencies that require confirmation from Pro Plus before production release.

## 1. Scope and operating principles

This scope includes provider connections, WABA and sender assignment, approved template synchronization, campaign creation, segmentation, opt-in evidence, queue-based sending, message logs, delivery events, reporting, wallet funding, rate-card management, markup, reconciliation, security, and operations.

Phase 1 supports outbound approved-template messaging documented by Pro Plus. Inbound chat and template-submission capabilities remain conditional on additional provider APIs and clear compliance controls.

## 2. Architecture and deployment

Browser/Admin/Client Portal → Laravel application and channel services → Redis/Horizon workers → Pro Plus provider adapter → WhatsApp Business Platform.

MySQL stores campaign state, templates, message events, consent records, and immutable wallet entries. Existing Paystack funding, notifications, scheduler, and storage are reused.

| Layer | Implementation |
| --- | --- |
| Backend | Laravel 12, PHP, existing OmniCast authentication and RBAC |
| Database | Existing MySQL; WhatsApp tables namespaced as `whatsapp_*` |
| Async processing | Redis + Horizon; dedicated priority queues and per-provider throttles |
| Integration | Laravel HTTP client behind a `WhatsAppProvider` interface |
| Storage | Existing Laravel disk/media library with controlled HTTPS media delivery |
| Operations | Existing deployment, cron/scheduler, logging, alerts, and backups |

## 3. Provider API contract

The Pro Plus integration guide uses HTTP Basic authentication with the Client ID as the username and the API key as the password. The base URL is provider-supplied.

Documented endpoints include:

- `GET /api/whatsapp/v1/Connector/GetAll`
- `GET /api/whatsapp/v1/Template/Get?MetaTemplateId=...`
- `GET /api/whatsapp/v1/Template/GetAll`
- `POST /api/whatsapp/v1/Send`

`Template/GetAll` accepts `WABAID` and optional `offset`, `limit` (maximum 25), and `templateStatus` values:

- `0` draft
- `1` pending
- `2` rejected
- `3` approved

The guide illustrates GET requests using multipart form fields; the final request encoding must be confirmed in a sandbox. Send payloads use `SenderNumber`, `MetaTemplateId`, and `BulkPayload` or `CustomPayload`. Preserve the provider spelling `RecieverNumber` exactly.

Supported outbound variations documented in the guide include:

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
| `whatsapp_providers` | `id`, `name`, `driver`, `base_url`, `status` |
| `whatsapp_provider_credentials` | `provider_id`, encrypted credentials, `rotated_at` |
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
| `omnicast_wallets` | `client_id`, `currency`, `status`; shared across channels |
| `omnicast_wallet_ledger` | `wallet_id`, `channel`, `reference`, `debit_credit`, `amount`, `posted_at` |
| `omnicast_wallet_reservations` | `wallet_id`, `message_id`, `amount`, `status` |
| `whatsapp_usage_records` | `message_id`, `country`, `category`, `cost`, `selling_price`, `settlement_state` |
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

## 10. Unified wallet, package units, and usage

One client wallet holds monetary credit in GHS and can fund pay-as-you-go SMS, Voice, Email, and WhatsApp. Existing package-specific balances remain independent and do not get converted or erased.

For a channel with eligible package units, consume package units first, then use wallet credit if enabled and approved. WhatsApp uses wallet money directly unless a future WhatsApp promotional entitlement is explicitly configured.

Separate WhatsApp bundle purchase is not required.

Example:

- Client owns 55,000 SMS package units and GHS 500 wallet credit
- SMS sends consume eligible SMS units first
- WhatsApp deliveries debit wallet credit
- The 55,000 SMS units remain unchanged by WhatsApp activity
- Voice usage follows existing billing rules and wallet fallback only when configured

Wallet accounting flow:

`Estimate charge → Reserve funds atomically → Dispatch → Obtain billable delivery evidence → Settle charge at saved rate snapshot → Release unspent reservation → Reconcile provider invoice`

Unknown outcomes retain reservations subject to documented exception deadlines. Top-ups use the existing Paystack integration. Financial adjustments are ledger entries requiring audit trails and permission.

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

## 15. Implementation roadmap and gates

| Phase | Deliverables / exit gate |
| --- | --- |
| 1 — Foundation | WhatsApp module, migrations, roles, encrypted settings, tested Pro Plus authentication and connector retrieval |
| 2 — Connectors & Templates | WABA assignment, template sync, statuses, previews, validated template variable schemas |
| 3 — Contacts & Campaigns | Consent, imports, groups, campaign wizard, snapshots, schedules, and test sends |
| 4 — Dispatch Engine | Queues, throttling, idempotency strategy, retries, unknown outcomes, pause/resume |
| 5 — Pricing & Unified Wallet | Meta rate import, markup, FX, provider fees, shared wallet reservations, Paystack top-ups, atomic ledger |
| 6 — Status & Reconciliation | Provider callback usage contract, event ingestion, delivered-message settlement, financial reports |
| 7 — QA & Launch | Tenant security, load tests, failure recovery, monitoring, pilot clients, go/no-go checklist |

Indicative delivery: 9–10 weeks with an experienced focused team, dependent on provider documentation and commercial agreements. Feature flags should hide unsupported delivery/read or two-way features until they are validated.

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

## 18. References

- Pro Plus WhatsApp API Reference Guide (uploaded, 24 pages): authentication (p. 3), connector (p. 3), template retrieval (pp. 4–7), outbound template formats (pp. 8–16), custom recipient payloads and OTP (pp. 17–24)
- Meta official WhatsApp Business Platform pricing: https://whatsappbusiness.com/products/platform-pricing/
- OmniCast | WhatsApp Integration Technical Specification | 2026

---

This specification should be treated as a working blueprint for design and implementation. Final production decisions must be validated with the provider and aligned to the billing, compliance, and operational requirements of the OmniCast platform.
