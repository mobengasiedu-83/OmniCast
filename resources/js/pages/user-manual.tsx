import { Link } from '@inertiajs/react';
import DocShell from '@/components/docs/doc-shell';
import {
    Bullets,
    Callout,
    CodeBlock,
    FieldTable,
    Mono,
    Para,
    Section,
    Steps,
    SubSection,
} from '@/components/docs/doc-blocks';
import { apiBaseUrl } from '@/lib/urls';
import { apiDocs } from '@/routes';

/**
 * Everything a signed-in client user does inside OmniCast, screen by screen.
 *
 * The chapter list drives the "On this page" rail, so a section and its entry
 * are added in the same edit: `id` is the anchor the rail links to.
 */
const SECTIONS = [
    { id: 'overview', label: 'How OmniCast fits together' },
    { id: 'getting-started', label: 'Getting started' },
    { id: 'dashboard', label: 'The dashboard' },
    { id: 'team', label: 'Team and roles' },
    { id: 'contacts', label: 'Contacts' },
    { id: 'audiences', label: 'Audiences and suppression' },
    { id: 'sender-ids', label: 'Sender IDs' },
    { id: 'templates', label: 'Templates' },
    { id: 'campaigns', label: 'Building a campaign' },
    { id: 'approvals', label: 'Approval and money' },
    { id: 'delivery', label: 'Delivery and monitoring' },
    { id: 'wallet', label: 'Wallet, top-ups and invoices' },
    { id: 'reports', label: 'Reports and exports' },
    { id: 'webhooks', label: 'Webhook subscriptions' },
    { id: 'api-tokens', label: 'API tokens' },
    { id: 'smpp', label: 'SMPP submissions' },
    { id: 'account', label: 'Your account and security' },
    { id: 'troubleshooting', label: 'Troubleshooting' },
    { id: 'glossary', label: 'Glossary' },
];

