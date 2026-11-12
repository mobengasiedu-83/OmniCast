import { Form, Head } from '@inertiajs/react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Coins } from 'lucide-react';
import { store as storeRate, approve as approveRate } from '@/routes/admin/whatsapp-pricing';

type ClientOption = { id: number; name: string };
type Rate = {
    id: number; market: string; category: string; currency: string; meta_rate: string; volume_from: number; volume_to: number | null;
    provider_fee: string; direct_cost: string; fx_rate: string; markup_percent: string;
    minimum_price: string; selling_price: string; status: string; source: string;
    effective_from: string; effective_to: string | null; client: { name: string } | null;
};

export default function WhatsAppPricing({ rates, clients, canManage }: { rates: Rate[]; clients: ClientOption[]; canManage: boolean }) {
    return <>
        <Head title="WhatsApp pricing" />
        <PageHeader icon={Coins} title="WhatsApp pricing" description="Manage effective market and category rates. New rates require approval before client estimates or dispatch can use them." />
        <div className="mt-6 grid gap-6">
            {canManage && <section className="rounded-lg border bg-card p-5">
                <h2 className="font-semibold">Add rate for approval</h2>
                <p className="mt-1 text-sm text-muted-foreground">Selling price (GHS) = max(((Meta USD rate × FX) + provider fee + direct cost) × (1 + markup), minimum price).</p>
                <Form action={storeRate.url()} method="post" className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{({ processing, errors }) => <>
                    <Field name="market" label="Market (ISO country code)" defaultValue="GH" error={errors.market} />
                    <div className="grid gap-2"><Label htmlFor="client_id">Pricing scope</Label><select id="client_id" name="client_id" className="h-10 rounded-md border bg-background px-3"><option value="">Platform default</option>{clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}</select>{errors.client_id && <p className="text-sm text-destructive">{errors.client_id}</p>}</div>
                    <div className="grid gap-2"><Label htmlFor="category">Category</Label><select id="category" name="category" className="h-10 rounded-md border bg-background px-3"><option value="marketing">Marketing</option><option value="utility">Utility</option><option value="authentication">Authentication</option></select>{errors.category && <p className="text-sm text-destructive">{errors.category}</p>}</div>
                    <Field name="meta_rate" label="Meta rate (USD)" type="number" step="0.000001" error={errors.meta_rate} />
                    <Field name="provider_fee" label="Provider fee (GHS)" type="number" step="0.000001" defaultValue="0" error={errors.provider_fee} />
                    <Field name="direct_cost" label="Direct cost (GHS)" type="number" step="0.000001" defaultValue="0" error={errors.direct_cost} />
                    <Field name="fx_rate" label="GHS per USD" type="number" step="0.000001" error={errors.fx_rate} />
                    <Field name="markup_percent" label="Markup (%)" type="number" step="0.0001" defaultValue="0" error={errors.markup_percent} />
                    <Field name="minimum_price" label="Minimum price (GHS)" type="number" step="0.0001" defaultValue="0" error={errors.minimum_price} />
                    <Field name="volume_from" label="Volume from (recipients)" type="number" step="1" defaultValue="0" error={errors.volume_from} />
                    <Field name="volume_to" label="Volume to (optional)" type="number" step="1" error={errors.volume_to} />
                    <Field name="source" label="Source / approval reference" error={errors.source} />
                    <Field name="effective_from" label="Effective from" type="date" error={errors.effective_from} />
                    <Field name="effective_to" label="Effective to (optional)" type="date" error={errors.effective_to} />
                    <div className="flex items-end"><Button disabled={processing}>Save pending rate</Button></div>
                </>}</Form>
            </section>}
            <section className="overflow-hidden rounded-lg border bg-card">
                <div className="border-b p-5"><h2 className="font-semibold">Rate history</h2></div>
                <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-muted/50"><tr>{['Market', 'Category', 'Volume tier', 'Scope', 'Meta USD', 'Provider fee', 'FX', 'Markup', 'Selling GHS', 'Effective', 'Status', ''].map((label) => <th key={label} className="whitespace-nowrap px-4 py-3 font-medium">{label}</th>)}</tr></thead><tbody className="divide-y">{rates.map((rate) => <tr key={rate.id}><td className="px-4 py-3">{rate.market}</td><td className="px-4 py-3 capitalize">{rate.category}</td><td className="px-4 py-3">{rate.volume_from}–{rate.volume_to ?? '∞'}</td><td className="px-4 py-3">{rate.client?.name || 'Platform'}</td><td className="px-4 py-3">{rate.meta_rate}</td><td className="px-4 py-3">{rate.provider_fee}</td><td className="px-4 py-3">{rate.fx_rate}</td><td className="px-4 py-3">{rate.markup_percent}%</td><td className="px-4 py-3">{rate.selling_price}</td><td className="whitespace-nowrap px-4 py-3">{rate.effective_from}{rate.effective_to ? ` – ${rate.effective_to}` : ' onward'}</td><td className="px-4 py-3"><Badge variant={rate.status === 'approved' ? 'success' : 'muted'}>{rate.status}</Badge></td><td className="px-4 py-3">{canManage && rate.status === 'pending' && <Form action={approveRate.url(rate.id)} method="post">{({ processing }) => <Button size="sm" disabled={processing}>Approve</Button>}</Form>}</td></tr>)}</tbody></table></div>
                {rates.length === 0 && <p className="p-5 text-sm text-muted-foreground">No WhatsApp pricing rates have been added.</p>}
            </section>
        </div>
    </>;
}

function Field({ name, label, type = 'text', step, defaultValue, error }: { name: string; label: string; type?: string; step?: string; defaultValue?: string; error?: string }) {
    return <div className="grid content-start gap-2"><Label htmlFor={name}>{label}</Label><input id={name} name={name} type={type} step={step} defaultValue={defaultValue} required={!name.endsWith('_to')} className="h-10 min-w-0 rounded-md border bg-background px-3" />{error && <p className="text-sm text-destructive">{error}</p>}</div>;
}
