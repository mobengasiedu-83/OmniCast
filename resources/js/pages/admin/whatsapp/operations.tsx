import { Head, Link } from '@inertiajs/react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Gauge } from 'lucide-react';
import { urls } from '@/lib/urls';

type Provider = { id: number; name: string; driver: string; status: string; is_enabled: boolean; tested_at: string | null; last_test_error: string | null; connectors_count: number };
type ConsentStat = { purpose: string; total: number; active: number; revoked: number };
type Campaign = { id: number; name: string; status: string; estimated_recipients: number; created_at: string; client: { name: string } };
type Message = { id: number; status: string; provider_message_id: string | null; created_at: string; campaign: { name: string; client: { name: string } } };

export default function WhatsAppOperations({ sendingEnabled, eventsEnabled, providerHealth, campaignStatuses, recipientStatuses, unknownReservations, eventQueue, reconciliation, usage, consents, recentCampaigns, recentMessages, launchFlags }: {
    sendingEnabled: boolean; eventsEnabled: boolean; providerHealth: Provider[]; campaignStatuses: Record<string, number>; recipientStatuses: Record<string, number>;
    unknownReservations: { open: number; overdue: number; holdDays: number }; eventQueue: { unmatched: number; unverified: number };
    reconciliation: { openVariances: number; invoicesToReview: number }; usage: { billableMessages: number; charges: string; providerCost: string; grossMargin: string };
    consents: ConsentStat[]; recentCampaigns: Campaign[]; recentMessages: Message[];
    launchFlags: { inboundMessaging: boolean; readReceipts: boolean; providerTemplateSubmission: boolean; productionBillingEvidence: boolean };
}) {
    return <>
        <Head title="WhatsApp operations" />
        <PageHeader icon={Gauge} title="WhatsApp operations" description="Provider health, delivery and billing outcomes, compliance evidence, and production feature gates." />
        <div className="mt-6 space-y-6">
            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Metric title="Campaigns" value={Object.values(campaignStatuses).reduce((sum, count) => sum + count, 0)} detail={Object.entries(campaignStatuses).map(([key, count]) => `${key}: ${count}`).join(' · ') || 'No campaigns'} />
                <Metric title="Messages" value={Object.values(recipientStatuses).reduce((sum, count) => sum + count, 0)} detail={Object.entries(recipientStatuses).map(([key, count]) => `${key}: ${count}`).join(' · ') || 'No messages'} />
                <Metric title="Usage / margin" value={`${usage.charges} GHS`} detail={`${usage.billableMessages} billed · ${usage.providerCost} cost · ${usage.grossMargin} gross margin`} />
                <Metric title="Open exceptions" value={unknownReservations.overdue + reconciliation.openVariances} detail={`${unknownReservations.overdue} overdue unknown holds · ${reconciliation.openVariances} invoice variances`} />
            </section>
            <section className="rounded-lg border bg-card p-5"><h2 className="font-semibold">Provider health</h2><div className="mt-3 overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b">{['Provider', 'Driver', 'Status', 'Enabled', 'Connectors', 'Last test', 'Last error'].map((heading) => <th key={heading} className="px-3 py-2">{heading}</th>)}</tr></thead><tbody>{providerHealth.map((provider) => <tr key={provider.id} className="border-b"><td className="px-3 py-2">{provider.name}</td><td className="px-3 py-2">{provider.driver}</td><td className="px-3 py-2">{provider.status}</td><td className="px-3 py-2">{provider.is_enabled ? 'Enabled' : 'Disabled'}</td><td className="px-3 py-2">{provider.connectors_count}</td><td className="px-3 py-2">{provider.tested_at || 'Not tested'}</td><td className="px-3 py-2">{provider.last_test_error || '—'}</td></tr>)}</tbody></table>{providerHealth.length === 0 && <p className="py-4 text-sm text-muted-foreground">No providers configured.</p>}</div></section>
            <section className="grid gap-4 lg:grid-cols-2">
                <div className="rounded-lg border bg-card p-5"><h2 className="font-semibold">Compliance evidence</h2><div className="mt-3 overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="py-2">Purpose</th><th>Total</th><th>Active</th><th>Revoked</th></tr></thead><tbody>{consents.map((row) => <tr key={row.purpose} className="border-b"><td className="py-2 capitalize">{row.purpose}</td><td>{row.total}</td><td>{row.active}</td><td>{row.revoked}</td></tr>)}</tbody></table>{consents.length === 0 && <p className="py-3 text-sm text-muted-foreground">No WhatsApp consent records.</p>}</div><p className="mt-3 text-xs text-muted-foreground">Consent evidence is tenant-owned and revocation is retained. Retention, access, and erasure requests use the existing Privacy Requests and Data Retention workflows.</p><Link href={urls.admin.legal.privacyRetention} className="mt-3 inline-flex text-sm font-medium text-primary underline-offset-4 hover:underline">Review approved retention periods</Link></div>
                <div className="rounded-lg border bg-card p-5"><h2 className="font-semibold">Launch gates</h2><div className="mt-3 grid gap-2 text-sm"><Flag title="Production sending" enabled={sendingEnabled} /><Flag title="Signed provider event contract" enabled={eventsEnabled} /><Flag title="Delivery-based billable evidence" enabled={launchFlags.productionBillingEvidence} /><Flag title="Inbound messaging" enabled={launchFlags.inboundMessaging} /><Flag title="Read receipts" enabled={launchFlags.readReceipts} /><Flag title="Provider template submission" enabled={launchFlags.providerTemplateSubmission} /></div><p className="mt-3 text-xs text-muted-foreground">Enable a feature only after its provider contract, security review, and applicable release checks are signed off.</p></div>
            </section>
            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><Metric title="Unknown reservations" value={unknownReservations.open} detail={`${unknownReservations.overdue} held past ${unknownReservations.holdDays} days; no automatic release`} /><Metric title="Unmatched events" value={eventQueue.unmatched} detail={`${eventQueue.unverified} unverified event records`} /><Metric title="Invoices to review" value={reconciliation.invoicesToReview} detail={`${reconciliation.openVariances} open line or total variances`} /><Metric title="Dispatch mode" value={sendingEnabled ? 'Enabled' : 'Disabled'} detail={eventsEnabled ? 'Provider events enabled' : 'Provider event contract disabled'} /></section>
            <section className="grid gap-6 xl:grid-cols-2"><History title="Recent campaigns" empty="No WhatsApp campaigns." headers={['Client', 'Campaign', 'Status', 'Eligible']} rows={recentCampaigns.map((campaign) => [campaign.client.name, campaign.name, campaign.status, String(campaign.estimated_recipients)])} /><History title="Recent messages" empty="No WhatsApp messages." headers={['Client', 'Campaign', 'Provider ID', 'Status']} rows={recentMessages.map((message) => [message.campaign.client.name, message.campaign.name, message.provider_message_id || '—', message.status])} /></section>
        </div>
    </>;
}

