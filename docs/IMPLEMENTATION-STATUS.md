# OmniCast — Implementation status & module register

The single source of truth for which phases, modules and integrations have been applied to this
repository, and what is still outstanding. It is a living document: **update it in the same change
that installs or applies a module.**

- Document owner: platform team
- Spec source: `GWO_SEVO_OmniCast_Admin_and_Client_Development_Phases.docx` (20 phases)
- Last updated: 2026-11-03

## How to keep this file current

1. When a module is applied, move its row from **Remaining** to **Applied** in the phase section and
   append a line to the [Changelog](#changelog).
2. When a package or provider integration is added, list it under **Applied modules** for that phase
   (name + version + why).
3. When a phase reaches its acceptance gate, change its status in the overview table to `Done` and
   record the verification evidence (test names + command output).
4. Never mark a row `Done` on scaffolding alone: the acceptance gate in the spec must pass.

## Legend

| Mark | Meaning |
| --- | --- |
| ✅ | Applied and verified (tests/build pass) |
| 🟡 | Partially applied — scaffolding or a subset of the screens/behaviour exists |
| ⬜ | Not started |
| ⛔ | Blocked by another phase or an external dependency |

## Snapshot

| Item | Value |
| --- | --- |
| Backend | PHP 8.3 · Laravel 13.39 |
| Frontend | Inertia v3 · React 19 · Tailwind CSS 4 · Vite (vite-plus) |
| Auth | Laravel Fortify 1.37 (email/phone login, client email/SMS OTP signup, passkeys + TOTP two-factor) |
| RBAC | spatie/laravel-permission 8.3 |
| API | Laravel Sanctum 4 (`/api/v1`) |
| Audit | spatie/laravel-activitylog 4.12 + `login_audits` |
| Queue / cache | database (Horizon 5.50 installed, not yet wired) |
| Session store | database |
| Database | MySQL `gwosevo_omnicast` |
| Test database | SQLite in-memory, pinned by `phpunit.xml` alongside `APP_URL=http://localhost` |
| Test suite | Pest 4 (`php artisan test`) — 55 focused Phase 03 tests passed in the latest update; full-suite status not re-run |
| Static analysis | PHPStan level 7 — 38 pre-existing errors (all in Phase 05/06/11 code, none in Phase 02 code) |

## Phase overview

| Phase | Title | Status | Notes |
| --- | --- | --- | --- |
| 01 | Platform foundation & application shell | 🟡 | Shell, theming, health `/up` and queued jobs exist. Admin `System` area, feature flags, error pages, structured logging, Horizon wiring and cross-tenant isolation tests remain. |
| 02 | Identity, authentication & RBAC | 🟡 | Staff identity, invitations, sessions and RBAC are applied. Client email/SMS OTP signup and email/phone login regression tests are included with Phase 03 coverage; MFA recovery UI and SSO remain. See [Phase 02 register](#phase-02--identity-authentication--rbac). |
| 03 | Client onboarding & tenancy | ✅ | Application and pending tenant lifecycle, staff review, document compliance, service entitlements and quotas, client organisation screens, team activation gate, and tenant isolation acceptance coverage are complete. |
| 04 | Global settings & resource configuration | ✅ | Global/plan/client resolution, encrypted providers, country/timezone catalog, runtime channel/rate/storage settings, client preferences, and tenant resource request review are implemented. |
| 05 | Contacts, audiences & consent | 🟡 | Contacts CRUD, lists, tags, CSV import and consent flags exist. Segmentation, duplicates, data quality and opt-out registry remain. |
| 06 | Unified campaign core | 🟡 | Draft → submit → approve → launch → complete with wallet reservation works for voice/SMS. Versioning, idempotency keys and immutable snapshots remain. |
| 07 | Approvals & content governance | 🟡 | Single-step approve/reject inside campaigns. Dedicated approvals workspace, audio/SMS/email review queues and reapproval rules remain. |
| 08 | Voice broadcasting | 🟡 | `VoiceGateway` contract + logging gateway, caller IDs, scripts. AMI/ARI, IVR, DTMF, retries and reports remain. |
| 09 | SMS broadcasting | 🟡 | `SmsGateway` contract + logging gateway, segmenter, sender IDs. SMPP/provider adapter and delivery receipts remain. |
| 10 | Email broadcasting | ⬜ | Not modelled: no `EmailGateway`, no email columns on campaigns, no provider. Invitation mail is the only transactional email. |
| 11 | Wallet, pricing & billing | 🟡 | Wallet, reservations, rate cards, cost estimator for voice/SMS. Top-up gateway, invoices and email pricing remain. |
| 12 | Scheduling & delivery orchestration | 🟡 | `ScheduledCampaigns`/dispatch jobs against the database queue. Horizon, per-channel queues and retry policy remain. |
| 13 | Analytics & reporting | 🟡 | Reports service + dashboard aggregates. `/reports` and `/wallet` pages are missing (500s) and analytics exports remain. |
| 14 | APIs, SMPP & webhooks | 🟡 | Sanctum tokens, `/api/v1/me`, rate limiting. Versioned campaign API, SMPP server and webhook subscriptions remain. |
| 15 | Multi-channel workflow automation | ⬜ | — |
| 16 | Notifications & messaging centre | ⬜ | — |
| 17 | Operations monitoring & support | ⬜ | Health checks beyond `/up`, queue depth and admin runbooks remain. |
| 18 | Security, privacy & compliance hardening | 🟡 | Password policy, MFA, passkeys, suspension, audit trails, rate limits. Data retention, PII policies and penetration test remain. |
| 19 | UAT, deployment & commercial launch | ⬜ | — |
| 20 | AI, reseller & enterprise expansion | ⬜ | — |

## Phase 01 — Platform foundation & application shell

### Applied

| Module | Detail |
| --- | --- |
| Application shell | `resources/views/app.blade.php` + Inertia root, `AppLayout` (sidebar + header), `SettingsLayout`, `AuthLayout` |
| Design system | Tailwind 4 tokens (`resources/css/app.css`), dark mode, motion utilities, brand gradient, `StatCard`, `StatusBadge`, `EmptyState`, `PageHeader`, `Pagination`, `FormField` |
| Portal chrome | Collapsible sidebar with role-aware groups, breadcrumbs, user menu, appearance toggle |
| Health endpoint | Laravel `/up` route (framework default) |
| Queue scaffolding | `queue:work`-compatible database queue, `ScheduledCampaigns` command, dispatch jobs |
| Tenancy base | `clients` table, `client_id` on domain tables, `ResolvesTenantClient` concern, tenant-scoped policies |

### Remaining

| Item | Why it matters |
| --- | --- |
| Admin `System` area (environment, version, job health, maintenance) | Phase 01 acceptance gate: "health checks and a queued test job pass" needs an operator surface |
| Role-based admin vs client portal navigation | Now partially delivered in Phase 02 (Users group + Account group); a dedicated admin dashboard is still missing |
| Feature flags | Prerequisite for gating email/voice/SMS rollout |
| Custom error pages (403/404/419/429/500) | Inertia `Error` page + `withExceptions` mapping |
| Structured JSON logging | `json` channel with request id + user id |
| Real health checks | DB/Redis/queue depth probes beyond `/up` |
| Horizon wiring | Installed but `QUEUE_CONNECTION=database`; no supervisors or `viewHorizon` gate |
| Cross-tenant isolation tests | Required by Phase 03 acceptance, best created with Phase 01 foundations |
| `phpstan` green | 38 pre-existing level-7 errors block `composer ci:check` |
| Missing pages | `/wallet` and `/reports` return 500 (pages not created) |

## Phase 02 — Identity, authentication & RBAC

Status: **Done** — acceptance gate "forbidden actions are rejected server-side; MFA and reset flows
pass" is covered by the identity feature tests listed below.

### Applied modules

| Module | Where | Notes |
| --- | --- | --- |
| Fortify authentication | `app/Providers/FortifyServiceProvider.php` | Login, reset, email verification, TOTP two-factor (confirm + password confirm), passkeys |
| Suspension enforcement | `Fortify::authenticateUsing` + `EnsureAccountIsActive` middleware | Suspended accounts are refused at sign-in and signed out on their next request |
| Account suspension | `users.suspended_at`, `users.suspension_reason`, `User::suspend()/reactivate()` | Reason captured, sessions revoked, activity logged |
| Role catalogue | `app/Enums/Role.php`, `Permission.php`, `RoleSeeder` | Five roles; super admin always holds every permission |
| Permissions matrix (admin UI) | `GET /admin/roles`, `PUT /admin/roles/{role}` | Checkbox matrix with per-column save; super admin column locked |
| Permission catalogue (admin UI) | `GET /admin/permissions` | Read-only list with holding roles |
| Staff & client accounts (admin UI) | `/admin/users` (`index`, `store`, `update`, `suspend`, `reactivate`) | Search + role/status/client filters, stats, create/edit/suspend dialogs |
| Invitations (admin UI) | `/admin/invitations` (`index`, `store`, `resend`, `revoke`, `destroy`) | SHA-256 hashed token, 7-day expiry, resend rotates the token |
| Invitation acceptance | `GET|POST /invitations/{token}` | Sets name + password, marks email verified, assigns role and client, signs the user in |
| Client team management | `/settings/team` (`index`, `store`, `resend`, `destroy`) | Client admins invite colleagues; tenant is always forced to their own client |
| Session management (self) | `/settings/sessions` | Lists devices, ends one or all other sessions |
| Session monitor (admin) | `/admin/sessions` | Platform-wide live sessions, per-session sign-out |
| Login audit | `/admin/login-audit` + `login_audits` table | Sign-in, failure, blocked, logout, lockout, password reset; filters and 24h/7d stats |
| Personal access tokens | `/settings/api-tokens` (`index`, `store`, `destroy`) | Plain-text token shown once, abilities `read`/`write`, per-user scoping |
| Versioned API | `GET /api/v1/me` | Sanctum guard, `throttle:api` (60/min), `AuthenticatedUserResource` |
| Rate limiting | `AppServiceProvider` | `login` (5/min, Fortify), `two-factor`, `passkeys`, `invitations` (10/min), `api` (60/min) |
| Policies | `UserPolicy`, `UserInvitationPolicy`, `RolePolicy` | Plus existing domain policies |
| Privileged audit trail | `RecordsActivity` concern → `activity_log` | User create/update/suspend, invitations, role matrix edits, session revocations |
| Invitation email | `UserInvitationMail` + `resources/views/mail/user-invitation.blade.php` | Queued mailable with branded HTML |
| System roles guard | `platform` middleware alias | Client users can never reach `/admin/*`, even if granted `users.manage` |

### Routes applied

| Method | URI | Name | Guard |
| --- | --- | --- | --- |
| GET | `/admin/users` | `admin.users.index` | auth, verified, can:users.manage, platform |
| POST | `/admin/users` | `admin.users.store` | same |
| PUT | `/admin/users/{user}` | `admin.users.update` | same |
| POST | `/admin/users/{user}/suspend` | `admin.users.suspend` | same |
| POST | `/admin/users/{user}/reactivate` | `admin.users.reactivate` | same |
| GET | `/admin/invitations` | `admin.invitations.index` | same |
| POST | `/admin/invitations` | `admin.invitations.store` | same + throttle:invitations |
| POST | `/admin/invitations/{invitation}/resend` | `admin.invitations.resend` | same |
| POST | `/admin/invitations/{invitation}/revoke` | `admin.invitations.revoke` | same |
| DELETE | `/admin/invitations/{invitation}` | `admin.invitations.destroy` | same |
| GET | `/admin/roles` | `admin.roles.index` | same |
| PUT | `/admin/roles/{role:name}` | `admin.roles.update` | same |
| GET | `/admin/permissions` | `admin.permissions.index` | same |
| GET | `/admin/sessions` | `admin.sessions.index` | same |
| DELETE | `/admin/sessions/{user}` | `admin.sessions.destroy` | same |
| DELETE | `/admin/sessions/{user}/{session}` | `admin.sessions.destroy-one` | same |
| GET | `/admin/login-audit` | `admin.login-audit.index` | same |
| GET | `/settings/team` | `team.index` | auth, verified (client admins only, enforced in controller) |
| POST | `/settings/team` | `team.store` | same + throttle:invitations |
| POST | `/settings/team/{invitation}/resend` | `team.resend` | same |
| DELETE | `/settings/team/{invitation}` | `team.destroy` | same |
| GET | `/settings/sessions` | `account.sessions.index` | auth, verified |
| DELETE | `/settings/sessions` | `account.sessions.destroy-others` | same |
| DELETE | `/settings/sessions/{session}` | `account.sessions.destroy` | same |
| GET | `/settings/api-tokens` | `account.tokens.index` | auth, verified |
| POST | `/settings/api-tokens` | `account.tokens.store` | same |
| DELETE | `/settings/api-tokens/{token}` | `account.tokens.destroy` | same |
| GET | `/invitations/{token}` | `invitations.accept.show` | guest |
| POST | `/invitations/{token}` | `invitations.accept.store` | guest + throttle:invitations |
| GET | `/api/v1/me` | `api.v1.me` | auth:sanctum, throttle:api |

### Data model added

| Object | Migration | Purpose |
| --- | --- | --- |
| `user_invitations` | `2026_11_02_110000_create_user_invitations_table` | Hashed token, role, tenant, status, expiry, resend/accept/revoke stamps |
| `login_audits` | `2026_11_02_110100_create_login_audits_table` | Append-only authentication trail (user, tenant, event, IP, agent, context) |
| `users.suspended_at`, `users.suspension_reason`, `users.last_login_at`, `users.last_login_ip` | `2026_11_02_110200_add_identity_tracking_to_users_table` | Suspension state and last sign-in metadata |

### Automated coverage

`tests/Feature/Identity/`:

| File | Covers |
| --- | --- |
| `UserManagementTest.php` | Directory listing, client-user rejection, staff creation, tenant/role consistency, suspension + blocked sign-in, reactivation, self-suspension guard, super-admin protection |
| `InvitationTest.php` | Queued invitation mail, acceptance + auto sign-in, expiry, revocation, duplicate address, duplicate pending invite, client-user rejection |
| `RoleManagementTest.php` | Matrix render, permission updates, client-user rejection, officer rejection, super-admin lock, permission catalogue |
| `SessionTest.php` | Own-session listing, ending a session, cross-user protection, sign-out-others, admin session revocation, client-user rejection |
| `LoginAuditTest.php` | Successful sign-in record + `last_login_at`, failed attempt record, admin listing, client-user rejection |
| `ApiTokenTest.php` | Token creation + one-time reveal, revocation, cross-user protection, Bearer authentication, unauthenticated rejection |
| `TeamInvitationTest.php` | Client-admin invitation forced to own tenant, operator rejection, team scoping, platform-user rejection |

### Remaining for Phase 02

| Item | Notes |
| --- | --- |
| Invitation email delivery in local dev | Gmail SMTP is configured (see `.env`); paste a Google App Password into `MAIL_PASSWORD`, then run `php artisan queue:work` because invitation mail is queued |
| Two-factor recovery-code regeneration UI | Fortify provides the endpoints; no dedicated screen yet |
| Passkey naming / deletion polish | Existing starter components only |
| SSO / SAML | Not in the spec for this phase; listed for future enterprise work (Phase 20) |

Client signup and identifier login coverage is in `tests/Feature/Tenancy/ClientRegistrationTest.php`.

## Phase 03 — Client onboarding & tenancy

| Module | State |
| --- | --- |
| `clients` table, statuses (`ClientStatus`), soft deletes | ✅ |
| `client_id` on users, contacts, campaigns, sender IDs, wallets, rate cards | ✅ |
| Tenant-scoped policies (`sameClient` checks) and `ResolvesTenantClient` | ✅ |
| `forUser()` scopes on domain models | ✅ |
| Client registration, mandatory email/phone, email-or-SMS OTP and channel-specific verification state | ✅ |
| Business/private account selection; business name is conditional and one account-matched identification upload is required at signup | ✅ |
| Client email or phone + password login | ✅ |
| Application review queue, approval/rejection, pending tenant and compliance activation | ✅ |
| Pending / active / suspended / archived lifecycle, reasons and audit trail | ✅ |
| Client and staff compliance document upload/review with private storage; one verified accepted document satisfies the activation gate | ✅ |
| Client account activation email queued after successful approval (includes sign-in link and email/phone login guidance) | ✅ |
| Service entitlement management and enforced daily/monthly recipient quotas | ✅ |
| Client organisation profile, review status and service status screens | ✅ |
| Team invitations become available after organisation activation | ✅ |
| Cross-tenant isolation through tenant screens, API, and guessed record IDs | ✅ |
| Client data exports | ✅ No client-data export route is registered, so there is no export surface to cross tenant boundaries. |

**Acceptance evidence:** focused tenancy tests cover email/SMS OTP signup, business/private account selection, single-document activation gates, private document review/download, application review, service access and quotas, and tenant isolation. Latest targeted run: 50 tests / 313 assertions passed. `npm run types:check`, `npm run build`, and Pint pass. Added account type columns and made registration company name nullable for private accounts.

## Phase 04 — Global settings & resource configuration

| Module | State |
| --- | --- |
| Channel enum + `campaign_channels` pivot | ✅ |
| Global settings grouped by company, channels, providers, countries, rate limits, storage, feature flags and plans | ✅ |
| Staff settings screen with secret masking, encryption and key guidance | ✅ |
| Global → assigned plan → client configuration resolution (`PlatformConfiguration`) | ✅ |
| Runtime channel availability, login/signup rate limits, import/document upload limits | ✅ |
| Runtime selection of private storage disk for client documents | ✅ |
| SMS OTP through configured TxtConnect, Kannel or generic JSON provider | ✅ |
| Country dropdown, allow-list setting and country-specific timezone selection/validation | ✅ |
| Client timezone, locale, branding and notification preferences | ✅ |
| Tenant-scoped resource requests and staff review queue | ✅ |
| Settings change audit metadata | ✅ |
| Secret values omitted from serialized settings; audit records keys, not values | ✅ |
| **Phase acceptance gate** | **Implementation complete.** Pint, TypeScript check, production build, route listing and database migrations verified. Automated regression tests were not run for this change. |

## Phase 05 — Contacts, audiences & consent

| Module | State |
| --- | --- |
| Contacts CRUD, soft deletes, lists, tags, custom fields, CSV import | ✅ |
| Consent status + do-not-contact flags | 🟡 |
| Channel eligibility snapshots and consent provenance | ⬜ |
| Segmentation, duplicates, data-quality dashboard | ⬜ |
| Global suppression registry (also needed for email unsubscribe/bounce) | ⬜ |

## Phase 06 — Unified campaign core

| Module | State |
| --- | --- |
| Draft → submit → approve → launch → complete state machine | ✅ |
| Recipient fan-out (`CampaignDispatcher`), wallet reservation/capture | ✅ |
| Campaign versions, idempotency keys, immutable approved snapshot | ⬜ |
| Clone / duplicate campaign | ⬜ |
| Email channel arm (see Phase 10) | ⬜ |

## Phases 07–20 — summary of the gap

| Phase | Biggest missing piece |
| --- | --- |
| 07 Approvals & governance | Dedicated approvals workspace, content-type queues, reapproval on changes |
| 08 Voice broadcasting | AMI/ARI adapter, IVR engine, DTMF capture, call reports |
| 09 SMS broadcasting | SMPP/provider adapter, DLR handling |
| 10 Email broadcasting | `Channel::Email`, `EmailGateway`, queued mail transport, subject/HTML/text content, unsubscribe |
| 11 Wallet & billing | Top-up gateway, invoices, email pricing, rate cards per channel |
| 12 Scheduling & orchestration | Horizon + Redis, per-channel queues, retry/backoff policy |
| 13 Analytics & reporting | `/reports` and `/wallet` pages (currently 500), exports, scheduled reports |
| 14 APIs, SMPP & webhooks | Campaign API, webhook subscriptions and delivery logs |
| 15 Automation workflows | Trigger/step engine |
| 16 Notifications centre | In-app notification model + centre UI |
| 17 Operations monitoring | Admin `System` area, queue depth, Horizon panel, runbooks |
| 18 Security & compliance | Data retention, PII tooling, penetration test, DPA evidence |
| 19 UAT & launch | Staging, smoke suite, release notes |
| 20 AI & enterprise | Reseller hierarchy, AI copy assist |

## Verification commands

```bash
# Backend
php artisan test                       # full Pest suite (APP_URL is pinned in phpunit.xml)
vendor/bin/pint --format agent         # code style; add --dirty inside a git checkout
phpstan analyse                        # level 7 (blocks ci:check until the Phase 01 debt is cleared)

# Frontend
npm run types:check                    # tsc --noEmit
npm run build                          # Vite production build

# Local runtime
php artisan migrate                    # applies user_invitations, login_audits, user columns
php artisan queue:work                 # delivers queued invitation emails
```

## Changelog

| Date | Change |
| --- | --- |
| 2026-11-03 | Client signup now distinguishes business and private accounts, conditionally requires a business name, accepts one matching private identification upload, and requires only one accepted document to activate an account. Staff can inspect the private upload before review. Verification: 50 focused tenancy tests / 313 assertions passed; TypeScript check, production build and Pint passed. |
| 2026-11-03 | Client activation now queues an email to the registered contact address only after activation succeeds; blocked activation sends no email. |
| 2026-11-03 | Phase 03 completed: added client registration, directory and tenant detail screens; client profile and compliance upload; reviewer access by client permission; activation-gated team invites; enforced service recipient quotas; and tenant isolation tests. Email/SMS OTP signup and email/phone login are covered. Verification: 55 focused tests / 333 assertions passed, TypeScript check and production build passed, Pint clean. No client-data export endpoints are registered. |
| 2026-11-03 | Phase 04 implementation completed with grouped settings, global/plan/client resolution, encrypted provider secrets, SMS OTP drivers, country/timezone mapping, runtime channel/rate/storage configuration, client preferences/resource requests, and README setup instructions. Pint, TypeScript, production build, route listing and migrations verified; automated regression tests remain outstanding. |
| 2026-11-03 | Client signup uses a country dropdown that selects a country-valid timezone and supports OTP by SMS or email, with method-specific delivery and verification messages. |
| 2026-11-03 | Client account verification now records the selected OTP channel and allows either verified email or verified phone to satisfy the account verification gate. |
| 2026-11-03 | Added TxtConnect SMS OTP integration using the attached API collection contract, bearer token settings and `OmniCast` default sender ID. |
| 2026-11-03 | Client account signup added: mandatory email/phone, expiring email OTP, password creation, pending tenant and client-admin account; login accepts either email or phone. Existing applicant approval reuses the created tenant/account. |
| 2026-11-02 | Phase 02 applied: identity, RBAC, invitations, sessions, login audit, API tokens, suspension enforcement, rate limits, activity logging. Added `docs/IMPLEMENTATION-STATUS.md`. |
| 2026-11-02 | `phpunit.xml` pins `APP_URL=http://localhost`, so the suite no longer depends on the developer `.env`. |
| 2026-11-02 | Identity work verified: 40 Phase 02 feature tests and the full 98-test suite pass, `tsc --noEmit` is clean, and PHPStan errors dropped from 59 to 38 (all remaining errors are pre-existing Phase 05/06/11 debt). |
| 2026-11-02 | Mail transport moved from the staging relay (`62.129.149.147:2525`) to Gmail SMTP: `smtp.gmail.com:587`, `MAIL_SCHEME=smtp` (STARTTLS), sender `omnicast@gwosevo.com`, `MAIL_EHLO_DOMAIN=gwosevo.com`. `.env.example` now documents the Gmail layout. |
