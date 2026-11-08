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
    emailImages: path('/email/images'),
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
        duplicate: (campaign: number) =>
            path(`/campaigns/${campaign}/duplicate`),
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
        dndRegistry: path('/contacts/dnd'),
        lists: {
            index: path('/contacts/lists'),
            store: path('/contacts/lists'),
            show: (list: number) => path(`/contacts/lists/${list}`),
            update: (list: number) => path(`/contacts/lists/${list}`),
            destroy: (list: number) => path(`/contacts/lists/${list}`),
            import: (list: number) => path(`/contacts/lists/${list}/import`),
            attachContacts: (list: number) =>
                path(`/contacts/lists/${list}/contacts`),
            detachContacts: (list: number) =>
                path(`/contacts/lists/${list}/contacts`),
        },
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
    templates: {
        index: path('/templates'),
        create: path('/templates/create'),
        store: path('/templates'),
        edit: (template: number) => path(`/templates/${template}/edit`),
        update: (template: number) => path(`/templates/${template}`),
        destroy: (template: number) => path(`/templates/${template}`),
        customise: (template: number) =>
            path(`/templates/${template}/customise`),
        favourite: (template: number) =>
            path(`/templates/${template}/favourite`),
    },
    wallet: {
        index: path('/wallet'),
        topUp: path('/wallet/top-up'),
        checkout: path('/wallet/checkout'),
        checkoutCallback: path('/wallet/checkout/callback'),
        cancelCheckout: (payment: number) =>
            path(`/wallet/checkout/${payment}/cancel`),
        mobileMoney: {
            create: path('/wallet/mobile-money'),
            store: path('/wallet/mobile-money'),
            show: (payment: number) => path(`/wallet/mobile-money/${payment}`),
            otp: (payment: number) =>
                path(`/wallet/mobile-money/${payment}/otp`),
            refresh: (payment: number) =>
                path(`/wallet/mobile-money/${payment}/refresh`),
        },
    },
    invoices: {
        index: path('/invoices'),
        download: (invoice: number) => path(`/invoices/${invoice}/download`),
    },
    reports: {
        index: path('/reports'),
        campaigns: path('/reports/campaigns'),
        contacts: path('/reports/contacts'),
        voice: path('/reports/voice'),
        sms: path('/reports/sms'),
        email: path('/reports/email'),
        exports: {
            index: path('/reports/exports'),
            store: path('/reports/exports'),
            download: (exportId: number) =>
                path(`/reports/exports/${exportId}/download`),
        },
    },
    admin: {
        settings: {
            index: path('/admin/settings'),
            modules: path('/admin/settings/modules'),
            plans: path('/admin/settings/plans'),
            assignPlan: (client: number) =>
                path(`/admin/settings/plans/${client}`),
        },
        packages: {
            index: path('/admin/packages'),
            update: (packageKey: string) =>
                path(`/admin/packages/${packageKey}`),
        },
        pricing: {
            index: path('/admin/pricing'),
            update: path('/admin/pricing'),
        },
        payments: {
            index: path('/admin/payments'),
            verify: (payment: number) =>
                path(`/admin/payments/${payment}/verify`),
        },
        invoices: {
            index: path('/admin/invoices'),
        },
        rateCards: {
            index: path('/admin/rate-cards'),
            updateRate: (rate: number) => path(`/admin/rates/${rate}`),
        },
        clients: {
            index: path('/admin/clients'),
            show: (client: number) => path(`/admin/clients/${client}`),
            update: (client: number) => path(`/admin/clients/${client}`),
            activate: (client: number) =>
                path(`/admin/clients/${client}/activate`),
            suspend: (client: number) =>
                path(`/admin/clients/${client}/suspend`),
            reactivate: (client: number) =>
                path(`/admin/clients/${client}/reactivate`),
            archive: (client: number) =>
                path(`/admin/clients/${client}/archive`),
            documents: (client: number) =>
                path(`/admin/clients/${client}/documents`),
            reviewDocument: (client: number, document: number) =>
                path(`/admin/clients/${client}/documents/${document}/review`),
            deleteDocument: (client: number, document: number) =>
                path(`/admin/clients/${client}/documents/${document}`),
            entitlements: (client: number) =>
                path(`/admin/clients/${client}/entitlements`),
        },
        clientRegistrations: {
            index: path('/admin/client-registrations'),
            approve: (registration: number) =>
                path(`/admin/client-registrations/${registration}/approve`),
            reject: (registration: number) =>
                path(`/admin/client-registrations/${registration}/reject`),
        },
        resourceRequests: {
            index: path('/admin/resource-requests'),
        },
        users: {
            index: path('/admin/users'),
            store: path('/admin/users'),
            update: (user: number) => path(`/admin/users/${user}`),
            suspend: (user: number) => path(`/admin/users/${user}/suspend`),
            reactivate: (user: number) =>
                path(`/admin/users/${user}/reactivate`),
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
        queues: {
            index: path('/admin/queues'),
        },
        templates: {
            index: path('/admin/templates'),
            create: path('/admin/templates/create'),
            store: path('/admin/templates'),
            edit: (template: number) =>
                path(`/admin/templates/${template}/edit`),
            update: (template: number) => path(`/admin/templates/${template}`),
            destroy: (template: number) => path(`/admin/templates/${template}`),
            archive: (template: number) =>
                path(`/admin/templates/${template}/archive`),
        },
        templateApprovals: {
            index: path('/admin/template-approvals'),
            approve: (template: number) =>
                path(`/admin/template-approvals/${template}/approve`),
            reject: (template: number) =>
                path(`/admin/template-approvals/${template}/reject`),
        },
        templateUsage: {
            index: path('/admin/template-usage'),
        },
        reports: {
            index: path('/admin/reports'),
            clients: path('/admin/reports/clients'),
            voice: path('/admin/reports/voice'),
            sms: path('/admin/reports/sms'),
            email: path('/admin/reports/email'),
            revenue: path('/admin/reports/revenue'),
            providers: path('/admin/reports/providers'),
            exports: {
                index: path('/admin/reports/exports'),
                store: path('/admin/reports/exports'),
                download: (exportId: number) =>
                    path(`/admin/reports/exports/${exportId}/download`),
            },
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
            destroy: (invitation: number) =>
                path(`/settings/team/${invitation}`),
        },
    },
    invitations: {
        accept: (token: string) => path(`/invitations/${token}`),
    },
} as const;
