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
        launch: (campaign: number) => path(`/campaigns/${campaign}/launch`),
        cancel: (campaign: number) => path(`/campaigns/${campaign}/cancel`),
    },
    contacts: {
        index: path('/contacts'),
        create: path('/contacts/create'),
        store: path('/contacts'),
        edit: (contact: number) => path(`/contacts/${contact}/edit`),
        update: (contact: number) => path(`/contacts/${contact}`),
        destroy: (contact: number) => path(`/contacts/${contact}`),
        import: path('/contacts/import'),
    },
    senderIds: {
        index: path('/sender-ids'),
        store: path('/sender-ids'),
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
    },
    admin: {
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
