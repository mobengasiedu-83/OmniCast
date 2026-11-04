import { withBasePath as path } from '@/lib/base-path';

/**
 * The URIs served by `routes/web.php`.
 *
 * These mirror the Laravel route table so pages can link to and post to the
 * domain screens. Swap them for the Wayfinder helpers in `@/routes` once
 * `php artisan wayfinder:generate --with-form` has been run.
 *
 * The application is served from a sub-directory (`APP_URL`), so every URI is
 * run through `withBasePath()` -- a root-relative `/campaigns` would otherwise
 * escape to the host root.
 */
export const urls = {
    dashboard: path('/dashboard'),
    login: path('/login'),
    approvals: {
        index: path('/approvals'),
    },
    campaigns: {
        index: path('/campaigns'),
        create: path('/campaigns/create'),
        store: path('/campaigns'),
        show: (campaign: number) => path(`/campaigns/${campaign}`),
        edit: (campaign: number) => path(`/campaigns/${campaign}/edit`),
        update: (campaign: number) => path(`/campaigns/${campaign}`),
        destroy: (campaign: number) => path(`/campaigns/${campaign}`),
        submit: (campaign: number) => path(`/campaigns/${campaign}/submit`),
        approve: (campaign: number) => path(`/campaigns/${campaign}/approve`),
        reject: (campaign: number) => path(`/campaigns/${campaign}/reject`),
        approveAudio: (campaign: number) =>
            path(`/campaigns/${campaign}/audio/approve`),
        rejectAudio: (campaign: number) =>
            path(`/campaigns/${campaign}/audio/reject`),
        launch: (campaign: number) => path(`/campaigns/${campaign}/launch`),
        cancel: (campaign: number) => path(`/campaigns/${campaign}/cancel`),
        duplicate: (campaign: number) => path(`/campaigns/${campaign}/duplicate`),
    },
    contacts: {
        index: path('/contacts'),
        create: path('/contacts/create'),
        store: path('/contacts'),
        show: (contact: number) => path(`/contacts/${contact}`),
        edit: (contact: number) => path(`/contacts/${contact}/edit`),
        update: (contact: number) => path(`/contacts/${contact}`),
        destroy: (contact: number) => path(`/contacts/${contact}`),
        bulk: path('/contacts/bulk'),
        dnd: (contact: number) => path(`/contacts/${contact}/dnd`),
        import: path('/contacts/import'),
        importTemplate: path('/contacts/import/template'),
        audiences: path('/contacts/audiences'),
        storeTag: path('/contacts/audiences/tags'),
        deleteTag: (tag: number) => path(`/contacts/audiences/tags/${tag}`),
        storeList: path('/contacts/audiences/lists'),
        deleteList: (list: number) => path(`/contacts/audiences/lists/${list}`),
        storeSegment: path('/contacts/audiences/segments'),
        updateSegment: (segment: number) =>
            path(`/contacts/audiences/segments/${segment}`),
        deleteSegment: (segment: number) =>
            path(`/contacts/audiences/segments/${segment}`),
        mergeDuplicates: path('/contacts/audiences/duplicates/merge'),
        storeSuppression: path('/contacts/audiences/suppressions'),
        liftSuppression: (suppression: number) =>
            path(`/contacts/audiences/suppressions/${suppression}`),
    },
    senderIds: {
        index: path('/sender-ids'),
        create: path('/sender-ids/create'),
        store: path('/sender-ids'),
        edit: (senderId: number) => path(`/sender-ids/${senderId}/edit`),
        update: (senderId: number) => path(`/sender-ids/${senderId}`),
        approve: (senderId: number) => path(`/sender-ids/${senderId}/approve`),
        reject: (senderId: number) => path(`/sender-ids/${senderId}/reject`),
        destroy: (senderId: number) => path(`/sender-ids/${senderId}`),
    },
    wallet: {
        index: path('/wallet'),
        topUp: path('/wallet/top-up'),
    },
    reports: {
        index: path('/reports'),
        voice: path('/reports/voice'),
        sms: path('/reports/sms'),
        email: path('/reports/email'),
    },
    admin: {
        settings: {
            index: path('/admin/settings'),
            plans: path('/admin/settings/plans'),
            assignPlan: (client: number) => path(`/admin/settings/plans/${client}`),
        },
        clients: {
            index: path('/admin/clients'),
            show: (client: number) => path(`/admin/clients/${client}`),
            update: (client: number) => path(`/admin/clients/${client}`),
            activate: (client: number) => path(`/admin/clients/${client}/activate`),
            suspend: (client: number) => path(`/admin/clients/${client}/suspend`),
            reactivate: (client: number) => path(`/admin/clients/${client}/reactivate`),
            archive: (client: number) => path(`/admin/clients/${client}/archive`),
            documents: (client: number) => path(`/admin/clients/${client}/documents`),
            reviewDocument: (client: number, document: number) => path(`/admin/clients/${client}/documents/${document}/review`),
            deleteDocument: (client: number, document: number) => path(`/admin/clients/${client}/documents/${document}`),
            entitlements: (client: number) => path(`/admin/clients/${client}/entitlements`),
        },
        clientRegistrations: {
            index: path('/admin/client-registrations'),
            approve: (registration: number) => path(`/admin/client-registrations/${registration}/approve`),
            reject: (registration: number) => path(`/admin/client-registrations/${registration}/reject`),
        },
        resourceRequests: {
            index: path('/admin/resource-requests'),
        },
        users: {
            index: path('/admin/users'),
            store: path('/admin/users'),
            update: (user: number) => path(`/admin/users/${user}`),
            suspend: (user: number) => path(`/admin/users/${user}/suspend`),
            reactivate: (user: number) => path(`/admin/users/${user}/reactivate`),
        },
        invitations: {
            index: path('/admin/invitations'),
            store: path('/admin/invitations'),
            resend: (invitation: number) =>
                path(`/admin/invitations/${invitation}/resend`),
            revoke: (invitation: number) =>
                path(`/admin/invitations/${invitation}/revoke`),
            destroy: (invitation: number) =>
                path(`/admin/invitations/${invitation}`),
        },
        roles: {
            index: path('/admin/roles'),
            update: (role: string) => path(`/admin/roles/${role}`),
        },
        permissions: {
            index: path('/admin/permissions'),
        },
        sessions: {
            index: path('/admin/sessions'),
            revoke: (user: number) => path(`/admin/sessions/${user}`),
            revokeOne: (user: number, session: string) =>
                path(`/admin/sessions/${user}/${session}`),
        },
        loginAudit: {
            index: path('/admin/login-audit'),
        },
    },
    account: {
        organisation: {
            index: path('/settings/organisation'),
            update: path('/settings/organisation'),
            uploadDocument: path('/settings/organisation/documents'),
        },
        resourceRequests: {
            index: path('/settings/resource-requests'),
            store: path('/settings/resource-requests'),
        },
        sessions: {
            index: path('/settings/sessions'),
            destroy: (session: string) => path(`/settings/sessions/${session}`),
            destroyOthers: path('/settings/sessions'),
        },
        tokens: {
            index: path('/settings/api-tokens'),
            store: path('/settings/api-tokens'),
            destroy: (token: number) => path(`/settings/api-tokens/${token}`),
        },
        team: {
            index: path('/settings/team'),
            store: path('/settings/team'),
            resend: (invitation: number) =>
                path(`/settings/team/${invitation}/resend`),
            destroy: (invitation: number) => path(`/settings/team/${invitation}`),
        },
    },
    invitations: {
        accept: (token: string) => path(`/invitations/${token}`),
    },
} as const;