function Metric({ title, value, detail }: { title: string; value: string | number; detail: string }) {
    return <Card><CardHeader className="pb-2"><CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle></CardHeader><CardContent><p className="text-2xl font-semibold">{value}</p><p className="mt-1 text-xs text-muted-foreground">{detail}</p></CardContent></Card>;
}

function Flag({ title, enabled }: { title: string; enabled: boolean }) {
    return <div className="flex items-center justify-between gap-4"><span>{title}</span><Badge variant={enabled ? 'success' : 'muted'}>{enabled ? 'Enabled' : 'Gated'}</Badge></div>;
}

function History({ title, empty, headers, rows }: { title: string; empty: string; headers: string[]; rows: string[][] }) {
    return <div className="overflow-hidden rounded-lg border bg-card"><div className="border-b p-5"><h2 className="font-semibold">{title}</h2></div><div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b">{headers.map((heading) => <th key={heading} className="px-3 py-2">{heading}</th>)}</tr></thead><tbody>{rows.map((row, index) => <tr key={`${row[0]}-${index}`} className="border-b">{row.map((cell, cellIndex) => <td key={cellIndex} className="px-3 py-2">{cell}</td>)}</tr>)}</tbody></table>{rows.length === 0 && <p className="p-4 text-sm text-muted-foreground">{empty}</p>}</div></div>;
}
