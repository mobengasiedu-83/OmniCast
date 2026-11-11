import { withBasePath as path } from '@/lib/base-path';

/**
 * The origin production is served from.
 *
 * The API reference prints absolute URLs (base URL, `links.*` hrefs, webhook
 * callbacks), so it states the production origin instead of whichever host the
 * reader happens to be browsing — a client reading the docs from staging must
 * still copy the address their integration will call.
 */
export const productionOrigin = 'https://omnicast.sysup360.com';

/** The versioned base URL every documented request is built on. */
export const apiBaseUrl = `${productionOrigin}/api/v1`;

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
    documentation: {
        userManual: path('/user-manual'),
        apiDocs: path('/api-docs'),
    },
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
    automations: {
        index: path('/automations'),
        store: path('/automations'),
        show: (workflow: number) => path(`/automations/${workflow}`),
        update: (workflow: number) => path(`/automations/${workflow}`),
        toggle: (workflow: number) => path(`/automations/${workflow}/toggle`),
        destroy: (workflow: number) => path(`/automations/${workflow}`),
        steps: {
            store: (workflow: number) => path(`/automations/${workflow}/steps`),
            update: (workflow: number, step: number) =>
                path(`/automations/${workflow}/steps/${step}`),
            move: (workflow: number, step: number) =>
                path(`/automations/${workflow}/steps/${step}/move`),
            destroy: (workflow: number, step: number) =>
                path(`/automations/${workflow}/steps/${step}`),
        },
        runs: {
            index: path('/automations/runs'),
            show: (run: number) => path(`/automations/runs/${run}`),
        },
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
    notifications: {
        index: path('/notifications'),
        read: (notification: string) =>
            path(`/notifications/${notification}/read`),
        unread: (notification: string) =>
            path(`/notifications/${notification}/unread`),
        destroy: (notification: string) =>
            path(`/notifications/${notification}`),
        readAll: path('/notifications/read-all'),
        clearRead: path('/notifications/read'),
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
        integrations: {
            index: path('/admin/integrations'),
            update: path('/admin/integrations'),
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
        webhooks: {
            index: path('/admin/webhooks'),
            store: path('/admin/webhooks'),
            update: (endpoint: number) => path(`/admin/webhooks/${endpoint}`),
            toggle: (endpoint: number) =>
                path(`/admin/webhooks/${endpoint}/toggle`),
            rotate: (endpoint: number) =>
                path(`/admin/webhooks/${endpoint}/rotate`),
            test: (endpoint: number) =>
                path(`/admin/webhooks/${endpoint}/test`),
            destroy: (endpoint: number) => path(`/admin/webhooks/${endpoint}`),
            replay: (delivery: number) =>
                path(`/admin/webhook-deliveries/${delivery}/replay`),
        },
        smpp: {
            index: path('/admin/smpp'),
            store: path('/admin/smpp'),
            update: (account: number) => path(`/admin/smpp/${account}`),
            toggle: (account: number) => path(`/admin/smpp/${account}/toggle`),
            rotate: (account: number) => path(`/admin/smpp/${account}/rotate`),
        },
        announcements: {
            index: path('/admin/announcements'),
            store: path('/admin/announcements'),
            send: (announcement: number) =>
                path(`/admin/announcements/${announcement}/send`),
            cancelSchedule: (announcement: number) =>
                path(`/admin/announcements/${announcement}/cancel-schedule`),
            destroy: (announcement: number) =>
                path(`/admin/announcements/${announcement}`),
        },
        legal: {
            documents: {
                index: path('/admin/legal-documents'),
                store: path('/admin/legal-documents'),
                update: (document: number) =>
                    path(`/admin/legal-documents/${document}`),
                destroy: (document: number) =>
                    path(`/admin/legal-documents/${document}`),
                toggle: (document: number) =>
                    path(`/admin/legal-documents/${document}/toggle`),
                requirements: (document: number) =>
                    path(`/admin/legal-documents/${document}/requirements`),
                storeVersion: (document: number) =>
                    path(`/admin/legal-documents/${document}/versions`),
            },
            versions: {
                index: path('/admin/legal-document-versions'),
                update: (version: number) =>
                    path(`/admin/legal-document-versions/${version}`),
                destroy: (version: number) =>
                    path(`/admin/legal-document-versions/${version}`),
                publish: (version: number) =>
                    path(`/admin/legal-document-versions/${version}/publish`),
                archive: (version: number) =>
                    path(`/admin/legal-document-versions/${version}/archive`),
                notify: (version: number) =>
                    path(`/admin/legal-document-versions/${version}/notify`),
            },
            acceptances: {
                index: path('/admin/legal-acceptances'),
                export: path('/admin/legal-acceptances/export'),
            },
            dpas: {
                index: path('/admin/legal-dpas'),
                update: (client: number) => path(`/admin/legal-dpas/${client}`),
                uploadEvidence: (client: number) =>
                    path(`/admin/legal-dpas/${client}/evidence`),
                evidence: (client: number, evidence: number) =>
                    path(`/admin/legal-dpas/${client}/evidence/${evidence}`),
            },
            privacyRetention: path('/admin/privacy/retention'),
            privacyRetentionUpdate: (category: string) =>
                path(`/admin/privacy/retention/${category}`),
            privacyRequests: {
                index: path('/admin/privacy/requests'),
                store: path('/admin/privacy/requests'),
                review: (id: number) =>
                    path(`/admin/privacy/requests/${id}/review`),
                export: (id: number) =>
                    path(`/admin/privacy/requests/${id}/export`),
                erase: (id: number) =>
                    path(`/admin/privacy/requests/${id}/erase`),
            },
            complianceSettings: path('/admin/legal-compliance-settings'),
            notifications: path('/admin/legal-notifications'),
            reports: path('/admin/legal-compliance-reports'),
        },
        support: {
            enquiries: {
                index: path('/admin/support/enquiries'),
                show: (reference: string) =>
                    path(`/admin/support/enquiries/${reference}`),
            },
            tickets: {
                index: path('/admin/support/tickets'),
                show: (reference: string) =>
                    path(`/admin/support/tickets/${reference}`),
            },
            categories: {
                index: path('/admin/support/categories'),
            },
            teams: {
                index: path('/admin/support/teams'),
            },
            notifications: {
                index: path('/admin/support/notifications'),
                update: path('/admin/support/notifications'),
            },
            knowledgeBase: {
                index: path('/admin/support/knowledge-base'),
            },
            reports: {
                index: path('/admin/support/reports'),
            },
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
        notifications: {
            index: path('/settings/notifications'),
            update: path('/settings/notifications'),
        },
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
        webhooks: {
            index: path('/settings/webhooks'),
            store: path('/settings/webhooks'),
            update: (endpoint: number) =>
                path(`/settings/webhooks/${endpoint}`),
            toggle: (endpoint: number) =>
                path(`/settings/webhooks/${endpoint}/toggle`),
            rotate: (endpoint: number) =>
                path(`/settings/webhooks/${endpoint}/rotate`),
            test: (endpoint: number) =>
                path(`/settings/webhooks/${endpoint}/test`),
            destroy: (endpoint: number) =>
                path(`/settings/webhooks/${endpoint}`),
        },
    },
    invitations: {
        accept: (token: string) => path(`/invitations/${token}`),
    },
    contact: path('/contact'),
    help: {
        index: path('/help'),
        show: (slug: string) => path(`/help/${slug}`),
    },
    support: {
        index: path('/support'),
        contact: path('/support/contact'),
        tickets: {
            index: path('/support/tickets'),
            create: path('/support/tickets/create'),
            store: path('/support/tickets'),
            show: (reference: string) => path(`/support/tickets/${reference}`),
            reply: (reference: string) =>
                path(`/support/tickets/${reference}/reply`),
            close: (reference: string) =>
                path(`/support/tickets/${reference}/close`),
        },
        knowledgeBase: {
            index: path('/support/knowledge-base'),
            show: (slug: string) => path(`/support/knowledge-base/${slug}`),
        },
    },
    legal: {
        index: path('/legal'),
        show: (slug: string) => path(`/legal/${slug}`),
        print: (slug: string) => path(`/legal/${slug}/print`),
        settings: {
            index: path('/settings/legal'),
            accept: path('/settings/legal/accept'),
            dpa: path('/settings/legal/dpa'),
        },
    },
} as const;
