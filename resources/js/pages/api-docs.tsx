import DocShell from '@/components/docs/doc-shell';
import {
    Bullets,
    Callout,
    CodeBlock,
    Endpoint,
    FieldTable,
    Mono,
    Para,
    Section,
    SubSection,
} from '@/components/docs/doc-blocks';
import { apiBaseUrl, productionOrigin } from '@/lib/urls';

/**
 * The reference for everything a machine talks to: the versioned HTTP API, the
 * outbound webhooks and the SMPP listener.
 *
 * Every sample here is an example of the real contract — the request shape, the
 * response envelope and the field names come from the resources and controllers
 * under `app/Http/Resources` and `app/Services/Webhooks`. Hosts in the samples
 * come from `@/lib/urls`, so the reference always prints the production origin
 * (`https://omnicast.sysup360.com`) rather than the host being browsed.
 */
const SECTIONS = [
    { id: 'overview', label: 'Overview' },
    { id: 'authentication', label: 'Authentication' },
    { id: 'quickstart', label: 'Quickstart' },
    { id: 'conventions', label: 'Conventions' },
    { id: 'errors', label: 'Errors and status codes' },
    { id: 'rate-limits', label: 'Rate limits' },
    { id: 'pagination', label: 'Pagination and filtering' },
    { id: 'endpoints', label: 'Endpoint index' },
    { id: 'health', label: 'GET /health' },
    { id: 'me', label: 'GET /me' },
    { id: 'campaigns-list', label: 'GET /campaigns' },
    { id: 'campaigns-show', label: 'GET /campaigns/{id}' },
    { id: 'campaign-object', label: 'The campaign object' },
    { id: 'enums', label: 'Enumerations' },
    { id: 'webhooks', label: 'Webhooks' },
    { id: 'webhook-events', label: 'Webhook payloads' },
    { id: 'smpp', label: 'SMPP' },
    { id: 'recipes', label: 'Integration recipes' },
    { id: 'changelog', label: 'Versioning and changelog' },
];

