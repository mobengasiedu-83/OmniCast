import { Form, Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { MessageCircle, Upload, Megaphone, ShieldCheck, Download, WalletCards, ChartNoAxesCombined } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import whatsappRoutes from '@/routes/whatsapp';
import { store as storeConsent, revoke as revokeConsent } from '@/routes/whatsapp/consents';
import { store as storeCampaign, dispatch as dispatchCampaign, test as testCampaign, pause as pauseCampaign, resume as resumeCampaign, cancel as cancelCampaign } from '@/routes/whatsapp/campaigns';
import whatsappMessageRoutes from '@/routes/whatsapp/messages';
import metaWhatsappRoutes from '@/routes/whatsapp/meta';

declare global {
    interface Window {
        fbAsyncInit?: () => void;
        FB?: {
            init: (options: { appId: string; version: string; xfbml: boolean; autoLogAppEvents: boolean }) => void;
            login: (callback: (response: { authResponse?: { code?: string } }) => void, options: Record<string, unknown>) => void;
        };
    }
}

type Connector = { id: number; connector_name: string | null; sender_number: string | null; waba_id: string | null; status: string };
type Assignment = { id: number; enabled: boolean; approval_status: string; connector: Connector };
type Template = { id: number; name: string; language: string; category: string | null; components: unknown; versions: { version: number; status: string; captured_at: string }[] };
type Media = { id: number; original_name: string; mime_type: string; size_bytes: number; created_at: string };
type Contact = { id: number; first_name: string; last_name: string | null; phone: string };
type SimpleOption = { id: number; name: string };
type Consent = { id: number; purpose: string; source: string; wording: string; granted_at: string; revoked_at: string | null; contact: Contact };
type Campaign = { id: number; name: string; status: string; control_state: string; estimated_recipients: number; estimated_cost: string | null; estimate_currency: string; estimate_disclaimer: { status: string; message: string }; connector: Connector; template: Template };
type Message = { id: number; campaign_id: number; phone: string; status: string; provider_message_id: string | null; submitted_at: string | null; delivered_at: string | null; read_at: string | null; failed_at: string | null; last_error: string | null; campaign: { name: string } };
type WhatsAppWallet = { currency: string; balance: string; reserved: string; available: string; transactions: { id: number; type: string; amount: string; balance_after: string; reference: string | null; description: string; created_at: string }[] } | null;
type UsageReport = { status_counts: Record<string, number>; billable_messages: number; charges: string; provider_cost: string; gross_margin: string; breakdown: { category: string | null; market: string | null; messages: number; charges: string; provider_cost: string }[] };
type MetaConnection = { id: number; connector_name: string | null; sender_number: string | null; waba_id: string | null; business_id: string | null; status: string; token_expires_at: string | null; templates_count: number };
type MetaSignup = { enabled: boolean; appId: string | null; configId: string | null; graphVersion: string; solutionId: string | null };
type MetaSignupSession = { waba_id?: string; phone_number_id?: string; business_id?: string };

export default function WhatsApp({ connectors, availableConnectors, metaConnections, metaSignup, templates, media, maxUploadKilobytes, contacts, lists, segments, consents, campaigns, messages, whatsappWallet, usageReport, sendingEnabled }: {
    connectors: Assignment[]; availableConnectors: Connector[]; templates: Template[]; media: Media[]; maxUploadKilobytes: number;
    metaConnections: MetaConnection[]; metaSignup: MetaSignup;
    contacts: Contact[]; lists: SimpleOption[]; segments: SimpleOption[]; consents: Consent[]; campaigns: Campaign[]; messages: Message[]; whatsappWallet: WhatsAppWallet; usageReport: UsageReport; sendingEnabled: boolean;
}) {
    const [facebookSdkReady, setFacebookSdkReady] = useState(false);
    const [metaSignupState, setMetaSignupState] = useState<'idle' | 'opening' | 'connecting'>('idle');
    const [metaSignupError, setMetaSignupError] = useState<string | null>(null);
    const signupCode = useRef<string | null>(null);
    const signupSession = useRef<MetaSignupSession | null>(null);
    const signupSubmitted = useRef(false);

    const completeMetaSignup = useCallback(() => {
        const code = signupCode.current;
        const session = signupSession.current;
        if (!code || !session?.waba_id || !session.phone_number_id || signupSubmitted.current) return;
        signupSubmitted.current = true;
        setMetaSignupState('connecting');
        router.post(metaWhatsappRoutes.complete.url(), {
            code,
            waba_id: session.waba_id,
            phone_number_id: session.phone_number_id,
            business_id: session.business_id,
        }, {
            preserveScroll: true,
            onError: (errors) => {
                setMetaSignupError(errors.meta || 'Meta could not complete the connection. Please try again.');
                setMetaSignupState('idle');
                signupSubmitted.current = false;
            },
            onFinish: () => {
                signupCode.current = null;
                signupSession.current = null;
            },
        });
    }, []);

    useEffect(() => {
        if (!metaSignup.enabled || !metaSignup.appId || !metaSignup.configId) return;
        window.fbAsyncInit = () => {
            window.FB?.init({ appId: metaSignup.appId!, version: metaSignup.graphVersion, xfbml: false, autoLogAppEvents: false });
            setFacebookSdkReady(Boolean(window.FB));
        };
        if (window.FB) {
            window.fbAsyncInit();
        } else if (!document.getElementById('facebook-jssdk')) {
            const script = document.createElement('script');
            script.id = 'facebook-jssdk';
            script.async = true;
            script.defer = true;
            script.crossOrigin = 'anonymous';
            script.src = 'https://connect.facebook.net/en_US/sdk.js';
            document.head.appendChild(script);
        }

        const onMessage = (event: MessageEvent): void => {
            let host = '';
            try { host = new URL(event.origin).hostname; } catch { return; }
            if (host !== 'facebook.com' && !host.endsWith('.facebook.com')) return;
            let message: { type?: string; event?: string; data?: MetaSignupSession };
            try { message = typeof event.data === 'string' ? JSON.parse(event.data) : event.data; } catch { return; }
            if (message?.type !== 'WA_EMBEDDED_SIGNUP') return;
            if (message.event === 'CANCEL' || message.event === 'ERROR') {
                setMetaSignupError('Meta signup was not completed. You can try again when ready.');
                setMetaSignupState('idle');
                return;
            }
            if (message.event === 'FINISH' || message.event === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING') {
                signupSession.current = message.data || null;
                completeMetaSignup();
            }
        };
        window.addEventListener('message', onMessage);
        return () => window.removeEventListener('message', onMessage);
    }, [completeMetaSignup, metaSignup.appId, metaSignup.configId, metaSignup.enabled, metaSignup.graphVersion]);

    const connectMetaAccount = (): void => {
        if (!window.FB || !metaSignup.configId) return;
        signupCode.current = null;
        signupSession.current = null;
        signupSubmitted.current = false;
        setMetaSignupError(null);
        setMetaSignupState('opening');
        const setup: Record<string, unknown> = {};
        if (metaSignup.solutionId) setup.solutionID = metaSignup.solutionId;
        window.FB.login((response) => {
            const code = response.authResponse?.code;
            if (code) {
                signupCode.current = code;
                completeMetaSignup();
            } else {
                setMetaSignupState('idle');
                setMetaSignupError('Meta signup was cancelled before account access was granted.');
            }
        }, {
            config_id: metaSignup.configId,
            response_type: 'code',
            override_default_response_type: true,
            extras: { setup },
        });
    };

    const deliveredCount = (usageReport.status_counts.delivered || 0) + (usageReport.status_counts.read || 0);
    const failedCount = usageReport.status_counts.failed || 0;
    return <>
        <Head title="WhatsApp" />
        <PageHeader icon={MessageCircle} title="WhatsApp" description="Manage sender access, consent, templates, campaigns, message outcomes, wallet usage, and eligible media." />
        <div className="mt-6 space-y-6">
            <section className="rounded-lg border bg-card p-5">
                <h2 className="font-semibold">Assigned senders</h2>
                {connectors.length === 0 ? <p className="mt-3 text-sm text-muted-foreground">No sender access has been requested yet.</p> : <ul className="mt-3 divide-y">{connectors.map(({ id, connector, approval_status, enabled }) => <li key={id} className="flex flex-wrap items-center justify-between gap-3 py-3"><span>{connector.connector_name || 'WhatsApp sender'} · {connector.sender_number || 'Number unavailable'}</span><Badge variant={approval_status === 'approved' && enabled ? 'success' : 'muted'}>{enabled ? 'Enabled' : approval_status}</Badge></li>)}</ul>}
                {availableConnectors.length > 0 && <Form action={whatsappRoutes.connectors.request.url()} method="post" className="mt-4 flex flex-wrap items-end gap-3">{({ processing, errors }) => <><div className="grid gap-2"><Label htmlFor="connector_id">Request sender access</Label><select id="connector_id" name="connector_id" required className="h-10 rounded-md border bg-background px-3">{availableConnectors.map((connector) => <option key={connector.id} value={connector.id}>{connector.connector_name || connector.sender_number || `Sender ${connector.id}`}</option>)}</select>{errors.connector_id && <span className="text-sm text-destructive">{errors.connector_id}</span>}</div><Button disabled={processing}>Submit request</Button></>}</Form>}
            </section>
            <section className="rounded-lg border bg-card p-5">
                <h2 className="font-semibold">Connect your Meta account</h2>
                <p className="mt-1 text-sm text-muted-foreground">Connect a WhatsApp Business Account you administer. OmniCast will use your Meta Cloud API account to send messages from its connected phone numbers.</p>
                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <Button onClick={connectMetaAccount} disabled={!metaSignup.enabled || !facebookSdkReady || metaSignupState !== 'idle'}>
                        {metaSignupState === 'opening' ? 'Opening Meta…' : metaSignupState === 'connecting' ? 'Connecting…' : 'Continue with Facebook'}
                    </Button>
                    {!metaSignup.enabled && <span className="text-sm text-muted-foreground">Meta onboarding is not configured yet.</span>}
                    {metaSignup.enabled && !facebookSdkReady && <span className="text-sm text-muted-foreground">Loading Meta signup…</span>}
                </div>
                <p className="mt-2 text-xs text-muted-foreground">A Facebook or Meta business administrator must approve access. OmniCast stores the access token encrypted and scopes it to your client account.</p>
                {metaSignupError && <p role="alert" className="mt-3 text-sm text-destructive">{metaSignupError}</p>}
                {metaConnections.length > 0 && <ul className="mt-4 divide-y">{metaConnections.map((connection) => <li key={connection.id} className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"><span>{connection.connector_name || 'Meta WhatsApp sender'} · {connection.sender_number || connection.id} <span className="text-muted-foreground">· {connection.templates_count} templates</span></span><Badge variant={connection.status === 'available' ? 'success' : 'muted'}>{connection.status}</Badge></li>)}</ul>}
            </section>
            <section className="rounded-lg border bg-card p-5"><h2 className="font-semibold">Approved templates</h2><p className="mt-1 text-sm text-muted-foreground">Only approved templates from enabled, approved sender assignments are shown.</p>{templates.length === 0 ? <p className="mt-3 text-sm text-muted-foreground">No approved templates are available.</p> : <div className="mt-4 grid gap-4 md:grid-cols-2">{templates.map((template) => <article key={template.id} className="rounded-md border p-4"><div className="flex items-center justify-between gap-2"><h3 className="font-medium">{template.name}</h3><Badge variant="success">Approved</Badge></div><p className="mt-1 text-sm text-muted-foreground">{template.language} · {template.category || 'Uncategorized'}</p><div className="mt-3 space-y-2 rounded bg-muted p-3">{previewComponents(template.components).map((component, index) => <p key={`${component.type}-${index}`} className={component.type === 'BODY' ? 'whitespace-pre-wrap text-sm' : 'text-xs font-medium uppercase text-muted-foreground'}>{component.text || `[${component.type} component]`}</p>)}</div><p className="mt-2 text-xs text-muted-foreground">{template.versions.length} saved version(s)</p></article>)}</div>}</section>
            <section className="rounded-lg border bg-card p-5"><div className="flex items-center gap-2"><ShieldCheck className="size-4"/><h2 className="font-semibold">WhatsApp consent evidence</h2></div><p className="mt-1 text-sm text-muted-foreground">SMS consent does not count as WhatsApp consent. Capture the purpose, source, and exact wording.</p><Form action={storeConsent.url()} method="post" className="mt-4 grid gap-3 md:grid-cols-2">{({ processing, errors }) => <><div className="grid gap-2"><Label htmlFor="consent_contact_id">Contact</Label><select id="consent_contact_id" name="contact_id" required className="h-10 rounded-md border bg-background px-3"><option value="">Choose a contact</option>{contacts.map((contact) => <option key={contact.id} value={contact.id}>{contact.first_name} {contact.last_name || ''} · {contact.phone}</option>)}</select>{errors.contact_id && <span className="text-sm text-destructive">{errors.contact_id}</span>}</div><div className="grid gap-2"><Label htmlFor="purpose">Purpose</Label><select id="purpose" name="purpose" required className="h-10 rounded-md border bg-background px-3"><option value="marketing">Marketing</option><option value="utility">Utility</option><option value="authentication">Authentication</option></select></div><div className="grid gap-2"><Label htmlFor="source">Consent source</Label><input id="source" name="source" required maxLength={120} placeholder="Web form, paper form, etc." className="h-10 rounded-md border bg-background px-3"/>{errors.source && <span className="text-sm text-destructive">{errors.source}</span>}</div><div className="grid gap-2"><Label htmlFor="granted_at">Granted at</Label><input id="granted_at" name="granted_at" type="datetime-local" required className="h-10 rounded-md border bg-background px-3"/></div><div className="grid gap-2 md:col-span-2"><Label htmlFor="wording">Exact opt-in wording</Label><textarea id="wording" name="wording" required maxLength={2000} rows={3} className="rounded-md border bg-background p-3"/>{errors.wording && <span className="text-sm text-destructive">{errors.wording}</span>}</div><div><Button disabled={processing}>Record consent</Button></div></>}</Form>{consents.length > 0 && <ul className="mt-4 divide-y">{consents.map((consent) => <li key={consent.id} className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"><span>{consent.contact.first_name} {consent.contact.last_name || ''} · {consent.purpose} · {consent.source} {consent.revoked_at && <Badge variant="muted">Revoked</Badge>}</span>{!consent.revoked_at && <Form action={revokeConsent.url(consent.id)} method="delete">{({ processing }) => <Button variant="outline" size="sm" disabled={processing}>Revoke</Button>}</Form>}</li>)}</ul>}</section>
            <section className="rounded-lg border bg-card p-5"><div className="flex items-center gap-2"><Megaphone className="size-4"/><h2 className="font-semibold">Campaign authoring</h2></div><p className="mt-1 text-sm text-muted-foreground">Drafts capture an immutable audience snapshot. Only opted-in eligible recipients count. Sending is not enabled yet.</p><Form action={storeCampaign.url()} method="post" className="mt-4 grid gap-3 md:grid-cols-2">{({ processing, errors }) => <><div className="grid gap-2"><Label htmlFor="campaign_name">Campaign name</Label><input id="campaign_name" name="name" required maxLength={120} className="h-10 rounded-md border bg-background px-3"/>{errors.name && <span className="text-sm text-destructive">{errors.name}</span>}</div><div className="grid gap-2"><Label htmlFor="connector_id_campaign">Approved sender</Label><select id="connector_id_campaign" name="connector_id" required className="h-10 rounded-md border bg-background px-3">{connectors.filter((item) => item.enabled && item.approval_status === 'approved').map(({ connector }) => <option key={connector.id} value={connector.id}>{connector.connector_name || connector.sender_number}</option>)}</select>{errors.connector_id && <span className="text-sm text-destructive">{errors.connector_id}</span>}</div><div className="grid gap-2"><Label htmlFor="template_id">Approved template</Label><select id="template_id" name="template_id" required className="h-10 rounded-md border bg-background px-3">{templates.map((template) => <option key={template.id} value={template.id}>{template.name} · {template.language}</option>)}</select></div><div className="grid gap-2"><Label htmlFor="contact_list_id">Contact list (optional)</Label><select id="contact_list_id" name="contact_list_id" className="h-10 rounded-md border bg-background px-3"><option value="">All contacts</option>{lists.map((list) => <option key={list.id} value={list.id}>{list.name}</option>)}</select></div><div className="grid gap-2"><Label htmlFor="contact_segment_id">Saved segment (optional)</Label><select id="contact_segment_id" name="contact_segment_id" className="h-10 rounded-md border bg-background px-3"><option value="">No segment</option>{segments.map((segment) => <option key={segment.id} value={segment.id}>{segment.name}</option>)}</select></div><div className="grid gap-2"><Label htmlFor="media_asset_id">Media (optional)</Label><select id="media_asset_id" name="media_asset_id" className="h-10 rounded-md border bg-background px-3"><option value="">No media</option>{media.map((asset) => <option key={asset.id} value={asset.id}>{asset.original_name}</option>)}</select></div><input type="hidden" name="schedule_type" value="now"/><input type="hidden" name="timezone" value="Africa/Accra"/><div className="md:col-span-2"><Button disabled={processing || templates.length === 0 || connectors.every((item) => !item.enabled || item.approval_status !== 'approved')}>Save and validate draft</Button></div></>}</Form>{campaigns.length > 0 && <div className="mt-5 overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="py-2">Campaign</th><th>Sender</th><th>Template</th><th>Eligible</th><th>Estimate</th><th>Status</th><th>Actions</th></tr></thead><tbody>{campaigns.map((campaign) => <tr key={campaign.id} className="border-b"><td className="py-2">{campaign.name}</td><td>{campaign.connector.connector_name || campaign.connector.sender_number}</td><td>{campaign.template.name}</td><td>{campaign.estimated_recipients}</td><td>{campaign.estimated_cost === null ? campaign.estimate_disclaimer?.message : `${campaign.estimate_currency} ${campaign.estimated_cost}`}</td><td><Badge variant="muted">{campaign.status}</Badge></td><td className="space-x-2 py-2">{sendingEnabled && campaign.status === 'draft' && <><Form action={dispatchCampaign.url(campaign.id)} method="post"><Button size="sm">Queue</Button></Form><Form action={testCampaign.url(campaign.id)} method="post"><Button variant="outline" size="sm">Test</Button></Form></>}{sendingEnabled && campaign.control_state === 'active' && campaign.status !== 'draft' && <Form action={pauseCampaign.url(campaign.id)} method="post"><Button variant="outline" size="sm">Pause</Button></Form>}{sendingEnabled && campaign.control_state === 'paused' && <Form action={resumeCampaign.url(campaign.id)} method="post"><Button size="sm">Resume</Button></Form>}{sendingEnabled && !['cancelled', 'completed'].includes(campaign.control_state) && <Form action={cancelCampaign.url(campaign.id)} method="post"><Button variant="destructive" size="sm">Cancel</Button></Form>}</td></tr>)}</tbody></table></div>}</section>
            <section className="rounded-lg border bg-card p-5"><h2 className="font-semibold">Media library</h2><p className="mt-1 text-sm text-muted-foreground">JPEG, PNG, WebP, MP4, and PDF up to {maxUploadKilobytes} KB.</p><Form action={whatsappRoutes.media.store.url()} method="post" encType="multipart/form-data" className="mt-4 flex flex-wrap items-end gap-3">{({ processing, errors }) => <><div className="grid gap-2"><Label htmlFor="file">Upload a file</Label><input id="file" type="file" name="file" required accept="image/jpeg,image/png,image/webp,video/mp4,application/pdf" className="max-w-full text-sm" />{errors.file && <span className="text-sm text-destructive">{errors.file}</span>}</div><Button disabled={processing}><Upload className="mr-2 size-4" />Upload</Button></>}</Form>{media.length > 0 && <ul className="mt-4 divide-y">{media.map((asset) => <li key={asset.id} className="flex justify-between gap-4 py-2 text-sm"><span>{asset.original_name} <span className="text-muted-foreground">({asset.mime_type})</span></span><span className="text-muted-foreground">{Math.ceil(asset.size_bytes / 1024)} KB</span></li>)}</ul>}</section>
            <section className="rounded-lg border bg-card p-5"><div className="flex items-center justify-between gap-4"><div><div className="flex items-center gap-2"><ChartNoAxesCombined className="size-4"/><h2 className="font-semibold">Delivery and usage report</h2></div><p className="mt-1 text-sm text-muted-foreground">Totals are scoped to this client and only billed usage with provider evidence is included.</p></div><a href={whatsappMessageRoutes.export.url()} className="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted"><Download className="mr-2 size-4"/>Export message history</a></div><div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-7"><ReportMetric title="Billable messages" value={usageReport.billable_messages}/><ReportMetric title="Delivered" value={deliveredCount}/><ReportMetric title="Failed" value={failedCount}/><ReportMetric title="Client charges" value={`${whatsappWallet?.currency || 'GHS'} ${usageReport.charges}`}/><ReportMetric title="Expected provider cost" value={`${whatsappWallet?.currency || 'GHS'} ${usageReport.provider_cost}`}/><ReportMetric title="Gross margin" value={`${whatsappWallet?.currency || 'GHS'} ${usageReport.gross_margin}`}/><ReportMetric title="Unknown outcomes" value={usageReport.status_counts.unknown || 0}/></div><div className="mt-5 overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="py-2">Market</th><th>Category</th><th>Messages</th><th>Client charges</th><th>Provider cost</th><th>Gross margin</th></tr></thead><tbody>{usageReport.breakdown.map((row, index) => <tr key={`${row.market}-${row.category}-${index}`} className="border-b"><td className="py-2">{row.market || 'Unclassified'}</td><td className="capitalize">{row.category || 'Unclassified'}</td><td>{row.messages}</td><td>{row.charges}</td><td>{row.provider_cost}</td><td>{(Number(row.charges) - Number(row.provider_cost)).toFixed(4)}</td></tr>)}</tbody></table>{usageReport.breakdown.length === 0 && <p className="py-4 text-sm text-muted-foreground">No billable usage has been recorded.</p>}</div><div className="mt-5 overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="py-2">Campaign</th><th>Recipient</th><th>Status</th><th>Provider message</th><th>Last error</th><th>Delivered</th><th>Read</th></tr></thead><tbody>{messages.map((message) => <tr key={message.id} className="border-b"><td className="py-2">{message.campaign.name}</td><td>{message.phone}</td><td><Badge variant={message.status === 'delivered' || message.status === 'read' ? 'success' : 'muted'}>{message.status}</Badge></td><td>{message.provider_message_id || '—'}</td><td className="max-w-64 truncate">{message.last_error || '—'}</td><td>{message.delivered_at || '—'}</td><td>{message.read_at || '—'}</td></tr>)}</tbody></table>{messages.length === 0 && <p className="py-4 text-sm text-muted-foreground">No WhatsApp message history yet.</p>}</div></section>
            <section className="rounded-lg border bg-card p-5"><div className="flex items-center gap-2"><WalletCards className="size-4"/><h2 className="font-semibold">Shared wallet usage</h2></div>{whatsappWallet ? <><div className="mt-3 grid gap-3 sm:grid-cols-3"><ReportMetric title="Wallet balance" value={`${whatsappWallet.currency} ${whatsappWallet.balance}`}/><ReportMetric title="Reserved" value={`${whatsappWallet.currency} ${whatsappWallet.reserved}`}/><ReportMetric title="Available" value={`${whatsappWallet.currency} ${whatsappWallet.available}`}/></div><p className="mt-3 text-sm text-muted-foreground">WhatsApp reservations and charges use the shared wallet; package units remain unchanged.</p><div className="mt-4 overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="py-2">Date</th><th>Type</th><th>Amount</th><th>Balance after</th><th>Description</th><th>Reference</th></tr></thead><tbody>{whatsappWallet.transactions.map((transaction) => <tr key={transaction.id} className="border-b"><td className="py-2">{transaction.created_at}</td><td>{transaction.type}</td><td>{transaction.amount}</td><td>{transaction.balance_after}</td><td>{transaction.description}</td><td>{transaction.reference || '—'}</td></tr>)}</tbody></table>{whatsappWallet.transactions.length === 0 && <p className="py-4 text-sm text-muted-foreground">No WhatsApp wallet transactions recorded.</p>}</div></> : <p className="mt-3 text-sm text-muted-foreground">A shared wallet will appear here when one is available for this client.</p>}</section>
        </div>
    </>;
}

function ReportMetric({ title, value }: { title: string; value: string | number }) {
    return <div className="rounded-md border bg-muted/30 p-3"><p className="text-xs text-muted-foreground">{title}</p><p className="mt-1 font-semibold">{value}</p></div>;
}

function previewComponents(components: unknown): { type: string; text: string }[] {
    if (!Array.isArray(components)) return [];
    return components.flatMap((component) => {
        if (!component || typeof component !== 'object') return [];
        const item = component as { type?: unknown; text?: unknown; format?: unknown };
        const type = typeof item.type === 'string' ? item.type.toUpperCase() : 'TEMPLATE';
        const text = typeof item.text === 'string' ? item.text : typeof item.format === 'string' ? item.format : '';
        return [{ type, text }];
    });
}
