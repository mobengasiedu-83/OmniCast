/**
 * The contact details an operator has confirmed.
 *
 * Every field is nullable because nothing is published until the business has
 * supplied it: the screens render a channel only when its value is present, so a
 * placeholder phone number can never reach a visitor.
 */
export type SupportContact = {
    email: string | null;
    support_email: string | null;
    sales_email: string | null;
    phone: string | null;
    whatsapp: string | null;
    address: string | null;
    hours: string | null;
    response_time: string | null;
    status_url: string | null;
};

/** A reason someone can write, as the contact and ticket forms offer it. */
export type SupportCategoryOption = {
    slug: string;
    name: string;
    description: string | null;
    priority: string;
    priority_label: string;
};

/** A published help centre article, as a list renders it. */
export type SupportArticleSummary = {
    slug: string;
    title: string;
    excerpt: string | null;
    category: string | null;
    is_featured: boolean;
};

/** What a form tells the requester before they attach a file. */
export type SupportAttachmentLimits = {
    max_files: number;
    max_kilobytes: number;
    mimes: string[];
};

/** A value/label pair for a select control. */
export type SupportOption = {
    value: string;
    label: string;
};

/**
 * One row of the admin support register.
 *
 * Enquiries and tickets share the shape; the ticket screen additionally shows the
 * organisation and the owner, which an enquiry does not have.
 */
export type SupportTicketRow = {
    id: number;
    reference: string;
    subject: string;
    requester: string;
    email: string;
    company: string | null;
    category: string | null;
    team: string | null;
    priority: string;
    priority_label: string;
    status: string;
    status_label: string;
    waiting_since: string;
    created_at: string | null;
    url: string;
    client?: string | null;
    assignee?: string | null;
};
