export type SelectOption = {
    value: string;
    label: string;
};

export type ClientOption = {
    id: number;
    name: string;
};

export type ContactListOption = {
    id: number;
    name: string;
    contacts_count: number;
};

/** A row of the contact list library. */
export type ContactListSummary = {
    id: number;
    name: string;
    description: string | null;
    client: string | null;
    contacts_count: number;
    campaigns_count: number;
    updated_at: string | null;
};

/** The header of a single contact list. */
export type ContactListDetail = {
    id: number;
    name: string;
    description: string | null;
    client_name: string | null;
    campaigns_count: number;
    created_at: string | null;
    updated_at: string | null;
};

/** A member of a contact list. */
export type ContactListMember = {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    company: string | null;
    dnd: boolean;
    added_at: string | null;
};

/** A contact in the book that can still be added to a list. */
export type ContactListCandidate = {
    id: number;
    name: string;
    phone: string;
    email: string | null;
};

export type TagOption = {
    id: number;
    name: string;
    color: string;
    contacts_count?: number;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

export type ImportResult = {
    created: number;
    updated: number;
    skipped: number;
};

export type CampaignRow = {
    id: number;
    reference: string;
    name: string;
    client: string | null;
    channel: string;
    channel_label: string;
    status: string;
    status_label: string;
    audience: string;
    sender: string | null;
    estimated_recipients: number | null;
    estimated_sms_parts: number | null;
    estimated_cost: number;
    reserved_amount: number;
    actual_cost: number;
    scheduled_at: string | null;
    created_at: string | null;
};

export type CampaignDetail = CampaignRow & {
    sms_message: string | null;
    email_subject: string | null;
    email_body: string | null;
    email_format: string | null;
    email_attachments: EmailAttachmentRow[];
    voice_script: string | null;
    voice_recording_path: string | null;
    voice_max_duration_seconds: number | null;
    ivr_enabled: boolean;
    ivr_prompt: string | null;
    ivr_timeout_seconds: number;
    ivr_max_attempts: number;
    ivr_options: Record<string, string>;
    timezone: string | null;
    schedule_type: string | null;
    max_retries: number | null;
    retry_delay_minutes: number | null;
    notes: string | null;
    rejection_reason: string | null;
    creator: string | null;
    approver: string | null;
    caller: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    launched_at: string | null;
    completed_at: string | null;
};

export type CampaignRecipient = {
    id: number;
    name: string | null;
    phone: string | null;
    email: string | null;
    status: string;
    status_label: string;
    attempts: number;
    cost: number;
    error_message: string | null;
    sent_at: string | null;
};

export type CampaignVersionRow = {
    id: number;
    version: number;
    status: string;
    status_label: string;
    audience_size: number;
    estimated_cost: number;
    fingerprint: string;
    frozen_at: string | null;
    author: string | null;
};

export type VoiceCallRow = {
    id: number;
    reference: string;
    to: string;
    from: string;
    contact: string | null;
    status: string;
    status_label: string;
    attempts: number;
    duration_seconds: number;
    dtmf_digits: string | null;
    ivr_action: string | null;
    ivr_action_label: string | null;
    dialled_at: string | null;
    ended_at: string | null;
    error_message: string | null;
};

export type VoiceDigitRow = {
    digit: string | null;
    action: string | null;
    action_label: string | null;
    total: number;
};

export type VoiceCallTotals = {
    calls: number;
    answered: number;
    no_answer: number;
    busy: number;
    failed: number;
    canceled: number;
    answer_rate: number | null;
    average_duration: number;
    opt_outs: number;
    spend: number;
};

export type VoiceCallReport = {
    totals: VoiceCallTotals;
    digits: VoiceDigitRow[];
    recent: VoiceCallRow[];
};

export type SmsMessageRow = {
    id: number;
    reference: string;
    to: string;
    from: string;
    contact: string | null;
    status: string;
    status_label: string;
    provider_status: string | null;
    segments: number;
    attempts: number;
    submitted_at: string | null;
    delivered_at: string | null;
    error_message: string | null;
};

export type SmsMessageTotals = {
    messages: number;
    delivered: number;
    pending: number;
    undelivered: number;
    expired: number;
    rejected: number;
    failed: number;
    delivery_rate: number | null;
    segments: number;
    spend: number;
};

export type SmsMessageReport = {
    totals: SmsMessageTotals;
    recent: SmsMessageRow[];
};

export type EmailMessageRow = {
    id: number;
    reference: string;
    to: string;
    from: string;
    contact: string | null;
    subject: string;
    status: string;
    status_label: string;
    provider_status: string | null;
    attempts: number;
    submitted_at: string | null;
    delivered_at: string | null;
    error_message: string | null;
};

export type EmailMessageTotals = {
    messages: number;
    delivered: number;
    pending: number;
    bounced: number;
    complained: number;
    rejected: number;
    failed: number;
    delivery_rate: number | null;
    bounce_rate: number | null;
    spend: number;
};

export type EmailMessageReport = {
    totals: EmailMessageTotals;
    recent: EmailMessageRow[];
};

export type CampaignFormOptions = {
    clients: ClientOption[];
    tenantClientId: number | null;
    contactLists: ContactListOption[];
    contactSegments: { id: number; name: string; contacts_count: number }[];
    senderIds: { id: number; name: string; channel: string }[];
    callerIds: { id: number; number: string; label: string | null }[];
    channels: SelectOption[];
    outboundCallerId: string | null;
    templates: CampaignTemplateOption[];
};

/**
 * A template as the campaign editor needs it: enough to preview the copy the
 * picker offers and to drop it into the campaign body.
 */
export type CampaignTemplateOption = {
    id: number;
    name: string;
    channel: string;
    channel_label: string;
    subject: string | null;
    body: string;
    email_format: string;
    category: string | null;
    variables: string[];
    is_system: boolean;
    usage_count: number;
};

/**
 * A file already stored against a campaign, as the editor and the review
 * screens list it. The bytes never travel with the page: `download_url` is a
 * guarded route.
 */
export type EmailAttachmentRow = {
    id: number;
    name: string;
    size: string;
    mime_type: string;
    is_image: boolean;
    download_url: string;
};

export type CampaignFormValues = {
    name: string;
    channel: string;
    contact_list_id: string;
    contact_segment_id: string;
    sender_id: string;
    caller_id: string;
    message_template_id: string;
    sms_message: string;
    email_subject: string;
    email_body: string;
    email_format: string;
    email_attachments: File[];
    removed_attachment_ids: number[];
    voice_script: string;
    voice_max_duration_seconds: string;
    ivr_enabled: string;
    ivr_prompt: string;
    ivr_timeout_seconds: string;
    ivr_max_attempts: string;
    ivr_options: Record<string, string>;
    schedule_type: string;
    scheduled_at: string;
    timezone: string;
    max_retries: string;
    retry_delay_minutes: string;
    notes: string;
    client_id: string;
    idempotency_key: string;
};

export type SenderIdRow = {
    id: number;
    name: string;
    channel: string;
    channel_label: string;
    identifier_label: string;
    country: string | null;
    purpose: string | null;
    status: string;
    status_label: string;
    requested_at: string | null;
    approved_at: string | null;
    rejection_reason: string | null;
    requester: string | null;
    client: string | null;
    campaigns_count: number;
    can: {
        update: boolean;
        delete: boolean;
        approve: boolean;
    };
};

export type SenderIdChannelOption = {
    value: string;
    label: string;
    description: string;
    identifier_label: string;
    placeholder: string;
    hint: string;
    requires_country: boolean;
    max_length: number;
};

export type SenderIdFormValues = {
    channel: string;
    name: string;
    country: string;
    purpose: string;
    client_id: string;
};

export type ReadinessIssue = {
    key: string;
    label: string;
    detail: string;
};

export type CampaignReview = {
    requires_audio: boolean;
    requires_campaign_approval: boolean;
    audio_status: string;
    audio_status_label: string;
    audio_rejection_reason: string | null;
    audio_reviewed_at: string | null;
    audio_reviewer: string | null;
    outbound_caller: string | null;
    blockers: ReadinessIssue[];
};

export type ApprovalCampaignRow = {
    id: number;
    reference: string;
    name: string;
    client: string | null;
    channel: string;
    channel_label: string;
    audience: string;
    sender: string | null;
    recipients: number;
    estimated_cost: number;
    submitted_at: string | null;
    blockers: string[];
};

export type ApprovalAudioRow = {
    id: number;
    reference: string;
    name: string;
    client: string | null;
    channel_label: string;
    voice_script: string | null;
    voice_recording_path: string | null;
    voice_max_duration_seconds: number;
    submitted_at: string | null;
    blockers: string[];
};

export type ApprovalSenderIdRow = {
    id: number;
    name: string;
    channel: string;
    channel_label: string;
    identifier_label: string;
    country: string | null;
    purpose: string | null;
    client: string | null;
    requester: string | null;
    requested_at: string | null;
};

export type ApprovalQueue<T> = {
    items: T[];
    total: number;
};

export type ContactRow = {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    company: string | null;
    account_number: string | null;
    location: string | null;
    dnd: boolean;
    dnd_at: string | null;
    lists: string[];
    tags: string[];
    created_at: string | null;
};

export type ContactChannelQuality = {
    channel: string;
    eligible: number;
    blocked: number;
};

export type ContactQualityReport = {
    total: number;
    broadcastable: number;
    dnd: number;
    missing_email: number;
    invalid_email: number;
    duplicate_emails: number;
    duplicate_contacts: number;
    suppressed: number;
    added_last_30_days: number;
    channels: ContactChannelQuality[];
};

export type ContactDuplicateMember = {
    id: number;
    name: string;
    phone: string;
    dnd: boolean;
    created_at: string | null;
};

export type ContactDuplicateGroup = {
    email: string;
    contacts: ContactDuplicateMember[];
};

export type ContactSuppressionRow = {
    id: number;
    channel: string;
    identifier: string;
    reason: string;
    reason_label: string;
    source: string;
    is_global: boolean;
    suppressed_at: string | null;
};

export type ContactSegmentRow = {
    id: number;
    name: string;
    description: string | null;
    filters: Record<string, unknown>;
    contacts_count: number;
};

export type ContactEligibilitySnapshot = {
    eligible: boolean;
    reason: string | null;
    suppression_id: number | null;
};

export type ContactDetail = {
    id: number;
    name: string;
    first_name: string | null;
    last_name: string | null;
    phone: string;
    original_phone: string | null;
    alternative_phone: string | null;
    email: string | null;
    company: string | null;
    account_number: string | null;
    customer_id: string | null;
    location: string | null;
    language: string | null;
    timezone: string | null;
    dnd: boolean;
    dnd_at: string | null;
    custom_fields: Record<string, unknown>;
    lists: string[];
    tags: { id: number; name: string; color: string }[];
    creator: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type ContactCampaignHistory = {
    id: number;
    name: string;
    reference: string;
    channel: string;
    status: string;
};

export type ContactFormValues = {
    first_name: string;
    last_name: string;
    phone: string;
    alternative_phone: string;
    email: string;
    company: string;
    account_number: string;
    customer_id: string;
    location: string;
    language: string;
    timezone: string;
    dnd: boolean;
    contact_list_ids: number[];
    tag_ids: number[];
    custom_fields_json: string;
    client_id: string;
};

export type CallerIdRow = {
    id: number;
    number: string;
    label: string | null;
    status: string;
    status_label: string;
};

export type WalletSummary = {
    id: number;
    currency: string;
    balance: number;
    reserved: number;
    available: number;
    low_balance_threshold: number;
    is_low: boolean;
};

export type WalletTransactionRow = {
    id: number;
    type: string;
    type_label: string;
    amount: number;
    balance_after: number;
    description: string | null;
    reference: string | null;
    increases_balance: boolean;
    created_at: string | null;
};

export type WalletReservationRow = {
    id: number;
    amount: number;
    captured_amount: number;
    campaign: string | null;
    reference: string | null;
    created_at: string | null;
};

export type WalletPackageUnit = {
    service: string;
    label: string;
    quantity: number;
    formatted: string;
};

export type WalletPackage = {
    key: string;
    name: string;
    price: number;
    popular: boolean;
    tagline: string;
    units: WalletPackageUnit[];
};

/**
 * One service on the published price list: what a single unit costs and what
 * that unit is counted in.
 */
export type BillingRate = {
    service: string;
    label: string;
    unit: string;
    note: string;
    unit_price: number;
    price_formatted: string;
};

/**
 * The range the amount slider is drawn over, and the amount it starts on.
 */
export type BillingSlider = {
    min: number;
    max: number;
    step: number;
    default: number;
};

/**
 * What a chosen amount buys on one channel.
 */
export type BillingQuoteLine = BillingRate & {
    quantity: number;
    formatted: string;
};

export type WalletPaymentRow = {
    id: number;
    reference: string;
    description: string;
    status: string;
    status_label: string;
    currency: string;
    amount: number;
    channel: string | null;
    failure_reason: string | null;
    authorization_url: string | null;
    cancel_url: string | null;
    created_at: string | null;
};

export type WalletInvoiceRow = {
    id: number;
    number: string;
    status: string;
    status_label: string;
    currency: string;
    total: number;
    issued_at: string | null;
};

export type MobileMoneyProviderOption = {
    value: string;
    label: string;
    prefixes: string[];
};

export type WalletMobileMoneyPayment = {
    id: number;
    reference: string;
    description: string;
    status: string;
    status_label: string;
    amount: number;
    currency: string;
    phone: string | null;
    provider: string | null;
    provider_label: string | null;
    charge_status: string | null;
    message: string | null;
    requires_otp: boolean;
    awaits_approval: boolean;
    is_open: boolean;
    failure_reason: string | null;
    created_at: string | null;
};

export type PaymentRow = {
    id: number;
    reference: string;
    client_id: number;
    client: string | null;
    package_key: string | null;
    package: string | null;
    description: string;
    provider: string;
    status: string;
    status_label: string;
    is_open: boolean;
    is_settled: boolean;
    currency: string;
    amount: number;
    amount_paid: number;
    channel: string | null;
    failure_reason: string | null;
    invoice_number: string | null;
    paid_at: string | null;
    created_at: string | null;
};

export type PaymentStats = {
    currency: string;
    total: number;
    paid: number;
    pending: number;
    failed: number;
    collected: number;
};

export type InvoiceLine = {
    description: string;
    quantity: number;
    unit_price: number;
    amount: number;
};

export type InvoiceRow = {
    id: number;
    number: string;
    client_id: number;
    client: string | null;
    payment_reference: string | null;
    channel: string | null;
    status: string;
    status_label: string;
    currency: string;
    subtotal: number;
    tax: number;
    total: number;
    lines: InvoiceLine[];
    issued_at: string | null;
    paid_at: string | null;
};

export type InvoiceStats = {
    currency: string;
    total: number;
    paid: number;
    refunded: number;
    void: number;
    billed: number;
};

/** One of the client's own purchases, as the invoices screen lists it. */
export type PurchaseRow = {
    id: number;
    number: string;
    status: string;
    status_label: string;
    currency: string;
    subtotal: number;
    tax: number;
    total: number;
    description: string;
    payment_reference: string | null;
    channel: string | null;
    issued_at: string | null;
    paid_at: string | null;
    download_url: string;
};

/** The headline figures shown above the client's purchases. */
export type PurchaseSummary = {
    currency: string;
    count: number;
    paid: number;
    spent: number;
};

export type RateRow = {
    id: number;
    destination: string;
    destination_label: string;
    billing_unit: string;
    billing_unit_label: string;
    unit_price: number;
    supplier_cost: number;
    margin: number;
};

export type RateCardRow = {
    id: number;
    name: string;
    client_id: number | null;
    client: string;
    channel: string;
    channel_label: string;
    currency: string;
    is_default: boolean;
    effective_from: string | null;
    effective_to: string | null;
    rates: RateRow[];
};

export type Option = {
    value: string;
    label: string;
};

export type ReportSummary = {
    campaigns: number;
    completed_campaigns: number;
    recipients: number;
    delivered: number;
    failed: number;
    skipped: number;
    spend: number;
    reserved: number;
    delivery_rate: number | null;
};

export type ChannelReport = {
    channel: string;
    label: string;
    campaigns: number;
    recipients: number;
    spend: number;
};

export type MonthlyReport = {
    month: string;
    label: string;
    campaigns: number;
    spend: number;
};

export type TopCampaign = {
    id: number;
    reference: string;
    name: string;
    channel_label: string;
    recipients: number | null;
    spend: number;
};

export type DashboardStats = {
    campaigns: number;
    active_campaigns: number;
    awaiting_approval: number;
    contacts: number;
    delivered: number;
    spend_this_month: number;
};

export type DashboardCampaign = {
    id: number;
    reference: string;
    name: string;
    channel: string;
    channel_label: string;
    status: string;
    status_label: string;
    audience: string;
    sender: string | null;
    estimated_recipients: number | null;
    estimated_cost: number;
    actual_cost: number;
    scheduled_at: string | null;
    created_at: string | null;
};

export type ChannelBreakdown = {
    channel: string;
    label: string;
    total: number;
};

export type ManagedUserRow = {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    client_id: number | null;
    client: string | null;
    roles: { name: string; label: string }[];
    is_active: boolean;
    is_suspended: boolean;
    suspension_reason: string | null;
    two_factor_enabled: boolean;
    email_verified: boolean;
    last_login_at: string | null;
    created_at: string | null;
};

export type InvitationRow = {
    id: number;
    email: string;
    name: string | null;
    role: string;
    role_label: string;
    client: string | null;
    inviter: string | null;
    status: string;
    status_label: string;
    is_pending: boolean;
    expires_at: string | null;
    accepted_at?: string | null;
    last_sent_at?: string | null;
    created_at: string | null;
};

export type RoleRow = {
    name: string;
    label: string;
    permissions: string[];
    users_count: number;
    locked: boolean;
};

export type PermissionGroup = {
    key: string;
    label: string;
    permissions: { value: string; label: string; roles?: string[] }[];
};

export type SessionRow = {
    session_id: string;
    ip_address: string | null;
    device: string;
    user_agent?: string | null;
    last_activity: number;
    last_active_at: string;
    is_current: boolean;
};

export type PlatformSessionRow = {
    session_id: string;
    user_id: number | null;
    user_name: string | null;
    user_email: string | null;
    client_name: string | null;
    ip_address: string | null;
    device: string;
    last_activity: number;
    last_active_at: string;
};

export type LoginAuditRow = {
    id: number;
    email: string | null;
    user: string | null;
    client: string | null;
    event: string;
    event_label: string;
    event_tone: string;
    ip_address: string | null;
    device: string;
    context: Record<string, unknown> | null;
    created_at: string | null;
};

export type ApiTokenRow = {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string | null;
};

export type WebhookEndpointRow = {
    id: number;
    name: string;
    url: string;
    description: string | null;
    events: string[];
    api_version: string;
    is_active: boolean;
    masked_secret: string;
    consecutive_failures: number;
    disabled_at: string | null;
    last_success_at: string | null;
    last_failure_at: string | null;
    client?: { id: number; name: string } | null;
};

export type WebhookDeliveryRow = {
    id: number;
    event: string;
    status: string;
    tone: string;
    attempts: number;
    response_status: number | null;
    error_message: string | null;
    endpoint: { id: number | null; name: string | null; url: string | null };
    created_at: string | null;
    delivered_at: string | null;
};

export type WebhookEventCatalogue = {
    group: string;
    events: { value: string; label: string }[];
};

export type SmppAccountRow = {
    id: number;
    name: string;
    system_id: string;
    bind_mode: string;
    bind_mode_label: string;
    allowed_sources: string[];
    throughput_per_second: number;
    is_active: boolean;
    last_bound_at: string | null;
    client: { id: number; name: string } | null;
    messages_count: number;
};

export type SmppSessionRow = {
    id: number;
    system_id: string;
    mode: string;
    remote_address: string | null;
    bound_at: string | null;
    last_seen_at: string | null;
    pdus_in: number;
    pdus_out: number;
    account: { id: number; name: string } | null;
};

export type SmppMessageRow = {
    id: number;
    reference: string;
    message_id: string;
    source_address: string;
    destination_address: string;
    status: string;
    status_label: string;
    segments: number;
    provider: string | null;
    account: string | null;
    submitted_at: string | null;
};

export type TeamMemberRow = {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    roles: string[];
    is_active: boolean;
    is_self: boolean;
    two_factor_enabled: boolean;
    last_login_at: string | null;
};

export type TeamInvitationRow = {
    id: number;
    email: string;
    name: string | null;
    role_label: string;
    inviter: string | null;
    status: string;
    status_label: string;
    is_pending: boolean;
    expires_at: string | null;
    created_at: string | null;
};

export type DeliveryQueueRow = {
    name: string;
    purpose: string;
    channels: string[];
    supervisor: string | null;
    cps: number;
    tries: number;
    backoff: string;
    timeout: string;
    queued: number;
    failed: number;
};

export type DeliveryChannelRow = {
    value: string;
    label: string;
    queue: string;
    cps: number;
};

/*
|--------------------------------------------------------------------------
| Reporting
|--------------------------------------------------------------------------
*/

export type ReportOption = {
    value: string;
    label: string;
};

export type ReportPeriod = {
    label: string;
    days: number;
};

export type ReportCampaignOption = {
    id: number;
    name: string;
    reference: string;
};

export type CampaignPerformanceRow = {
    id: number;
    reference: string;
    name: string;
    channel: string;
    channel_label: string;
    status: string;
    status_label: string;
    recipients: number;
    delivered: number;
    failed: number;
    skipped: number;
    pending: number;
    spend: number;
    delivery_rate: number | null;
    created_at: string | null;
    launched_at: string | null;
    completed_at: string | null;
};

export type CampaignPerformanceTotals = {
    campaigns: number;
    recipients: number;
    delivered: number;
    failed: number;
    skipped: number;
    pending: number;
    spend: number;
    delivery_rate: number | null;
};

export type CampaignPerformanceReport = {
    rows: CampaignPerformanceRow[];
    totals: CampaignPerformanceTotals;
};

export type ContactOutcomeReport = {
    totals: {
        contacts: number;
        reachable: number;
        dnd: number;
        suppressed: number;
        reached: number;
        delivered: number;
        failed: number;
        skipped: number;
        pending: number;
        opt_outs: number;
    };
    byStatus: Array<{ status: string; label: string; total: number }>;
    topContacts: Array<{
        contact_id: number | null;
        name: string;
        phone: string | null;
        email: string | null;
        recipients: number;
        failed: number;
    }>;
    recentOptOuts: Array<{
        id: number;
        name: string;
        phone: string | null;
        email: string | null;
        dnd_at: string | null;
    }>;
};

export type ReportExportRow = {
    id: number;
    report: string;
    report_label: string;
    status: string;
    status_label: string;
    source: string;
    period: string;
    rows: number | null;
    size: number | null;
    file_name: string | null;
    downloadable: boolean;
    expired: boolean;
    error_message: string | null;
    client: string | null;
    requester: string | null;
    created_at: string | null;
};

export type ExecutiveSummary = ReportSummary & {
    clients: number;
    active_clients: number;
    new_clients: number;
    pending: number;
    revenue: number;
    payments: number;
    wallet_balance: number;
    wallet_reserved: number;
};

export type ReportTrendPoint = {
    date: string;
    recipients: number;
    delivered: number;
    failed: number;
    spend: number;
};

export type TopClientRow = {
    client_id: number;
    name: string;
    campaigns: number;
    recipients: number;
    spend: number;
};

export type ClientPerformanceRow = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    campaigns: number;
    recipients: number;
    delivered: number;
    failed: number;
    skipped: number;
    delivery_rate: number | null;
    spend: number;
    revenue: number;
    balance: number;
    reserved: number;
};

export type ClientPerformanceTotals = {
    clients: number;
    campaigns: number;
    recipients: number;
    delivered: number;
    failed: number;
    spend: number;
    revenue: number;
    balance: number;
    reserved: number;
};

export type RevenueStatusRow = {
    status: string;
    label: string;
    total: number;
    amount: number;
};

export type WalletEntryRow = {
    type: string;
    label: string;
    entries: number;
    amount: number;
};

export type RevenueMonthlyPoint = {
    month: string;
    label: string;
    revenue: number;
    spend: number;
};

export type ProviderRow = {
    provider: string;
    total: number;
    delivered: number;
    failed: number;
    pending: number;
    delivery_rate: number | null;
    spend: number;
};

export type ProviderChannelReport = {
    label: string;
    rows: ProviderRow[];
    totals: {
        total: number;
        delivered: number;
        failed: number;
        spend: number;
        delivery_rate: number | null;
    };
};

export type LedgerReconciliation = {
    period: string;
    usage: { sms: number; voice: number; email: number; total: number };
    campaign_cost: number;
    ledger: number;
    variance: number;
    usage_variance: number;
    tolerance: number;
    balanced: boolean;
    campaigns: Array<{
        id: number;
        reference: string;
        name: string;
        claimed: number;
        debited: number;
        variance: number;
    }>;
};

export type RevenueTotals = {
    revenue: number;
    payments: number;
    pending_payments: number;
    failed_payments: number;
    invoiced: number;
    invoices: number;
    spend: number;
    balance: number;
    reserved: number;
};

export type RevenueProviderRow = {
    provider: string;
    payments: number;
    revenue: number;
};

export type RevenueReport = {
    totals: RevenueTotals;
    paymentStatuses: RevenueStatusRow[];
    invoiceStatuses: RevenueStatusRow[];
    wallet: WalletEntryRow[];
    byProvider: RevenueProviderRow[];
    monthly: RevenueMonthlyPoint[];
};

export type ChannelDeliveryTotals = {
    total: number;
    delivered: number;
    failed: number;
    pending: number;
    delivery_rate: number | null;
    spend: number;
    clients: number;
};

export type ChannelDeliveryPoint = {
    date: string;
    label: string;
    total: number;
    delivered: number;
    failed: number;
    spend: number;
};

export type ChannelDeliveryClientRow = {
    client_id: number;
    name: string;
    total: number;
    delivered: number;
    failed: number;
    pending: number;
    delivery_rate: number | null;
    spend: number;
};

export type ChannelDeliveryReport = {
    channel: { value: string; label: string; unit: string };
    totals: ChannelDeliveryTotals;
    daily: ChannelDeliveryPoint[];
    byClient: ChannelDeliveryClientRow[];
};

/**
 * A category the library files templates under. Platform categories are shared
 * with every tenant, tenant categories stay inside the account that made them.
 */
export type TemplateCategoryOption = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    is_system: boolean;
    templates_count: number;
};