export default function UserManual() {
    return (
        <DocShell
            kind="manual"
            title="User Manual"
            eyebrow="User manual · every screen explained"
            intro={
                <p>
                    One workspace for SMS, voice and email broadcasting: import
                    an audience, draft a campaign, reserve the credit, get it
                    approved and watch delivery land recipient by recipient.
                    This manual follows the product in the order you meet it.
                </p>
            }
            sections={SECTIONS}
        >
            <Section
                id="overview"
                title="How OmniCast fits together"
                lead="Six objects explain the whole application. Everything else is a screen for one of them."
            >
                <FieldTable
                    columns={[
                        'Building block',
                        'What it is',
                        'Where you work on it',
                    ]}
                    rows={[
                        [
                            'Client account',
                            'Your organisation: one wallet, one set of users, one set of contacts and sender IDs.',
                            'Settings → Organisation',
                        ],
                        [
                            'Contact',
                            'One recipient, stored with one normalised phone number per organisation. Contacts live in lists and carry tags.',
                            'Contacts',
                        ],
                        [
                            'Audience',
                            'The subset a campaign sends to: a whole list, a saved segment, or every broadcastable contact.',
                            'Contacts → Audiences',
                        ],
                        [
                            'Sender ID',
                            'The identity a message arrives from — an SMS sender name, an email from-address or a voice caller ID — and whether it has been approved.',
                            'Sender IDs',
                        ],
                        [
                            'Campaign',
                            'One piece of copy, one audience, one schedule and one cost estimate.',
                            'Campaigns',
                        ],
                        [
                            'Wallet',
                            'The GHS credit every channel draws from. Submitting reserves it, launching captures it.',
                            'Wallet',
                        ],
                    ]}
                />

                <Para>
                    A campaign moves through a fixed lifecycle — draft,
                    submitted, approved, launched, completed — and every move
                    has a screen, an audit row and sometimes a wallet entry. The
                    approval chapter below sets that out in one table.
                </Para>

                <Callout tone="note" title="Channels can be switched off">
                    SMS, voice and email can each be enabled or disabled for
                    your organisation. When a channel is not enabled for you it
                    does not appear in the campaign form and its report screen
                    is hidden from the sidebar — nothing is deleted, only the
                    entry point.
                </Callout>
            </Section>

            <Section
                id="getting-started"
                title="Getting started"
                lead="From the public landing page to your first approved campaign."
            >
                <Steps
                    items={[
                        {
                            title: 'Create an account',
                            body: (
                                <>
                                    Use <Mono>Get started</Mono> on the landing
                                    page. Registration asks for your company
                                    details, the services you need and how the
                                    one-time code should reach you.
                                </>
                            ),
                        },
                        {
                            title: 'Verify and set a password',
                            body: (
                                <>
                                    A six-digit code goes to the email address
                                    or phone number you chose. It expires after
                                    10 minutes; verifying it and choosing a
                                    password finishes the registration.
                                </>
                            ),
                        },
                        {
                            title: 'Wait for approval',
                            body: (
                                <>
                                    New accounts start in the{' '}
                                    <Mono>pending</Mono> state. Your
                                    registration and any compliance documents
                                    are reviewed, then the organisation is
                                    activated.
                                </>
                            ),
                        },
                        {
                            title: 'Fund the wallet',
                            body: (
                                <>
                                    Buy a package or a custom amount from{' '}
                                    <Mono>Wallet</Mono>. Credit arrives as soon
                                    as the payment gateway confirms.
                                </>
                            ),
                        },
                        {
                            title: 'Add contacts and a sender ID',
                            body: (
                                <>
                                    Import your audience and request the
                                    identity you will broadcast under. A
                                    campaign cannot launch until its sender ID
                                    is approved.
                                </>
                            ),
                        },
                        {
                            title: 'Build, submit and launch',
                            body: (
                                <>
                                    Draft the campaign, submit it so its cost is
                                    reserved, then launch once it is approved.
                                </>
                            ),
                        },
                    ]}
                />

                <SubSection title="What registration asks for">
                    <FieldTable
                        columns={['Field', 'Notes']}
                        rows={[
                            [
                                'Company name',
                                'Becomes the organisation name across the workspace.',
                            ],
                            [
                                'Contact name',
                                'The first user on the account, and its client administrator.',
                            ],
                            [
                                'Email address',
                                'Also the sign-in identity; must be unique.',
                            ],
                            [
                                'Phone number',
                                'Normalised to international format and used for SMS codes.',
                            ],
                            [
                                'Country',
                                'Sets the default timezone; another timezone inside that country can be chosen.',
                            ],
                            [
                                'Services',
                                'SMS, voice and/or email — what is enabled on your account.',
                            ],
                            [
                                'Code delivery',
                                'Email or SMS. Either one satisfies verification; you are never asked for both.',
                            ],
                        ]}
                        caption="An SMS code needs a configured SMS provider. When the provider is unavailable the form says so and asks you to use email instead."
                    />
                </SubSection>

                <Callout tone="warning" title="Approval unlocks broadcasting">
                    You can sign in and prepare contacts, audiences and drafts
                    while the account is pending, but launching a campaign and
                    spending credit stay blocked until the organisation is
                    activated.
                </Callout>

                <SubSection title="Signing in">
                    <Bullets
                        items={[
                            <>
                                Sign in at <Mono>/login</Mono> with your email
                                and password. Repeated failures are rate
                                limited, so a locked-out user should wait a
                                minute instead of retrying.
                            </>,
                            'A forgotten password is reset by emailed link from the same screen.',
                            <>
                                Two-factor authentication and passkeys are
                                optional per user and managed in{' '}
                                <Mono>Settings → Security</Mono>.
                            </>,
                            'Team members do not register themselves: an administrator invites them and they accept the emailed link.',
                        ]}
                    />
                </SubSection>
            </Section>

            <Section
                id="dashboard"
                title="The dashboard"
                lead="The first screen after sign-in: what is running, what is waiting and what it is costing."
            >
                <FieldTable
                    columns={['Panel', 'What it tells you']}
                    rows={[
                        [
                            'Stat tiles',
                            'Total campaigns, campaigns in flight (scheduled, running, paused), campaigns awaiting approval, contacts on file, messages delivered and spend this month.',
                        ],
                        [
                            'Wallet card',
                            'Balance, reserved credit and the amount actually available to spend, plus a low-balance warning.',
                        ],
                        [
                            'Channel breakdown',
                            'How many campaigns of each enabled channel exist, so a channel that is suddenly quiet is obvious.',
                        ],
                        [
                            'Recent campaigns',
                            'The six newest campaigns with channel, status, audience, sender, estimated recipients, estimated and actual cost.',
                        ],
                    ]}
                />

                <Para>
                    The sidebar is built from your permissions: a finance user
                    sees the wallet and invoices, an operator sees campaigns,
                    contacts and reports. Sections your organisation does not
                    use, and channels it does not have, are omitted.
                </Para>

                <Callout tone="tip" title="Work from the dashboard's counts">
                    If delivery looks stalled, compare{' '}
                    <Mono>active campaigns</Mono> with the per-recipient
                    statuses on the campaign page: a campaign that stays at{' '}
                    <Mono>pending</Mono> across all recipients has not started
                    sending, not finished.
                </Callout>
            </Section>

            <Section
                id="team"
                title="Team and roles"
                lead="Who is allowed to do what, and how a colleague gets an account."
            >
                <Steps
                    items={[
                        {
                            title: 'Invite from Settings → Team',
                            body: (
                                <>
                                    Enter the colleague&rsquo;s name, email and
                                    role. The invitation is emailed with a
                                    signed link and expires; it can be resent or
                                    revoked until it is accepted.
                                </>
                            ),
                        },
                        {
                            title: 'The colleague accepts',
                            body: (
                                <>
                                    The link opens an acceptance screen where
                                    they set their password; they land inside
                                    the organisation with the role they were
                                    given.
                                </>
                            ),
                        },
                        {
                            title: 'Review the members list',
                            body: (
                                <>
                                    Settings → Team lists everyone with their
                                    role, whether two-factor is on and how many
                                    invitations are outstanding.
                                </>
                            ),
                        },
                    ]}
                />

                <FieldTable
                    columns={['Role', 'Intended for', 'Typical abilities']}
                    rows={[
                        [
                            'Client admin',
                            'Owners and managers of the account.',
                            'Manage contacts, campaigns, sender IDs, templates, wallet top-ups, team, webhooks and API tokens.',
                        ],
                        [
                            'Client operator',
                            'Day-to-day senders.',
                            'Create and submit campaigns, work with contacts and audiences, read reports.',
                        ],
                    ]}
                    caption="These are the two roles a client administrator can assign when inviting a colleague; every member of your organisation holds one of them."
                />

                <Para>
                    Every permission is granular and named after the object it
                    protects — for example <Mono>campaigns.manage</Mono>,{' '}
                    <Mono>wallet.top-up</Mono> and <Mono>reports.export</Mono>.
                    A button you cannot see is usually a permission your role
                    does not hold, not a bug; a client administrator sees and
                    changes members&rsquo; roles under Settings → Team.
                </Para>
            </Section>

            <Section
                id="contacts"
                title="Contacts"
                lead="Contacts is the address book every campaign draws from. One contact holds one phone number for the organisation."
            >
                <SubSection title="Adding a contact">
                    <Para>
                        A single contact needs a phone number; everything else
                        is optional. Numbers are normalised to international
                        format on save, so <Mono>024 123 4567</Mono> and{' '}
                        <Mono>+233241234567</Mono> are the same recipient and
                        cannot both exist on your account.
                    </Para>

                    <FieldTable
                        columns={['Field', 'Notes']}
                        rows={[
                            [
                                'Phone number',
                                'Required. The deduplication key, and the address SMS and voice use.',
                            ],
                            [
                                'First / last name',
                                'Used by the {{first_name}} and {{full_name}} placeholders.',
                            ],
                            [
                                'Email address',
                                'Required for email campaigns; stored lower-case.',
                            ],
                            [
                                'Company, location',
                                'Free text you can search and filter on.',
                            ],
                            [
                                'Account number, customer ID',
                                'Your own identifiers, echoed back per recipient in reports.',
                            ],
                            [
                                'Language, timezone',
                                'Defaults to the organisation settings; per-contact overrides are allowed.',
                            ],
                            [
                                'Tags',
                                'Comma-separated labels used to build audiences.',
                            ],
                            [
                                'Custom fields',
                                'Any extra column prefixed custom_ on import is kept with the contact.',
                            ],
                        ]}
                    />
                </SubSection>

                <SubSection title="Importing a CSV">
                    <Steps
                        items={[
                            {
                                title: 'Download the template',
                                body: (
                                    <>
                                        Contacts → Import offers a CSV with a
                                        header row and one example row. Only the
                                        phone column is mandatory.
                                    </>
                                ),
                            },
                            {
                                title: 'Prepare the file',
                                body: (
                                    <>
                                        Keep the header row. Column names are
                                        normalised, so <Mono>First Name</Mono>{' '}
                                        and <Mono>first_name</Mono> both work,
                                        and <Mono>phone</Mono>,{' '}
                                        <Mono>phone_number</Mono>,{' '}
                                        <Mono>msisdn</Mono> or{' '}
                                        <Mono>mobile</Mono> are all read as the
                                        number.
                                    </>
                                ),
                            },
                            {
                                title: 'Upload and attach',
                                body: (
                                    <>
                                        Choose whether the rows join a list, add
                                        tags, or mark the contacts as
                                        do-not-disturb in the same pass. The
                                        result reports how many were created,
                                        updated and skipped, and shows the first
                                        errors.
                                    </>
                                ),
                            },
                        ]}
                    />

                    <Para>
                        Uploaded contacts are assumed to have consented already,
                        so the import never asks for an opt-in column. Rows with
                        an unusable number are skipped rather than guessed at,
                        and the reason is listed after the upload.
                    </Para>

                    <Callout
                        tone="warning"
                        title="Duplicates are updates, not errors"
                    >
                        A row whose number already exists updates that contact
                        instead of creating a second one. A skipped row means
                        the number could not be normalised; fix it and re-upload
                        only those rows.
                    </Callout>
                </SubSection>

                <SubSection title="Working with the list">
                    <Bullets
                        items={[
                            'Search, filter by tag, list, do-not-disturb state or campaign, then sort the results.',
                            'Bulk actions select a page of contacts to tag, add to a list, set do-not-disturb or delete.',
                            'A contact page shows its campaigns and the per-message history, so “did Ama get the reminder?” has one answer.',
                            'Contacts → Audiences → Merge duplicates repairs numbers that slipped in before normalisation was enforced.',
                        ]}
                    />
                </SubSection>
            </Section>

            <Section
                id="audiences"
                title="Audiences and suppression"
                lead="Audiences decide who a campaign reaches; the do-not-disturb registry decides who it must never reach."
            >
                <FieldTable
                    columns={['Concept', 'Where', 'What it does']}
                    rows={[
                        [
                            'List',
                            'Contacts → Audiences',
                            'A named, static group of contacts. Import straight into one, and reuse it across campaigns.',
                        ],
                        [
                            'Tag',
                            'Contacts → Audiences',
                            'A label many lists can carry. Cheap to apply in bulk and easy to combine into a segment.',
                        ],
                        [
                            'Segment',
                            'Contacts → Audiences',
                            'A saved filter — search text, location, company, list, tag, has-email and do-not-disturb — evaluated every time it is used, so it always reflects today’s data.',
                        ],
                        [
                            'Do-not-disturb',
                            'Contacts → DND',
                            'Per-contact suppression with a reason and a source, applied to every channel.',
                        ],
                    ]}
                />

                <Para>
                    A campaign targets one list (optionally narrowed by a saved
                    segment) or every broadcastable contact on the account.
                    Suppressed contacts are removed automatically, so the
                    recipient estimate already reflects who will actually be
                    dialled.
                </Para>

                <SubSection title="The do-not-disturb registry">
                    <Steps
                        items={[
                            {
                                title: 'A recipient asks to stop',
                                body: (
                                    <>
                                        Add them from their contact page, from a
                                        bulk action, or through the “stop
                                        messages” link that can be appended to
                                        SMS copy.
                                    </>
                                ),
                            },
                            {
                                title: 'The reason is recorded',
                                body: (
                                    <>
                                        Each suppression keeps why it was set
                                        (recipient request, manual, compliance)
                                        and who set it, so an audit question has
                                        an answer.
                                    </>
                                ),
                            },
                            {
                                title: 'Every channel respects it',
                                body: (
                                    <>
                                        A suppressed contact is skipped on SMS,
                                        voice and email, and shows as{' '}
                                        <Mono>skipped</Mono> rather than{' '}
                                        <Mono>failed</Mono>.
                                    </>
                                ),
                            },
                            {
                                title: 'Only an authorised user lifts it',
                                body: (
                                    <>
                                        Lifting a suppression needs the contacts
                                        permission and is logged against the
                                        user and the time.
                                    </>
                                ),
                            },
                        ]}
                    />

                    <Callout
                        tone="tip"
                        title="The stop-messages link is signed"
                    >
                        Each contact gets a signed request link that opens a
                        public page — no sign-in — where the recipient confirms
                        they want no more messages. Keep that line in
                        promotional SMS campaigns.
                    </Callout>
                </SubSection>
            </Section>

            <Section
                id="sender-ids"
                title="Sender IDs"
                lead="What the recipient sees. A campaign cannot launch until the identity it broadcasts under is approved."
            >
                <Steps
                    items={[
                        {
                            title: 'Request the identity',
                            body: (
                                <>
                                    Add it under Sender IDs (SMS and email), or
                                    request a caller ID from{' '}
                                    <Mono>Settings → Resource requests</Mono>.
                                    Reviewers see the same organisation profile
                                    your account holds.
                                </>
                            ),
                        },
                        {
                            title: 'It enters review',
                            body: (
                                <>
                                    A new identity is <Mono>pending</Mono> and
                                    moves to <Mono>submitted</Mono> while the
                                    network or our review team checks it.
                                </>
                            ),
                        },
                        {
                            title: 'Approved or rejected',
                            body: (
                                <>
                                    An approved identity becomes selectable on a
                                    campaign; a rejected one keeps the
                                    reviewer’s reason so it can be corrected and
                                    resubmitted.
                                </>
                            ),
                        },
                        {
                            title: 'Pick it on the campaign',
                            body: (
                                <>
                                    The campaign form lists only identities that
                                    belong to your account. With none approved,
                                    submission is refused rather than sent from
                                    a shared default.
                                </>
                            ),
                        },
                    ]}
                />

                <FieldTable
                    columns={['Status', 'Meaning']}
                    rows={[
                        [
                            'Pending',
                            'Created on the account, not yet sent for review.',
                        ],
                        ['Submitted', 'With the network or our review team.'],
                        [
                            'Approved',
                            'Usable on campaigns — the only state that can launch.',
                        ],
                        [
                            'Rejected',
                            'Refused, with a reason; edit and resubmit.',
                        ],
                        [
                            'Suspended',
                            'Previously approved and later withdrawn, usually after a complaint.',
                        ],
                        [
                            'Expired',
                            'No longer valid, for example a lapsed registration.',
                        ],
                    ]}
                />

                <SubSection title="Rules the form enforces">
                    <FieldTable
                        columns={['Rule', 'Detail']}
                        rows={[
                            [
                                'SMS length',
                                '3 to 11 characters including spaces by default. Spaces are allowed and the case you typed is kept.',
                            ],
                            [
                                'Case sensitivity',
                                'OmniCast and omnicast are two different sender IDs on one account.',
                            ],
                            [
                                'Email sender',
                                'A full address of up to 120 characters, stored lower-case.',
                            ],
                            [
                                'Voice caller ID',
                                'A number or short code your voice provider has verified for you.',
                            ],
                        ]}
                        caption="These bounds are configuration, so a deployment may permit longer sender IDs or forbid spaces."
                    />
                </SubSection>
            </Section>

            <Section
                id="templates"
                title="Templates"
                lead="Reusable copy with placeholders, so the tenth reminder reads like the first."
            >
                <Para>
                    A template stores a channel, a name, a category and the body
                    — SMS text, an email subject and body, or a voice script.
                    Templates support the same <Mono>{'{{placeholder}}'}</Mono>{' '}
                    tokens as a campaign, so inserting one fills the campaign
                    editor and you finish the job there.
                </Para>

                <FieldTable
                    columns={['Status', 'Meaning']}
                    rows={[
                        [
                            'Draft',
                            'Your own working copy, visible to your team only.',
                        ],
                        [
                            'Pending',
                            'Submitted to the template catalogue and awaiting review.',
                        ],
                        [
                            'Active',
                            'Approved: usable by campaigns and listed in the catalogue.',
                        ],
                        [
                            'Rejected',
                            'Refused with a reason; edit and resubmit.',
                        ],
                        [
                            'Archived',
                            'Retired. Kept for history, hidden from pickers.',
                        ],
                    ]}
                />

                <Bullets
                    items={[
                        <>
                            <Mono>Templates → New</Mono> creates a private
                            template; <Mono>Customise</Mono> on a catalogue
                            template makes your own copy without touching the
                            original.
                        </>,
                        'Favourite the ones you use weekly — they sort to the top of the picker inside the campaign form.',
                        'Categories group the catalogue so long pickers stay manageable, and the catalogue grows as reviews approve submitted templates.',
                    ]}
                />
            </Section>

            <Section
                id="campaigns"
                title="Building a campaign"
                lead="One form, three content modes. Everything except the copy, the audience and the schedule has a sensible default."
            >
                <Steps
                    items={[
                        {
                            title: 'Name it and choose a channel',
                            body: (
                                <>
                                    The name is internal; the system reference
                                    is what reports, invoices and webhooks
                                    quote. Choose SMS, voice or email — the
                                    content panel changes with it.
                                </>
                            ),
                        },
                        {
                            title: 'Pick the audience',
                            body: (
                                <>
                                    Choose a contact list, optionally narrow it
                                    with a saved segment, or leave the list
                                    empty to address every broadcastable
                                    contact. Suppressed contacts and contacts
                                    missing an email address (for email) are
                                    excluded, and the estimate updates as you
                                    change it.
                                </>
                            ),
                        },
                        {
                            title: 'Write the content',
                            body: (
                                <>
                                    SMS copy, an email subject and body, or a
                                    voice script. Attach files or images to
                                    email, and set a recording, maximum duration
                                    and keypad menu for voice.
                                </>
                            ),
                        },
                        {
                            title: 'Set the schedule and retries',
                            body: (
                                <>
                                    Send immediately or at a chosen date and
                                    time in the campaign timezone. Retries
                                    decide how many times a failed recipient is
                                    attempted again, and after how long.
                                </>
                            ),
                        },
                        {
                            title: 'Check the estimate and save',
                            body: (
                                <>
                                    The form shows recipients, SMS parts and the
                                    estimated cost before anything is reserved.
                                    Save as draft, then submit when the copy is
                                    final.
                                </>
                            ),
                        },
                    ]}
                />

                <SubSection title="The fields">
                    <FieldTable
                        columns={['Field', 'Channel', 'Notes']}
                        rows={[
                            [
                                'Campaign name',
                                'All',
                                'Human label, shown in lists and reports.',
                            ],
                            [
                                'Channel',
                                'All',
                                'SMS, voice or email. Changing it resets content that belongs to the old channel.',
                            ],
                            [
                                'Audience',
                                'All',
                                'A contact list, or all broadcastable contacts when left empty.',
                            ],
                            [
                                'Segment',
                                'All',
                                'Optional saved filter applied on top of the list.',
                            ],
                            [
                                'Sender ID',
                                'SMS, email',
                                'Must be approved before the campaign can launch.',
                            ],
                            [
                                'Caller ID',
                                'Voice',
                                'The number the recipient sees.',
                            ],
                            [
                                'SMS message',
                                'SMS',
                                'Copy with optional {{tokens}}; the part count and encoding are calculated live.',
                            ],
                            [
                                'Email subject / body',
                                'Email',
                                'Subject line and rich-text or plain body, plus attachments and inline images.',
                            ],
                            [
                                'Voice script',
                                'Voice',
                                'Read out by the switch; keep it short enough to fit the maximum duration.',
                            ],
                            [
                                'Schedule',
                                'All',
                                'Immediate, or a date and time in the campaign timezone.',
                            ],
                            [
                                'Max retries',
                                'All',
                                'How many times a failed recipient is tried again.',
                            ],
                            [
                                'Retry delay',
                                'All',
                                'Minutes to wait between attempts.',
                            ],
                            [
                                'Internal notes',
                                'All',
                                'Context for reviewers and colleagues; never sent to recipients.',
                            ],
                        ]}
                    />
                </SubSection>

                <SubSection title="SMS: encoding, parts and cost">
                    <Para>
                        The editor counts characters in real time. Copy that
                        fits the GSM-7 alphabet (plain Latin letters, digits and
                        common punctuation) is sent as GSM-7 and costs one
                        segment for the first 160 characters; anything outside
                        it — a curly quote, an emoji, an accented character the
                        alphabet lacks — switches the whole message to Unicode,
                        where a segment holds far fewer characters.
                    </Para>

                    <FieldTable
                        columns={['Piece', 'How it is counted']}
                        rows={[
                            [
                                'Encoding',
                                'Chosen automatically per message, never per campaign.',
                            ],
                            [
                                'Segments',
                                'Long copy is split into concatenated segments, and each segment is billed.',
                            ],
                            [
                                'Personalisation',
                                'Tokens are filled in before the count, so a long first name can add a segment.',
                            ],
                            [
                                'Price',
                                'Segments × the SMS rate on your price list; the form shows the total before you submit.',
                            ],
                        ]}
                    />

                    <Callout
                        tone="tip"
                        title="Keep the stop line inside one segment"
                    >
                        A promotional message carries the opt-out line. Check
                        the part count after you add it — going from one segment
                        to two doubles the cost of the whole campaign.
                    </Callout>
                </SubSection>

                <SubSection title="Email: subject, body and files">
                    <Bullets
                        items={[
                            'A subject line, a body and a format: rich HTML from the editor, or plain text for maximum deliverability.',
                            'Attachments are uploaded from the campaign form and stored on the configured attachment disk; remove one before submitting and it is unlinked.',
                            'Inline images can be placed in the body from the editor, and are served from the application’s own image route.',
                            'Every address is validated, deduplicated and rate-limited per your sender domain settings, and bounces come back as events rather than silent failures.',
                        ]}
                    />
                </SubSection>

                <SubSection title="Voice: script, keypad and audio">
                    <FieldTable
                        columns={['Setting', 'What it controls']}
                        rows={[
                            [
                                'Voice script',
                                'The text the switch speaks, with {{tokens}} filled per recipient.',
                            ],
                            [
                                'Recording',
                                'An uploaded or generated audio file that can replace the spoken script; it needs reviewer approval before launch.',
                            ],
                            [
                                'Max duration',
                                'The hard ceiling for one call, in seconds.',
                            ],
                            [
                                'Keypad menu (IVR)',
                                'An optional prompt, a timeout, a number of repeats and what a press does.',
                            ],
                            [
                                'Opt-out digit',
                                'The key that suppresses the contact and ends the call; fixed for your account, not per campaign.',
                            ],
                        ]}
                    />

                    <Para>
                        Keypad actions are <Mono>connect</Mono> (bridge to an
                        agent), <Mono>repeat</Mono> (play the message again),{' '}
                        <Mono>opt_out</Mono> (suppress and hang up) and{' '}
                        <Mono>callback</Mono> (record a request for a return
                        call). An unrecognised press is logged as{' '}
                        <Mono>unrecognised</Mono> so the menu can be tuned.
                    </Para>
                </SubSection>

                <SubSection title="Personalisation tokens">
                    <FieldTable
                        columns={['Token', 'Value']}
                        rows={[
                            ['{{first_name}}', 'The contact’s first name.'],
                            ['{{last_name}}', 'The contact’s last name.'],
                            [
                                '{{full_name}}, {{name}}',
                                'First and last name together.',
                            ],
                            [
                                '{{phone}}, {{msisdn}}',
                                'The normalised phone number.',
                            ],
                            ['{{email}}', 'The email address.'],
                            [
                                '{{company}}, {{organisation}}',
                                'The company field.',
                            ],
                            ['{{location}}, {{city}}', 'The location field.'],
                            [
                                '{{account_number}}',
                                'Your own account identifier.',
                            ],
                            [
                                '{{customer_id}}',
                                'Your own customer identifier.',
                            ],
                        ]}
                        caption="A token with no stored value renders empty rather than printing the placeholder; aliases such as firstname and fullname are accepted."
                    />
                </SubSection>
            </Section>

            <Section
                id="approvals"
                title="Approval and money"
                lead="Submitting reserves credit, approving frees the campaign to run, launching captures what it actually spends."
            >
                <FieldTable
                    columns={['Status', 'Meaning', 'What you can do']}
                    rows={[
                        [
                            'Draft',
                            'Being written; nothing reserved and nothing sent.',
                            'Edit, submit, delete.',
                        ],
                        [
                            'Pending approval',
                            'Submitted. Its estimated cost is reserved from the wallet.',
                            'Wait, edit and resubmit, or have a reviewer decide.',
                        ],
                        [
                            'Approved',
                            'Freed to run. An immediate campaign launches; a scheduled one waits for its slot.',
                            'Launch, cancel, duplicate.',
                        ],
                        [
                            'Rejected',
                            'A reviewer refused it with a reason. The reservation is released.',
                            'Read the reason, fix the copy, resubmit.',
                        ],
                        [
                            'Scheduled',
                            'Approved and waiting for its date and time.',
                            'Cancel.',
                        ],
                        [
                            'Running',
                            'Workers are sending, paced by the channel’s submissions per second.',
                            'Cancel if something is wrong; a running campaign cannot be paused, only cancelled.',
                        ],
                        [
                            'Paused',
                            'Sending was halted mid-flight; recipients already sent stay sent.',
                            'Cancel to stop it for good, or ask support to resume it.',
                        ],
                        [
                            'Completed',
                            'Every recipient reached a terminal state.',
                            'Duplicate for the next round.',
                        ],
                        [
                            'Failed',
                            'The campaign could not finish; the reason is on the campaign.',
                            'Read the log, duplicate with a fix.',
                        ],
                        [
                            'Cancelled',
                            'Stopped by a user. Unspent reservation is released.',
                            'Duplicate.',
                        ],
                    ]}
                />

                <SubSection title="Who decides">
                    <Para>
                        A campaign launches on its assets rather than on a
                        signature. When the sender ID it broadcasts under (SMS)
                        or its audio (voice) is approved, the campaign approves
                        itself and can launch. It only appears in the{' '}
                        <Mono>Approvals</Mono> queue when your organisation is
                        set to require a human decision — three switches control
                        that, and each can be set per organisation.
                    </Para>

                    <FieldTable
                        columns={['Switch', 'Default', 'Effect']}
                        rows={[
                            [
                                'Require approval',
                                'Off',
                                'On: every submitted campaign waits for a reviewer before it can launch.',
                            ],
                            [
                                'Require approved sender ID',
                                'On',
                                'Off: submission is allowed while the sender ID is still in review.',
                            ],
                            [
                                'Require approved audio',
                                'On',
                                'Off: a voice campaign may launch before its recording is approved.',
                            ],
                        ]}
                        caption="Deciding a campaign needs the approval permission. While it is pending, the campaign waits, and its reservation stays held."
                    />
                </SubSection>

                <SubSection title="The money trail">
                    <FieldTable
                        columns={['Moment', 'Wallet effect']}
                        rows={[
                            [
                                'Submit',
                                'Reserve — the estimate moves from balance to reserved; it is unavailable to other campaigns but not yet spent.',
                            ],
                            [
                                'Launch',
                                'Capture — the reserved amount is debited as the campaign actually consumes it.',
                            ],
                            [
                                'Reject or cancel',
                                'Release — the reservation returns to your available balance.',
                            ],
                            [
                                'Per-recipient outcome',
                                'The final cost equals what was actually sent; any unused reservation is released when the campaign completes.',
                            ],
                        ]}
                    />

                    <Callout
                        tone="warning"
                        title="A reservation is not a payment"
                    >
                        You are charged for messages that were actually
                        submitted, per segment, and for the billable duration of
                        voice calls. The reserve exists so two campaigns cannot
                        both promise the same credit.
                    </Callout>
                </SubSection>

                <Bullets
                    items={[
                        'Duplicate copies a campaign, its audience and its content into a fresh draft — useful after a rejection.',
                        'Every status change is written to the campaign’s version history with the user and time, so “who approved this?” is answerable.',
                        'A reviewer who rejects must supply a reason; the submitter sees it on the campaign and in the rejection email.',
                    ]}
                />
            </Section>

            <Section
                id="delivery"
                title="Delivery and monitoring"
                lead="What happens after launch, and how to read the numbers on a campaign page."
            >
                <Para>
                    Launching hands the campaign to the queue. Workers claim
                    recipients in order, hold each channel to its submissions
                    per second, and write the outcome back per recipient as the
                    provider or switch reports it. Nothing is sent inside your
                    browser request, so closing the tab never interrupts a send.
                </Para>

                <FieldTable
                    columns={['Recipient status', 'Meaning']}
                    rows={[
                        [
                            'Pending',
                            'Queued for this campaign, not yet attempted.',
                        ],
                        ['Queued', 'Handed to the channel worker.'],
                        [
                            'Sent',
                            'Accepted by the provider; the receipt has not arrived yet.',
                        ],
                        [
                            'Delivered',
                            'A receipt confirmed it reached the handset or mailbox.',
                        ],
                        [
                            'Failed',
                            'The provider or switch refused it; retries apply if the campaign allows them.',
                        ],
                        [
                            'Skipped',
                            'Deliberately not sent — suppressed, missing an address for the channel, or past the retry budget.',
                        ],
                    ]}
                />

                <SubSection title="Pacing, retries and stalled campaigns">
                    <Bullets
                        items={[
                            'Each channel has a submissions-per-second ceiling — the override set for your organisation when there is one, otherwise the default for the deployment. Zero means unthrottled.',
                            'A failed recipient is retried up to the campaign’s maximum retries, spaced by its retry delay, on the same channel queue.',
                            'Scheduled campaigns are released by a command the scheduler runs every minute, so a future date fires on time only while the scheduler is running.',
                            'A campaign that goes quiet is re-queued by a sweep that runs every few minutes, so a briefly stalled campaign heals itself.',
                        ]}
                    />
                </SubSection>

                <SubSection title="Receipts">
                    <Para>
                        SMS receipts, voice call events and email bounces arrive
                        on the provider webhook endpoints, signed with a shared
                        secret. A delivered receipt promotes the recipient; an
                        undelivered, expired or rejected one re-opens the
                        campaign and re-queues the retry until the budget is
                        spent. Unknown references are acknowledged with{' '}
                        <Mono>202</Mono> and ignored, so a provider’s retry
                        cannot move a campaign it never sent.
                    </Para>
                </SubSection>

                <Callout tone="note" title="One reference everywhere">
                    A campaign’s reference is quoted by the wallet statement,
                    the invoice, the delivery reports and every webhook — which
                    is what makes a support question answerable from one string.
                </Callout>
            </Section>

            <Section
                id="wallet"
                title="Wallet, top-ups and invoices"
                lead="One balance covers SMS, voice and email. You choose the channel when you build the campaign, not when you pay."
            >
                <FieldTable
                    columns={['Figure', 'Meaning']}
                    rows={[
                        [
                            'Balance',
                            'All credit on the account, including what is reserved.',
                        ],
                        [
                            'Reserved',
                            'Pledged to submitted campaigns that have not launched or been released.',
                        ],
                        [
                            'Available',
                            'Balance minus reserved — what a new campaign can actually spend.',
                        ],
                        [
                            'Low-balance threshold',
                            'The level below which the wallet warns you.',
                        ],
                    ]}
                />

                <Steps
                    items={[
                        {
                            title: 'Choose an amount',
                            body: (
                                <>
                                    Pick a package or drag the slider to any
                                    amount between the configured minimum and
                                    maximum. The page shows what that amount
                                    buys on each channel at the current rates.
                                </>
                            ),
                        },
                        {
                            title: 'Pay',
                            body: (
                                <>
                                    Card payment opens the gateway checkout and
                                    returns to the wallet when it completes.
                                    Mobile money prompts the number you enter
                                    and asks for the one-time code the provider
                                    issues.
                                </>
                            ),
                        },
                        {
                            title: 'See it land',
                            body: (
                                <>
                                    Credit is written when the gateway confirms,
                                    as a credit transaction carrying the payment
                                    reference. A pending payment that fails or
                                    is abandoned writes nothing.
                                </>
                            ),
                        },
                    ]}
                />

                <FieldTable
                    columns={['Mobile money provider', 'Stored value']}
                    rows={[
                        ['MTN Mobile Money', 'mtn'],
                        ['Telecel Cash', 'vod'],
                        ['AirtelTigo Money', 'atl'],
                    ]}
                    caption="Card payments are handled by the payment gateway configured for OmniCast; the wallet shows the resulting invoice either way."
                />

                <SubSection title="Statements and invoices">
                    <Bullets
                        items={[
                            'Transactions carry a type: credit, debit, reservation, release, refund or adjustment, each with its campaign or payment reference.',
                            'Invoices are downloadable and hold a status of paid, refunded or void; a refund writes the matching wallet entry.',
                            'Dynamic pricing, where it applies to your account, prices a custom amount from the rate card instead of the package table.',
                            'Packages, dynamic pricing and rate cards are published by us; the rates that apply to your organisation are on the public price list.',
                        ]}
                    />
                </SubSection>
            </Section>

            <Section
                id="reports"
                title="Reports and exports"
                lead="Delivery, spend and quality, sliced by campaign, channel or contact."
            >
                <FieldTable
                    columns={['Screen', 'What it answers']}
                    rows={[
                        [
                            'Reports',
                            'The overview: volume, delivery rate and spend over the period, per channel.',
                        ],
                        [
                            'Campaign report',
                            'Which campaigns ran, what they cost and how much of each audience was reached.',
                        ],
                        [
                            'Contacts report',
                            'Growth, reachability and duplicate quality of the address book.',
                        ],
                        [
                            'SMS messages',
                            'Per-message log: recipient, sender, encoding, segments, status and provider reference.',
                        ],
                        [
                            'Voice calls',
                            'Per-call log: duration, outcome, keypad presses and opt-outs.',
                        ],
                        [
                            'Email messages',
                            'Per-message log: recipient, subject, opens, bounces and provider response.',
                        ],
                    ]}
                />

                <SubSection title="Exports">
                    <Steps
                        items={[
                            {
                                title: 'Pick a report and a period',
                                body: (
                                    <>
                                        Every report accepts a date range and
                                        the filters its screen already offers.
                                    </>
                                ),
                            },
                            {
                                title: 'Queue the export',
                                body: (
                                    <>
                                        The file is generated in the background
                                        and appears under{' '}
                                        <Mono>Reports → Report exports</Mono>{' '}
                                        with a status: pending, processing,
                                        completed or failed.
                                    </>
                                ),
                            },
                            {
                                title: 'Download it',
                                body: (
                                    <>
                                        When the status is completed the
                                        download link is available to your
                                        organisation; a failed export shows the
                                        reason.
                                    </>
                                ),
                            },
                        ]}
                    />

                    <Callout tone="tip" title="Exports are permissioned">
                        Queueing an export needs <Mono>reports.export</Mono>,
                        and a download link is scoped to your organisation — a
                        copied URL does not leak another organisation’s data.
                    </Callout>
                </SubSection>
            </Section>

            <Section
                id="webhooks"
                title="Webhook subscriptions"
                lead="Push campaign and delivery events into your own systems instead of polling reports."
            >
                <Steps
                    items={[
                        {
                            title: 'Create the subscription',
                            body: (
                                <>
                                    Settings → Webhooks takes an HTTPS URL and
                                    the events you want. A subscription for your
                                    organisation sees only your organisation’s
                                    campaigns.
                                </>
                            ),
                        },
                        {
                            title: 'Copy the signing secret',
                            body: (
                                <>
                                    The <Mono>whsec_…</Mono> secret is shown
                                    once, at creation. Store it in your own
                                    secret manager; rotating issues a new one
                                    and invalidates the old immediately.
                                </>
                            ),
                        },
                        {
                            title: 'Send a test event',
                            body: (
                                <>
                                    The test button posts a sample payload so
                                    you can watch your endpoint accept it before
                                    a real campaign depends on it.
                                </>
                            ),
                        },
                        {
                            title: 'Watch the delivery log',
                            body: (
                                <>
                                    Every attempt is recorded with its response
                                    status, a truncated response body and the
                                    next retry time, so a failing endpoint can
                                    be fixed before the next campaign depends on
                                    it.
                                </>
                            ),
                        },
                    ]}
                />

                <FieldTable
                    columns={['Event', 'Fires when']}
                    rows={[
                        ['campaign.created', 'A campaign is first saved.'],
                        [
                            'campaign.submitted',
                            'It is submitted for approval and its cost is reserved.',
                        ],
                        ['campaign.approved', 'A reviewer approves it.'],
                        [
                            'campaign.rejected',
                            'A reviewer rejects it, with the reason in the payload.',
                        ],
                        ['campaign.launched', 'Sending begins.'],
                        [
                            'campaign.completed',
                            'Every recipient reached a terminal state.',
                        ],
                        ['campaign.cancelled', 'A user cancels it.'],
                        [
                            'sms.delivered',
                            'A handset receipt confirms SMS delivery.',
                        ],
                        [
                            'sms.failed',
                            'A receipt reports an undelivered, expired or rejected message.',
                        ],
                        [
                            'email.delivered',
                            'The receiving server accepted the message.',
                        ],
                        ['email.bounced', 'The message bounced.'],
                    ]}
                />

                <SubSection title="Delivery behaviour">
                    <FieldTable
                        columns={['Setting', 'Default']}
                        rows={[
                            [
                                'Attempts per event',
                                '5, queued rather than sent inside the request that caused it.',
                            ],
                            [
                                'Backoff',
                                '30 seconds, 2 minutes, 10 minutes, 30 minutes.',
                            ],
                            [
                                'Request timeout',
                                '15 seconds to respond, 5 to connect.',
                            ],
                            [
                                'Auto-disable',
                                'After 10 consecutive failures the endpoint is switched off until it is re-enabled.',
                            ],
                            [
                                'Retention',
                                'Delivery records are pruned after 30 days.',
                            ],
                        ]}
                    />
                    <Para>
                        Answer with any 2xx status to acknowledge. A 4xx or 5xx,
                        a timeout or a connection error counts as a failed
                        attempt and schedules the next one.
                    </Para>
                </SubSection>

                <SubSection title="Verifying the signature">
                    <Para>
                        Every request carries <Mono>X-Omnicast-Signature</Mono>,{' '}
                        <Mono>X-Omnicast-Event</Mono>,{' '}
                        <Mono>X-Omnicast-Delivery</Mono> and{' '}
                        <Mono>X-Omnicast-Attempt</Mono>. The signature is{' '}
                        <Mono>t=&lt;unix&gt;,v1=&lt;hmac&gt;</Mono> where the
                        HMAC is SHA-256 over <Mono>timestamp.rawBody</Mono>{' '}
                        keyed with your endpoint secret. Compare it in constant
                        time and reject a timestamp older than five minutes.
                    </Para>

                    <CodeBlock
                        title="Verify in PHP"
                        language="php"
                        code={`$header = $_SERVER['HTTP_X_OMNICAST_SIGNATURE'] ?? '';
$parts = [];
foreach (explode(',', $header) as $piece) {
    [$key, $value] = array_pad(explode('=', $piece, 2), 2, '');
    $parts[$key] = $value;
}

$timestamp = (int) ($parts['t'] ?? 0);
$expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
$fresh = abs(time() - $timestamp) <= 300;

if (! $fresh || ! hash_equals($expected, $parts['v1'] ?? '')) {
    http_response_code(400);
    exit;
}`}
                    />

                    <CodeBlock
                        title="Verify in Node.js"
                        language="javascript"
                        code={`import { createHmac, timingSafeEqual } from 'node:crypto';

const header = req.get('X-Omnicast-Signature') ?? '';
const parts = Object.fromEntries(
    header.split(',').map((piece) => piece.split('=')),
);

const timestamp = Number(parts.t ?? 0);
const expected = createHmac('sha256', process.env.OMNICAST_WEBHOOK_SECRET)
    .update(timestamp + '.' + req.rawBody)
    .digest('hex');

const fresh = Math.abs(Date.now() / 1000 - timestamp) <= 300;
const matches =
    expected.length === (parts.v1 ?? '').length &&
    timingSafeEqual(Buffer.from(expected), Buffer.from(parts.v1 ?? ''));

if (!fresh || !matches) {
    res.status(400).send('invalid signature');
    return;
}`}
                    />
                </SubSection>
            </Section>

            <Section
                id="api-tokens"
                title="API tokens"
                lead="How a script or a dashboard integration reads your campaigns over the HTTP API."
            >
                <Steps
                    items={[
                        {
                            title: 'Create a token',
                            body: (
                                <>
                                    Settings → API tokens takes a name (what
                                    will use it) and the abilities it may hold.
                                </>
                            ),
                        },
                        {
                            title: 'Copy it once',
                            body: (
                                <>
                                    The plain token is shown a single time. Only
                                    its hash is stored, so a lost token is
                                    replaced rather than recovered.
                                </>
                            ),
                        },
                        {
                            title: 'Send it as a bearer token',
                            body: (
                                <>
                                    <Mono>
                                        Authorization: Bearer &lt;token&gt;
                                    </Mono>{' '}
                                    on every request to{' '}
                                    <Mono>{apiBaseUrl}</Mono>.
                                </>
                            ),
                        },
                        {
                            title: 'Revoke when done',
                            body: (
                                <>
                                    Revoking is immediate: every request
                                    carrying that token starts answering{' '}
                                    <Mono>401</Mono>.
                                </>
                            ),
                        },
                    ]}
                />

                <FieldTable
                    columns={['Ability', 'Grants']}
                    rows={[
                        [
                            'read',
                            'List and read campaigns the token’s user is allowed to see.',
                        ],
                        [
                            'write',
                            'Reserved for endpoints that create or change records; a read-only token is refused with 403.',
                        ],
                    ]}
                    caption="A token inherits the scope of the user who created it, so it only ever reads your own organisation’s campaigns."
                />

                <Para>
                    Rate limits, pagination, the response shape and the full
                    field reference live in the{' '}
                    <Link
                        href={apiDocs()}
                        className="font-medium text-brand hover:underline"
                    >
                        API documentation
                    </Link>
                    .
                </Para>
            </Section>

            <Section
                id="smpp"
                title="SMPP submissions"
                lead="For integrations that speak SMPP directly rather than HTTP: OmniCast binds as an SMSC."
            >
                <Para>
                    An SMPP account with a system ID and password is issued to
                    your organisation when SMPP access is requested. An approved
                    ESME binds to the listener — a separate service, not the web
                    server — and submits <Mono>submit_sm</Mono> PDUs. Each
                    message is stored, queued to the SMS transport and answered
                    with a <Mono>submit_sm_resp</Mono>; the delivery receipt
                    comes back to the same session as a <Mono>deliver_sm</Mono>.
                </Para>

                <FieldTable
                    columns={['Piece', 'Detail']}
                    rows={[
                        ['Bind modes', 'transmitter, receiver or transceiver.'],
                        ['Default port', '2775.'],
                        [
                            'Source address',
                            'Taken from the PDU’s source address; a configured default applies when it is empty.',
                        ],
                        [
                            'Long messages',
                            'Concatenated PDUs are reassembled before the message is stored.',
                        ],
                        [
                            'Receipts',
                            'deliver_sm PDUs carry the message ID the submit response returned.',
                        ],
                        [
                            'Session limits',
                            'Concurrent bind ceiling, an enquire_link every 30 seconds, an idle timeout, and a maximum session lifetime.',
                        ],
                        [
                            'Kill switch',
                            'With the listener disabled every bind is refused with ESME_RBINDFAIL rather than accepted and dropped.',
                        ],
                    ]}
                />

                <FieldTable
                    columns={['Message status', 'Meaning']}
                    rows={[
                        [
                            'Accepted',
                            'Stored from the PDU, not yet handed to the transport.',
                        ],
                        ['Submitted', 'Handed to the SMS transport.'],
                        ['Delivered', 'The transport confirmed delivery.'],
                        [
                            'Failed',
                            'The transport refused it, or it ran out of retries.',
                        ],
                        [
                            'Rejected',
                            'Refused outright — for example a suppressed destination.',
                        ],
                    ]}
                />

                <Callout tone="note" title="Same wallet, same reports">
                    SMS that arrives over SMPP is billed and reported like any
                    other message: it appears in the SMS message log, in the
                    campaign-independent volume on the reports, and in the
                    wallet statement for your organisation.
                </Callout>
            </Section>

            <Section
                id="account"
                title="Your account and security"
                lead="Everything under the avatar menu and the Settings group."
            >
                <FieldTable
                    columns={['Screen', 'What lives there']}
                    rows={[
                        [
                            'Profile',
                            'Your name, email address and the details shown to colleagues.',
                        ],
                        [
                            'Security',
                            'Password, two-factor authentication with recovery codes, and passkeys.',
                        ],
                        [
                            'Sessions',
                            'Every device currently signed in, with the ability to end one or all others.',
                        ],
                        [
                            'Appearance',
                            'Light, dark or system theme; the choice is stored per user.',
                        ],
                        [
                            'Organisation',
                            'Company details, timezone, default language and compliance documents you upload for review.',
                        ],
                        [
                            'Resource requests',
                            'Ask for a new sender ID, caller ID or sending domain and follow its status.',
                        ],
                        ['Team', 'Members, roles and outstanding invitations.'],
                        [
                            'API tokens',
                            'Personal access tokens and their abilities.',
                        ],
                        [
                            'Webhooks',
                            'Outbound subscriptions and their delivery history.',
                        ],
                    ]}
                />

                <Bullets
                    items={[
                        'Uploading a new compliance document replaces the current one for review; the status shows pending, approved or rejected with the reason.',
                        'Ending other sessions is the right response to a lost laptop — it does not change your password.',
                        'Two-factor authentication and passkeys are optional but recommended for anyone who can move money or launch campaigns.',
                    ]}
                />
            </Section>

            <Section
                id="troubleshooting"
                title="Troubleshooting"
                lead="The messages people actually hit, and what they mean."
            >
                <FieldTable
                    columns={['Symptom', 'Likely cause', 'What to do']}
                    rows={[
                        [
                            '“Submit” is refused on a campaign',
                            'No approved sender ID, or a required field is empty.',
                            'Check the sender ID status on the Sender IDs screen, then the inline field errors on the form.',
                        ],
                        [
                            'Insufficient credit',
                            'The estimate is larger than the wallet’s available balance.',
                            'Top up, or narrow the audience. The reserve, not the balance, is what blocks.',
                        ],
                        [
                            'Campaign sits in pending approval',
                            'Your organisation requires a human decision before a campaign may launch.',
                            'Wait for the reviewer to decide — the campaign page and your notifications show it as pending until then.',
                        ],
                        [
                            'Voice campaign will not launch',
                            'The recording or script has not been approved.',
                            'Have the recording approved, or ask support whether the audio requirement applies to your organisation.',
                        ],
                        [
                            'Recipients show as skipped',
                            'Suppressed contacts, or missing email addresses on an email campaign.',
                            'Check the do-not-disturb registry and the contact’s email field.',
                        ],
                        [
                            'Recipients stay pending after launch',
                            'The send has not started yet.',
                            'Give it a minute; if nothing moves, contact support with the campaign reference.',
                        ],
                        [
                            'A scheduled campaign never fired',
                            'The date has not arrived in your organisation’s timezone.',
                            'Check the timezone on Settings → Organisation and the scheduled time on the campaign; contact support if the slot has passed.',
                        ],
                        [
                            'Webhook deliveries failing',
                            'Endpoint returns a non-2xx, times out, or rejects the signature.',
                            'Read the response in the delivery log, fix the endpoint, then re-enable it if it was auto-disabled. Past events are not resent, so the next campaign is the first clean test.',
                        ],
                        [
                            'Import skips rows',
                            'A phone number could not be normalised.',
                            'Fix the number to international format and re-upload just those rows.',
                        ],
                        [
                            'One-time code never arrives',
                            'SMS provider unavailable, or the address is wrong.',
                            'The signup screen reports an unavailable provider; retry with email delivery.',
                        ],
                        [
                            'A colleague cannot see a menu',
                            'Their role lacks the permission.',
                            'A client administrator adjusts the role on Settings → Team.',
                        ],
                    ]}
                />
            </Section>

            <Section
                id="glossary"
                title="Glossary"
                lead="The words this manual and the API documentation share."
            >
                <FieldTable
                    columns={['Term', 'Meaning']}
                    rows={[
                        [
                            'Campaign',
                            'One piece of copy sent to one audience on one schedule.',
                        ],
                        [
                            'Audience',
                            'The list, segment or full contact book a campaign targets.',
                        ],
                        [
                            'Segment (audience)',
                            'A saved filter, re-evaluated each time it is used.',
                        ],
                        [
                            'Sender ID',
                            'The identity a message arrives from, and its approval state.',
                        ],
                        [
                            'Reservation',
                            'Credit held for a submitted campaign that has not spent it yet.',
                        ],
                        [
                            'Capture',
                            'Turning reserved credit into an actual charge at launch.',
                        ],
                        [
                            'DND',
                            'Do not disturb: a suppression that blocks every channel.',
                        ],
                        [
                            'Segment (SMS)',
                            'One billed chunk of an SMS; unrelated to an audience segment.',
                        ],
                        [
                            'GSM-7 / Unicode',
                            'The two SMS encodings; Unicode fits fewer characters per segment.',
                        ],
                        [
                            'DLR',
                            'Delivery receipt: the provider’s confirmation that a message arrived.',
                        ],
                        [
                            'SMPP / ESME',
                            'The SMS protocol, and the external system that binds to it to submit messages.',
                        ],
                        [
                            'submit_sm / deliver_sm',
                            'The PDU that submits a message, and the one that returns its receipt.',
                        ],
                        [
                            'Webhook delivery',
                            'One attempt to post an event to a subscriber’s URL, with its response recorded.',
                        ],
                        [
                            'Personal access token',
                            'The bearer credential the HTTP API uses.',
                        ],
                    ]}
                />
            </Section>
        </DocShell>
    );
}