export default function ApiDocs() {
    return (
        <DocShell
            kind="api"
            title="API Docs"
            eyebrow="API documentation · v1"
            intro={
                <p>
                    A versioned JSON API for reading campaigns, signed webhooks
                    for reacting to delivery, and an SMPP listener for
                    integrations that speak SMS natively. This reference
                    documents the contract exactly as the application implements
                    it.
                </p>
            }
            sections={SECTIONS}
        >
            <Section
                id="overview"
                title="Overview"
                lead="One base URL, one credential type and a version that is part of the path."
            >
                <FieldTable
                    columns={['Property', 'Value']}
                    rows={[
                        ['Base URL', apiBaseUrl],
                        [
                            'Environment',
                            'The address above is the production deployment. Staging and local installs answer on the same /api/v1 path under their own host.',
                        ],
                        [
                            'Transport',
                            'HTTPS only; JSON request and response bodies.',
                        ],
                        [
                            'Version in path',
                            '/api/v1 — a breaking change becomes /api/v2 rather than a silent edit.',
                        ],
                        [
                            'Version reported',
                            'The `version` field of GET /api/v1/health, and the X-Omnicast-Api-Version header on webhook deliveries.',
                        ],
                        [
                            'Authentication',
                            'Sanctum personal access token as a bearer token.',
                        ],
                        [
                            'Pagination',
                            'Page based, 25 records by default, 100 maximum.',
                        ],
                        [
                            'Timestamps',
                            'ISO 8601 with offset, for example 2026-11-08T09:41:07+00:00.',
                        ],
                        [
                            'Money',
                            'Decimals in the wallet currency (GHS), never minor units.',
                        ],
                    ]}
                />

                <Para>
                    Three interfaces share this documentation: the HTTP API
                    described from the next chapter, the outbound webhooks
                    OmniCast pushes to your endpoint, and the SMPP listener your
                    SMSC-side integration binds to. They authenticate
                    differently — a bearer token for HTTP, an HMAC signature for
                    webhooks, a system ID for SMPP — so treat the credential
                    sections as separate.
                </Para>

                <Callout tone="note" title="Scope follows the token's owner">
                    A token only ever sees the campaigns of the organisation its
                    owner belongs to. There is no unauthenticated endpoint
                    except the health probe.
                </Callout>
            </Section>

            <Section
                id="authentication"
                title="Authentication"
                lead="Create a personal access token in the app, then send it as a bearer token."
            >
                <Endpoint
                    method="AUTH"
                    path="Authorization: Bearer <token>"
                    summary="Every /api/v1 request except GET /api/v1/health must carry a token."
                />

                <FieldTable
                    columns={['Ability', 'Allows']}
                    rows={[
                        [
                            'read',
                            'GET /me, GET /campaigns, GET /campaigns/{campaign}.',
                        ],
                        [
                            'write',
                            'Reserved for record-changing endpoints. A token without it receives 403 on those routes.',
                        ],
                    ]}
                    caption="GET /me needs only a valid token, with or without abilities. Create tokens under Settings → API tokens: the plain value is shown once and only a hash is stored, so a lost token must be replaced."
                />

                <CodeBlock
                    title="Authenticated request"
                    language="bash"
                    code={`curl -s "${productionOrigin}/api/v1/me" \\
  -H "Authorization: Bearer 3|GQ7d...your-token" \\
  -H "Accept: application/json"`}
                />

                <Callout tone="warning" title="Keep tokens server-side">
                    A token is bound to the user who created it and carries
                    their organisation’s scope. Do not ship one in a browser
                    bundle or a mobile app; proxy through your own backend
                    instead.
                </Callout>
            </Section>

            <Section
                id="quickstart"
                title="Quickstart"
                lead="Six commands from nothing to a campaign list."
            >
                <CodeBlock
                    title="Shell"
                    language="bash"
                    code={`# 1. Is the deployment up? (no token needed)
curl -s ${productionOrigin}/api/v1/health

# 2. Who does this token belong to?
curl -s ${productionOrigin}/api/v1/me \\
  -H "Authorization: Bearer $OMNICAST_TOKEN"

# 3. The first page of campaigns
curl -s "${productionOrigin}/api/v1/campaigns?per_page=10" \\
  -H "Authorization: Bearer $OMNICAST_TOKEN"

# 4. Only the ones that are running
curl -s "${productionOrigin}/api/v1/campaigns?status=running" \\
  -H "Authorization: Bearer $OMNICAST_TOKEN"

# 5. Search by name or reference
curl -s "${productionOrigin}/api/v1/campaigns?search=October" \\
  -H "Authorization: Bearer $OMNICAST_TOKEN"

# 6. One campaign, by id
curl -s ${productionOrigin}/api/v1/campaigns/42 \\
  -H "Authorization: Bearer $OMNICAST_TOKEN"`}
                />

                <Para>
                    The response to step 3 is a standard Laravel resource
                    collection: a <Mono>data</Mono> array plus{' '}
                    <Mono>links</Mono> and <Mono>meta</Mono> for pagination.
                </Para>
            </Section>

            <Section
                id="conventions"
                title="Conventions"
                lead="The shape every endpoint follows, so a client can be written once."
            >
                <Bullets
                    items={[
                        <>
                            Send and accept <Mono>application/json</Mono>. Add{' '}
                            <Mono>Accept: application/json</Mono> so an error is
                            rendered as JSON rather than HTML.
                        </>,
                        'Identifiers are integers; campaign references are separate, human-quotable strings (ULID-style, stable for the life of the record).',
                        'Timestamps are ISO 8601 with an offset and may be null when an event has not happened yet.',
                        'Money is a decimal number in the wallet currency. Never assume two decimal places — round at display time.',
                        'A collection is wrapped in `data`; a single record is returned as `{ "data": { … } }`.',
                        'Field names are snake_case, matching the field names in the application’s own forms and exports.',
                    ]}
                />

                <CodeBlock
                    title="Single record envelope"
                    language="json"
                    code={`{
  "data": {
    "id": 42,
    "reference": "CMP-01J9Z6Q3W7",
    "name": "October arrears reminder",
    "status": "running",
    "links": {
      "self": "${productionOrigin}/api/v1/campaigns/42",
      "web": "${productionOrigin}/campaigns/42"
    }
  }
}`}
                />
            </Section>

            <Section
                id="errors"
                title="Errors and status codes"
                lead="Laravel's JSON error shape, with the reason in `message` and field detail in `errors`."
            >
                <FieldTable
                    columns={['Status', 'When it happens', 'Body']}
                    rows={[
                        [
                            '200',
                            'The request succeeded; the payload is in `data`.',
                            'Resource or collection.',
                        ],
                        [
                            '401',
                            'No token, an expired token, or a revoked one.',
                            'message: "Unauthenticated." or "An access token is required."',
                        ],
                        [
                            '403',
                            'The token lacks the ability the route requires.',
                            'message: "This token does not hold the required ability."',
                        ],
                        [
                            '404',
                            'The record does not exist, or belongs to another organisation.',
                            'message: "No query results for model …" — a foreign record is deliberately indistinguishable.',
                        ],
                        [
                            '405',
                            'The endpoint exists but not for that method.',
                            'message: "The X method is not supported…"',
                        ],
                        [
                            '422',
                            'A query parameter failed validation, for example per_page above the cap.',
                            'message plus an errors object keyed by parameter.',
                        ],
                        [
                            '429',
                            'The per-token minute limit was exhausted.',
                            'message: "Too Many Requests." plus a Retry-After header.',
                        ],
                        [
                            '500',
                            'An unexpected server error; the incident is logged with a request id.',
                            'message only — the stack trace is never returned.',
                        ],
                    ]}
                />

                <CodeBlock
                    title="422 validation error"
                    language="json"
                    code={`{
  "message": "The per page field must not be greater than 100. (and 1 more error)",
  "errors": {
    "per_page": [
      "The per page field must not be greater than 100."
    ],
    "page": [
      "The page field must be at least 1."
    ]
  }
}`}
                />

                <Callout
                    tone="note"
                    title="A foreign record answers 404, not 403"
                >
                    Asking for a campaign that belongs to another organisation
                    by id returns <Mono>404</Mono>. That is deliberate: a{' '}
                    <Mono>403</Mono> would confirm the record exists.
                </Callout>
            </Section>

            <Section
                id="rate-limits"
                title="Rate limits"
                lead="A per-token ceiling per minute, chosen by the abilities the token holds."
            >
                <FieldTable
                    columns={['Token kind', 'Default limit', 'Key']}
                    rows={[
                        ['read only', '120 requests per minute', 'token id'],
                        ['read + write', '30 requests per minute', 'token id'],
                        [
                            'unauthenticated',
                            'IP based, and only the health probe is reachable',
                            'ip address',
                        ],
                    ]}
                    caption="Both ceilings are configuration (API_RATE_LIMIT_READ and API_RATE_LIMIT_WRITE), so a deployment can raise them without a code change."
                />

                <Para>
                    Every response carries the standard{' '}
                    <Mono>X-RateLimit-Limit</Mono> and{' '}
                    <Mono>X-RateLimit-Remaining</Mono> headers. When the ceiling
                    is reached the API answers <Mono>429</Mono> with a{' '}
                    <Mono>Retry-After</Mono> header — wait that many seconds
                    rather than retrying immediately.
                </Para>

                <Callout tone="tip" title="Poll a filter, not the world">
                    For a sync job, ask for the page you need with{' '}
                    <Mono>status</Mono> and <Mono>per_page</Mono> and let
                    webhooks tell you when something changes. A hundred-page
                    crawl per minute will hit the limit; one page every few
                    seconds will not.
                </Callout>
            </Section>

            <Section
                id="pagination"
                title="Pagination and filtering"
                lead="Page-based, newest first, with the filters the campaign list actually supports."
            >
                <FieldTable
                    columns={['Parameter', 'Type', 'Notes']}
                    rows={[
                        [
                            'page',
                            'integer ≥ 1',
                            'Which page to return. Default 1.',
                        ],
                        [
                            'per_page',
                            'integer 1–100',
                            'Page size. Default 25; asking for more than the cap is a 422.',
                        ],
                        [
                            'status',
                            'string',
                            'One campaign status, e.g. draft, running, completed.',
                        ],
                        ['channel', 'string', 'sms, voice or email.'],
                        [
                            'search',
                            'string ≤ 100',
                            'Matches the campaign name or its reference, case-insensitively.',
                        ],
                    ]}
                />

                <CodeBlock
                    title="Collection envelope"
                    language="json"
                    code={`{
  "data": [ { "id": 42, "reference": "CMP-01J9Z6Q3W7", "…": "…" } ],
  "links": {
    "first": "${productionOrigin}/api/v1/campaigns?page=1",
    "last": "${productionOrigin}/api/v1/campaigns?page=4",
    "prev": null,
    "next": "${productionOrigin}/api/v1/campaigns?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 4,
    "per_page": 25,
    "to": 25,
    "total": 92
  }
}
    `}
                />

                <Para>
                    Follow <Mono>links.next</Mono> until it is <Mono>null</Mono>{' '}
                    rather than building page numbers yourself: the link already
                    carries your filters.
                </Para>
            </Section>

            <Section
                id="endpoints"
                title="Endpoint index"
                lead="Four routes in v1: one probe, one identity, and the read side of campaigns."
            >
                <Endpoint
                    method="GET"
                    path="/api/v1/health"
                    summary="Liveness and the API version actually being served. No token required."
                    scope="public"
                />
                <Endpoint
                    method="GET"
                    path="/api/v1/me"
                    summary="The user behind the token, with roles, permissions and two-factor state."
                    scope="any token"
                />
                <Endpoint
                    method="GET"
                    path="/api/v1/campaigns"
                    summary="A paginated, filterable list of the campaigns the token may read."
                    scope="read"
                />
                <Endpoint
                    method="GET"
                    path="/api/v1/campaigns/{campaign}"
                    summary="One campaign with its audience, cost and delivery statistics."
                    scope="read"
                />

                <Callout tone="note" title="The API is read-only in v1">
                    Campaigns are created, submitted and launched in the
                    application. The API answers reporting integrations: a
                    dashboard, a reconciliation job or a CRM that needs the
                    current state.
                </Callout>
            </Section>

            <Section
                id="health"
                title="GET /api/v1/health"
                lead="Point a monitoring probe here. No credential, no side effects."
            >
                <Endpoint
                    method="GET"
                    path="/api/v1/health"
                    summary="Answers 200 with the version the deployment is serving and the server time."
                    scope="public"
                />

                <CodeBlock
                    title="200 OK"
                    language="json"
                    code={`{
  "status": "ok",
  "version": "v1",
  "time": "2026-11-08T09:41:07+00:00"
}`}
                />

                <Para>
                    The version in the payload is what the gateway is actually
                    running, not what a client was configured with — which is
                    the point of a probe that also reports a contract.
                </Para>
            </Section>

            <Section
                id="me"
                title="GET /api/v1/me"
                lead="Confirm which user, organisation and abilities a token carries before anything else."
            >
                <Endpoint
                    method="GET"
                    path="/api/v1/me"
                    summary="The authenticated user's identity, roles, permissions and security state."
                    scope="any token"
                />

                <CodeBlock
                    title="200 OK"
                    language="json"
                    code={`{
  "data": {
    "id": 17,
    "name": "Ama Mensah",
    "email": "ama@acme.example",
    "client_id": 3,
    "roles": ["client-admin"],
    "permissions": ["campaigns.view", "campaigns.manage", "contacts.view"],
    "two_factor_enabled": true,
    "last_login_at": "2026-11-07T16:12:44+00:00"
  }
}`}
                />

                <FieldTable
                    columns={['Field', 'Type', 'Notes']}
                    rows={[
                        ['id', 'integer', 'The user id.'],
                        [
                            'name, email',
                            'string',
                            'The identity the token acts as.',
                        ],
                        [
                            'client_id',
                            'integer|null',
                            'The organisation the user belongs to.',
                        ],
                        [
                            'roles',
                            'string[]',
                            'Role names, for example client-admin or client-operator.',
                        ],
                        [
                            'permissions',
                            'string[]',
                            'Effective permission names, derived from the roles.',
                        ],
                        [
                            'two_factor_enabled',
                            'boolean',
                            'Whether a second factor has been confirmed.',
                        ],
                        [
                            'last_login_at',
                            'string|null',
                            'ISO 8601 timestamp of the last interactive sign-in.',
                        ],
                    ]}
                />
            </Section>

            <Section
                id="campaigns-list"
                title="GET /api/v1/campaigns"
                lead="Newest first, scoped to your organisation, filterable and paginated."
            >
                <Endpoint
                    method="GET"
                    path="/api/v1/campaigns"
                    summary="Returns a paginated collection of campaign resources."
                    scope="read"
                />

                <FieldTable
                    columns={['Query parameter', 'Values', 'Notes']}
                    rows={[
                        [
                            'status',
                            'string ≤ 32',
                            'draft, pending_approval, approved, rejected, scheduled, running, paused, completed, failed, cancelled.',
                        ],
                        ['channel', 'string ≤ 16', 'sms, voice or email.'],
                        [
                            'search',
                            'string ≤ 100',
                            'Substring of the name or the reference.',
                        ],
                        ['per_page', '1–100', 'Defaults to 25.'],
                        ['page', '≥ 1', 'Defaults to 1.'],
                    ]}
                />

                <CodeBlock
                    title="Request"
                    language="bash"
                    code={`curl -s "${productionOrigin}/api/v1/campaigns?status=running&channel=sms&per_page=50" \\
  -H "Authorization: Bearer $OMNICAST_TOKEN" \\
  -H "Accept: application/json"`}
                />

                <Para>
                    Ordering is by id descending, which is also newest first, so
                    a sync job can stop as soon as it meets a record it has
                    already stored.
                </Para>
            </Section>

            <Section
                id="campaigns-show"
                title="GET /api/v1/campaigns/{campaign}"
                lead="One campaign by id, with the collected counts an integration needs."
            >
                <Endpoint
                    method="GET"
                    path="/api/v1/campaigns/{campaign}"
                    summary="Returns one campaign resource; 404 when it does not exist or belongs to another organisation."
                    scope="read"
                />

                <CodeBlock
                    title="Request"
                    language="bash"
                    code={`curl -s "${productionOrigin}/api/v1/campaigns/42" \\
  -H "Authorization: Bearer $OMNICAST_TOKEN" \\
  -H "Accept: application/json"`}
                />

                <CodeBlock
                    title="200 OK (truncated)"
                    language="json"
                    code={`{
  "data": {
    "id": 42,
    "reference": "CMP-01J9Z6Q3W7",
    "name": "October arrears reminder",
    "channel": "sms",
    "status": "running",
    "status_label": "Running",
    "version": 3,
    "client_id": 3,
    "statistics": {
      "recipients": 12480,
      "delivered": 9012,
      "failed": 143
    },
    "links": {
      "self": "${productionOrigin}/api/v1/campaigns/42",
      "web": "${productionOrigin}/campaigns/42"
    }
  }
}`}
                />

                <Callout tone="note" title="Counts are live">
                    The statistics are counted when the request is served, not
                    cached, so a poll while a campaign runs shows it moving.
                </Callout>
            </Section>

            <Section
                id="campaign-object"
                title="The campaign object"
                lead="The stable shape v1 promises. New fields may be added; existing ones will not move or change meaning."
            >
                <CodeBlock
                    title="Full campaign resource"
                    language="json"
                    code={`{
  "id": 42,
  "reference": "CMP-01J9Z6Q3W7",
  "name": "October arrears reminder",
  "channel": "sms",
  "status": "running",
  "status_label": "Running",
  "version": 3,
  "client_id": 3,
  "audience": {
    "contact_list_id": 8,
    "contact_segment_id": null,
    "estimated_recipients": 12480
  },
  "content": {
    "sms_message": "Hello {{first_name}}, your balance is due.",
    "sms_encoding": "gsm7",
    "email_subject": null,
    "voice_script": null
  },
  "schedule": {
    "type": "immediate",
    "scheduled_at": null,
    "timezone": "Africa/Accra",
    "window_starts_at": "08:00:00",
    "window_ends_at": "20:00:00"
  },
  "cost": {
    "estimated": 1248.00,
    "reserved": 1248.00,
    "actual": 915.50,
    "currency": "GHS"
  },
  "statistics": {
    "recipients": 12480,
    "delivered": 9012,
    "failed": 143
  },
  "timestamps": {
    "created_at": "2026-10-01T09:00:00+00:00",
    "submitted_at": "2026-10-01T09:14:00+00:00",
    "approved_at": "2026-10-01T09:20:00+00:00",
    "launched_at": "2026-10-01T09:21:00+00:00",
    "completed_at": null
  },
  "links": {
    "self": "${productionOrigin}/api/v1/campaigns/42",
    "web": "${productionOrigin}/campaigns/42"
  }
}`}
                />

                <FieldTable
                    columns={['Field', 'Type', 'Notes']}
                    rows={[
                        ['id', 'integer', 'Primary key.'],
                        [
                            'reference',
                            'string',
                            'Quotable identifier used by reports, invoices and webhooks.',
                        ],
                        ['name', 'string', 'The internal name.'],
                        ['channel', 'string', 'sms, voice or email.'],
                        [
                            'status',
                            'string',
                            'Machine status; see the enumerations chapter.',
                        ],
                        [
                            'status_label',
                            'string',
                            'Human label for the same status.',
                        ],
                        [
                            'version',
                            'integer',
                            'Content revision, incremented when the campaign is edited.',
                        ],
                        [
                            'client_id',
                            'integer',
                            'The organisation that owns the campaign.',
                        ],
                        [
                            'audience.contact_list_id',
                            'integer|null',
                            'Null means every broadcastable contact.',
                        ],
                        [
                            'audience.contact_segment_id',
                            'integer|null',
                            'Saved segment applied on top of the list.',
                        ],
                        [
                            'audience.estimated_recipients',
                            'integer|null',
                            'Estimate taken at submission.',
                        ],
                        [
                            'content.sms_encoding',
                            'string|null',
                            'gsm7 or unicode; null when the campaign has no SMS body.',
                        ],
                        [
                            'schedule.type',
                            'string|null',
                            'immediate or scheduled.',
                        ],
                        [
                            'schedule.scheduled_at',
                            'string|null',
                            'ISO 8601 when the campaign is scheduled.',
                        ],
                        [
                            'schedule.timezone',
                            'string|null',
                            'The zone the send window is expressed in.',
                        ],
                        [
                            'schedule.window_starts_at',
                            'string|null',
                            'Earliest time of day a message may be sent.',
                        ],
                        [
                            'schedule.window_ends_at',
                            'string|null',
                            'Latest time of day a message may be sent.',
                        ],
                        [
                            'cost.estimated / reserved / actual',
                            'number',
                            'Decimals in the currency shown alongside.',
                        ],
                        [
                            'cost.currency',
                            'string',
                            'The wallet currency, for example GHS.',
                        ],
                        [
                            'statistics.*',
                            'integer',
                            'Recipients, delivered and failed at the moment of the request.',
                        ],
                        [
                            'timestamps.*',
                            'string|null',
                            'ISO 8601 for each lifecycle step; null until it happens.',
                        ],
                        [
                            'links.self / links.web',
                            'string',
                            'Absolute URLs to the record and to its screen in the app.',
                        ],
                    ]}
                />
            </Section>

            <Section
                id="enums"
                title="Enumerations"
                lead="Every string an integration has to switch on."
            >
                <FieldTable
                    columns={['Campaign status', 'Meaning']}
                    rows={[
                        ['draft', 'Being written; nothing reserved.'],
                        [
                            'pending_approval',
                            'Submitted and awaiting a reviewer; cost reserved.',
                        ],
                        ['approved', 'Cleared to run.'],
                        [
                            'rejected',
                            'Refused by a reviewer; reservation released.',
                        ],
                        [
                            'scheduled',
                            'Approved and waiting for its start time.',
                        ],
                        ['running', 'Sending in progress.'],
                        ['paused', 'Stopped mid-flight.'],
                        [
                            'completed',
                            'Every recipient reached a terminal state.',
                        ],
                        ['failed', 'Could not finish.'],
                        ['cancelled', 'Stopped by a user.'],
                    ]}
                />

                <FieldTable
                    columns={[
                        'Channel',
                        'Value',
                        'Recipient status',
                        'Meaning',
                    ]}
                    rows={[
                        [
                            'SMS',
                            'sms',
                            'pending',
                            'Queued for the campaign, not attempted.',
                        ],
                        [
                            'Voice',
                            'voice',
                            'queued',
                            'Handed to a channel worker.',
                        ],
                        [
                            'Email',
                            'email',
                            'sent',
                            'Accepted by the provider; no receipt yet.',
                        ],
                        [
                            '—',
                            '—',
                            'delivered',
                            'A receipt confirmed delivery.',
                        ],
                        [
                            '—',
                            '—',
                            'failed',
                            'Refused or timed out; retries apply per the campaign.',
                        ],
                        [
                            '—',
                            '—',
                            'skipped',
                            'Deliberately not sent — suppressed, unreachable for the channel, or out of retries.',
                        ],
                    ]}
                />

                <FieldTable
                    columns={['SMS encoding', 'Value', 'When it is used']}
                    rows={[
                        [
                            'GSM-7',
                            'gsm7',
                            'Every character is in the GSM-7 alphabet: one segment per 160 characters.',
                        ],
                        [
                            'Unicode',
                            'unicode',
                            'Any character outside it: fewer characters per segment.',
                        ],
                    ]}
                />
            </Section>

            <Section
                id="webhooks"
                title="Webhooks"
                lead="Signed, retried, replayable callbacks. The envelope is Stripe-shaped on purpose."
            >
                <Para>
                    A subscription is created in the app under Settings →
                    Webhooks with a URL and a list of events. Deliveries are
                    queued, so a slow subscriber never slows a launch down.
                </Para>

                <CodeBlock
                    title="Envelope"
                    language="json"
                    code={`{
  "id": "evt_01J9Z7A2K4",
  "type": "campaign.launched",
  "version": "v1",
  "created_at": "2026-10-01T09:21:00+00:00",
  "data": {
    "id": 42,
    "reference": "CMP-01J9Z6Q3W7",
    "status": "running",
    "cost": { "estimated": 1248.0, "actual": 0.0, "currency": "GHS" }
  }
}`}
                />

                <FieldTable
                    columns={['Header', 'Value']}
                    rows={[
                        [
                            'X-Omnicast-Signature',
                            't=<unix>,v1=<hmac-sha256 over "timestamp.rawBody">',
                        ],
                        [
                            'X-Omnicast-Event',
                            'The event name, e.g. campaign.launched.',
                        ],
                        [
                            'X-Omnicast-Delivery',
                            'The delivery id, for support and replay.',
                        ],
                        [
                            'X-Omnicast-Api-Version',
                            'The payload version, currently v1.',
                        ],
                        [
                            'X-Omnicast-Attempt',
                            'Which attempt this is, starting at 1.',
                        ],
                        ['Content-Type', 'application/json.'],
                        ['User-Agent', 'OmniCast-Webhooks/1.0 by default.'],
                    ]}
                />

                <FieldTable
                    columns={['Behaviour', 'Value']}
                    rows={[
                        ['Attempts', '5 per delivery.'],
                        ['Backoff', '30s, 120s, 600s, 1800s.'],
                        ['Timeouts', '15s total, 5s to connect.'],
                        [
                            'Auto-disable',
                            'After 10 consecutive failures the endpoint is switched off.',
                        ],
                        [
                            'Retention',
                            'Delivery records are pruned after 30 days by default.',
                        ],
                        [
                            'Test event',
                            'The subscription screen can post a sample payload on demand.',
                        ],
                    ]}
                />

                <Callout tone="warning" title="Verify before you trust">
                    Compute the HMAC over the raw request body — not a body you
                    parsed and re-serialised — and compare with{' '}
                    <Mono>hash_equals</Mono> or an equivalent constant-time
                    comparison. Reject anything older than the tolerance (300
                    seconds by default).
                </Callout>

                <FieldTable
                    columns={['Inbound provider webhook', 'Endpoint']}
                    rows={[
                        [
                            'SMS delivery receipts',
                            'POST (or GET for Kannel) /webhooks/sms',
                        ],
                        ['Voice call events', 'POST /webhooks/voice'],
                        ['Email events', 'POST /webhooks/email'],
                        ['Payment gateway', 'POST /webhooks/paystack'],
                    ]}
                    caption="These are for providers, not clients: each one is signed with its own shared secret and rejects anything it cannot authenticate."
                />
            </Section>

            <Section
                id="webhook-events"
                title="Webhook payloads"
                lead="What `data` contains for each event family."
            >
                <SubSection title="Campaign events">
                    <Para>
                        The same body is sent for every campaign event;{' '}
                        <Mono>type</Mono> tells you which one fired and the
                        timestamps show how far the campaign has travelled.
                    </Para>

                    <CodeBlock
                        title="campaign.rejected"
                        language="json"
                        code={`{
  "id": "evt_01J9Z7A2K4",
  "type": "campaign.rejected",
  "version": "v1",
  "created_at": "2026-10-01T09:30:00+00:00",
  "data": {
    "id": 42,
    "reference": "CMP-01J9Z6Q3W7",
    "name": "October arrears reminder",
    "channel": "sms",
    "status": "rejected",
    "client": { "id": 3, "name": "Acme Ltd" },
    "audience": { "estimated_recipients": 12480, "estimated_sms_parts": 1 },
    "cost": { "estimated": 1248.0, "actual": 0.0, "currency": "GHS" },
    "schedule": {
      "type": "immediate",
      "scheduled_at": null,
      "timezone": "Africa/Accra"
    },
    "rejection_reason": "Copy does not carry an opt-out line.",
    "timestamps": {
      "created_at": "2026-10-01T09:00:00+00:00",
      "submitted_at": "2026-10-01T09:14:00+00:00",
      "approved_at": null,
      "rejected_at": "2026-10-01T09:30:00+00:00",
      "launched_at": null,
      "completed_at": null
    },
    "links": { "self": "${productionOrigin}/api/v1/campaigns/42" }
  }
}`}
                    />
                </SubSection>

                <SubSection title="SMS receipts">
                    <Para>
                        <Mono>sms.delivered</Mono> and <Mono>sms.failed</Mono>{' '}
                        are per message, so a campaign of 12,000 recipients
                        produces 12,000 events. Subscribe only if you need that
                        volume; otherwise read the campaign statistics over the
                        API.
                    </Para>

                    <CodeBlock
                        title="sms.delivered"
                        language="json"
                        code={`{
  "id": "evt_01J9Z8B6N1",
  "type": "sms.delivered",
  "version": "v1",
  "created_at": "2026-10-01T09:24:11+00:00",
  "data": {
    "reference": "SMS-01J9Z8A1QX",
    "status": "delivered",
    "provider": "txtconnect",
    "provider_message_id": "TC-991",
    "from": "OmniCast",
    "to": "+233241234567",
    "segments": 1,
    "cost": 0.04,
    "error": null,
    "campaign": { "id": 42, "reference": "CMP-01J9Z6Q3W7" },
    "timestamps": {
      "submitted_at": "2026-10-01T09:22:03+00:00",
      "delivered_at": "2026-10-01T09:24:11+00:00",
      "failed_at": null
    }
  }
}`}
                    />
                </SubSection>

                <SubSection title="Email receipts">
                    <CodeBlock
                        title="email.bounced"
                        language="json"
                        code={`{
  "id": "evt_01J9Z9C3R7",
  "type": "email.bounced",
  "version": "v1",
  "created_at": "2026-10-01T10:02:00+00:00",
  "data": {
    "reference": "EML-01J9Z9B2TT",
    "status": "bounced",
    "provider": "smtp",
    "provider_message_id": "01000192A1B2C3D4",
    "from": "statements@acme.example",
    "to": "ama@acme.example",
    "subject": "Your October statement is ready",
    "cost": 0.0005,
    "error": "550 5.1.1 Recipient address rejected",
    "campaign": { "id": 51, "reference": "CMP-01J9Z9A8LM" },
    "timestamps": {
      "submitted_at": "2026-10-01T10:01:20+00:00",
      "delivered_at": null,
      "failed_at": "2026-10-01T10:02:00+00:00"
    }
  }
}`}
                    />
                </SubSection>

                <FieldTable
                    columns={['Event group', 'Events']}
                    rows={[
                        [
                            'Campaigns',
                            'campaign.created, campaign.submitted, campaign.approved, campaign.rejected, campaign.launched, campaign.completed, campaign.cancelled',
                        ],
                        ['SMS', 'sms.delivered, sms.failed'],
                        ['Email', 'email.delivered, email.bounced'],
                    ]}
                    caption="A subscription is a list of event names; an endpoint subscribed to none receives nothing."
                />
            </Section>

            <Section
                id="smpp"
                title="SMPP"
                lead="A TCP interface for ESMEs that submit SMS natively instead of over HTTP."
            >
                <FieldTable
                    columns={['Connection', 'Value']}
                    rows={[
                        [
                            'Host',
                            'The SMPP host given to you with your account.',
                        ],
                        ['Port', '2775 by default (SMPP_SERVER_PORT).'],
                        ['System ID', 'Issued per account.'],
                        [
                            'Password',
                            'Issued once with the account; ask support to rotate it.',
                        ],
                        ['Bind modes', 'transmitter, receiver, transceiver.'],
                        ['Interface version', '3.4.'],
                        [
                            'TLS',
                            'Terminated in front of the listener by the deployment, not by the PDU layer.',
                        ],
                    ]}
                />

                <SubSection title="Submitting a message">
                    <Bullets
                        items={[
                            <>
                                Bind, then send <Mono>submit_sm</Mono> with{' '}
                                <Mono>source_addr</Mono>,{' '}
                                <Mono>destination_addr</Mono> and{' '}
                                <Mono>short_message</Mono>. The source becomes
                                the sender name; a configured default applies
                                when it is empty.
                            </>,
                            <>
                                The answer is a <Mono>submit_sm_resp</Mono>{' '}
                                whose <Mono>message_id</Mono> is the reference
                                receipts quote back.
                            </>,
                            'A UDH-bearing message is reassembled from its parts before it is stored, so long copy arrives as one record with the right segment count.',
                            'Every submission is written to the SMPP message log with its status, cost and the organisation it belongs to.',
                        ]}
                    />
                </SubSection>

                <SubSection title="Delivery receipts">
                    <Para>
                        The listener sends a <Mono>deliver_sm</Mono> to the same
                        session with the message id in the receipt body, in the
                        familiar{' '}
                        <Mono>id:… stat:… err:… submit date:… done date:…</Mono>{' '}
                        form. A receipt that cannot be parsed leaves the message
                        in its current state rather than marking it delivered.
                    </Para>
                </SubSection>

                <FieldTable
                    columns={['Message status', 'Meaning']}
                    rows={[
                        [
                            'accepted',
                            'Stored from the PDU, not yet handed to the SMS transport.',
                        ],
                        ['submitted', 'Handed to the SMS transport.'],
                        ['delivered', 'The transport confirmed delivery.'],
                        ['failed', 'Refused, or out of retries.'],
                        [
                            'rejected',
                            'Refused outright, for example a suppressed destination.',
                        ],
                    ]}
                />

                <FieldTable
                    columns={['Bind error', 'When it is answered']}
                    rows={[
                        ['ESME_RINVSYSID', 'Unknown system ID.'],
                        ['ESME_RINVPASWD', 'Wrong password.'],
                        [
                            'ESME_RBINDFAIL',
                            'The listener is switched off, or the account is suspended.',
                        ],
                        [
                            'ESME_RTHROTTLED',
                            'The concurrent session ceiling was reached.',
                        ],
                    ]}
                />

                <Para>
                    Session policy defaults: 100 concurrent sessions, an{' '}
                    <Mono>enquire_link</Mono> every 30 seconds, a 90-second idle
                    timeout and a one-hour session lifetime. A quiet session
                    that stops answering <Mono>enquire_link</Mono> is closed so
                    a leaked connection cannot hold a slot.
                </Para>
            </Section>

            <Section
                id="recipes"
                title="Integration recipes"
                lead="Three patterns that cover most real integrations."
            >
                <SubSection title="1. A monitoring probe">
                    <CodeBlock
                        title="Bash"
                        language="bash"
                        code={`#!/usr/bin/env bash
set -euo pipefail

payload=$(curl -fsS --max-time 5 ${productionOrigin}/api/v1/health)
status=$(printf '%s' "$payload" | python3 -c 'import json,sys; print(json.load(sys.stdin)["status"])')
version=$(printf '%s' "$payload" | python3 -c 'import json,sys; print(json.load(sys.stdin)["version"])')

[ "$status" = "ok" ] && [ "$version" = "v1" ] || exit 1`}
                    />
                </SubSection>

                <SubSection title="2. Sync every campaign into your own store">
                    <CodeBlock
                        title="PHP (Laravel HTTP client)"
                        language="php"
                        code={`$url = config('services.omnicast.url').'/api/v1/campaigns?per_page=100';

while ($url !== null) {
    $response = Http::withToken(config('services.omnicast.token'))
        ->acceptJson()
        ->throw()
        ->get($url);

    foreach ($response->json('data') as $campaign) {
        CampaignMirror::updateOrCreate(
            ['omnicast_id' => $campaign['id']],
            [
                'reference' => $campaign['reference'],
                'name' => $campaign['name'],
                'channel' => $campaign['channel'],
                'status' => $campaign['status'],
                'delivered' => $campaign['statistics']['delivered'],
                'failed' => $campaign['statistics']['failed'],
                'actual_cost' => $campaign['cost']['actual'],
                'completed_at' => $campaign['timestamps']['completed_at'],
            ],
        );
    }

    $url = $response->json('links.next');
}`}
                    />
                    <Para>
                        <Mono>throw()</Mono> turns a 4xx or 5xx into an
                        exception, which is what you want in a scheduled job,
                        and the <Mono>links.next</Mono> loop stops on its own.
                    </Para>
                </SubSection>

                <SubSection title="3. Alert on a launch">
                    <CodeBlock
                        title="PHP webhook receiver"
                        language="php"
                        code={`$event = $request->json()->all();

if (! $this->signatureIsValid($request)) {
    abort(400, 'invalid signature');
}

if (($event['type'] ?? null) === 'campaign.launched') {
    Slack::send(sprintf(
        'Campaign %s (%s) is running to %s recipients.',
        $event['data']['reference'],
        $event['data']['channel'],
        $event['data']['audience']['estimated_recipients'] ?? 'unknown',
    ));
}

// Acknowledge quickly; anything else counts as a failed attempt.
return response()->noContent();`}
                    />
                </SubSection>
            </Section>

            <Section
                id="changelog"
                title="Versioning and changelog"
                lead="What v1 guarantees, and how a breaking change would arrive."
            >
                <FieldTable
                    columns={['Version', 'Status', 'Notes']}
                    rows={[
                        [
                            'v1',
                            'Current',
                            'Health, identity and the read side of campaigns; webhook payloads are v1 as well.',
                        ],
                        [
                            'v2',
                            'Reserved',
                            'A breaking change ships as a new route group rather than an edit to v1.',
                        ],
                    ]}
                />

                <Bullets
                    items={[
                        'Fields may be added to a response at any time; a client must ignore unknown keys.',
                        'Fields are not removed or retyped inside a version. A removal means a new major version.',
                        'Event names are stable strings; new events can be added to the catalogue without a version bump.',
                        'Webhook envelopes carry version so a subscriber can branch before parsing data.',
                        'Deprecations are announced below and keep working for the rest of the major version.',
                    ]}
                />

                <SubSection title="Changelog">
                    <FieldTable
                        columns={['Date', 'Change']}
                        rows={[
                            [
                                '2026-11',
                                'v1 published: GET /health, GET /me, GET /campaigns, GET /campaigns/{campaign}; eleven webhook events; SMPP listener documented.',
                            ],
                            [
                                '2026-11',
                                'Webhook payloads gained campaign.completed and the SMPP submission body.',
                            ],
                        ]}
                    />
                </SubSection>

                <Callout tone="tip" title="Questions about the contract">
                    If a field you rely on is missing, say which integration
                    needs it and how it is used — a new field is a compatible
                    change, and most requests land in the next release.
                </Callout>
            </Section>
        </DocShell>
    );
}
