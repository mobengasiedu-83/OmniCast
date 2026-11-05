import { Form, Head, Link } from '@inertiajs/react';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { urls } from '@/lib/urls';
import { assign } from '@/routes/admin/settings/plans';
import type { Paginated } from '@/types/omnicast';

type ClientRow = { id: number; name: string; plan_code: string };
type Props = { plans: string[]; clients: Paginated<ClientRow> };

export default function ClientPlans({ plans, clients }: Props) {
    return (
        <>
            <Head title="Client plans" />
            <PageHeader
                title="Client plans"
                description="Assign each organisation a plan. Plan settings inherit global defaults and may be overridden by client settings."
                actions={
                    <Button asChild variant="outline">
                        <Link href={urls.admin.settings.index}>
                            Global settings
                        </Link>
                    </Button>
                }
            />
            <div className="mt-6 space-y-3">
                {clients.data.map((client) => (
                    <Form
                        key={client.id}
                        {...assign.form(client.id)}
                        className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-end"
                    >
                        {({ processing }) => (
                            <>
                                <div className="min-w-0 flex-1">
                                    <div className="font-medium">
                                        {client.name}
                                    </div>
                                    <Label
                                        htmlFor={`plan-${client.id}`}
                                        className="mt-2 block"
                                    >
                                        Assigned plan
                                    </Label>
                                    <select
                                        id={`plan-${client.id}`}
                                        name="plan_code"
                                        defaultValue={client.plan_code}
                                        className="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm sm:max-w-sm"
                                    >
                                        {plans.map((plan) => (
                                            <option key={plan} value={plan}>
                                                {plan}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <Button disabled={processing}>
                                    {processing ? 'Saving…' : 'Save plan'}
                                </Button>
                            </>
                        )}
                    </Form>
                ))}
            </div>
            <div className="mt-5">
                <Pagination paginator={clients} noun="clients" />
            </div>
        </>
    );
}
