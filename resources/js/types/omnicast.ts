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
    voice_script: string | null;
    voice_recording_path: string | null;
    voice_max_duration_seconds: number | null;
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
    status: string;
    status_label: string;
    attempts: number;
    cost: number;
    error_message: string | null;
    sent_at: string | null;
};

export type CampaignFormOptions = {
    clients: ClientOption[];
    tenantClientId: number | null;
    contactLists: ContactListOption[];
    senderIds: { id: number; name: string }[];
    callerIds: { id: number; number: string; label: string | null }[];
    channels: SelectOption[];
};

export type CampaignFormValues = {
    name: string;
    channel: string;
    contact_list_id: string;
    sender_id: string;
    caller_id: string;
    sms_message: string;
    voice_script: string;
    voice_max_duration_seconds: string;
    schedule_type: string;
    scheduled_at: string;
    timezone: string;
    max_retries: string;
    retry_delay_minutes: string;
    notes: string;
    client_id: string;
};

export type ContactRow = {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    company: string | null;
    account_number: string | null;
    location: string | null;
    consent_status: string;
    consent_label: string;
    is_do_not_contact: boolean;
    lists: string[];
    created_at: string | null;
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
    consent_status: string;
    is_do_not_contact: boolean;
    contact_list_ids: number[];
    client_id: string;
};

export type SenderIdRow = {
    id: number;
    name: string;
    country: string | null;
    purpose: string | null;
    status: string;
    status_label: string;
    requested_at: string | null;
    approved_at: string | null;
    rejection_reason: string | null;
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