/**
 * A template as the library, the catalogue and the review queue present it.
 */
export type TemplateRow = {
    id: number;
    name: string;
    channel: string;
    channel_label: string;
    status: string;
    status_label: string;
    description: string | null;
    subject: string | null;
    body: string;
    email_format: string;
    language: string;
    variables: string[];
    category: { id: number; name: string; slug: string } | null;
    client: string | null;
    client_id: number | null;
    is_system: boolean;
    usage_count: number;
    campaigns_count: number;
    author: string | null;
    approved_at: string | null;
    rejection_reason: string | null;
    updated_at: string | null;
    is_favourite: boolean;
    can: {
        update: boolean;
        delete: boolean;
        approve: boolean;
        use: boolean;
    };
};

/**
 * A template on the platform's usage report: who owns it and when it was last
 * copied into a campaign.
 */
export type TemplateUsageRow = TemplateRow & {
    ownership: string;
    last_used_at: string | null;
};

/**
 * The editable fields of a template. The editor keeps every value as a string
 * so an untouched select or input round-trips exactly what the server sent.
 */
export type TemplateFormValues = {
    name: string;
    channel: string;
    template_category_id: string;
    language: string;
    description: string;
    subject: string;
    email_format: string;
    body: string;
    status: string;
    client_id: string;
};
