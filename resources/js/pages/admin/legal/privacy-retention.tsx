import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy as disableRetention,
    update as updateRetention,
} from '@/actions/App/Http/Controllers/Admin/PrivacyRetentionController';
import { urls } from '@/lib/urls';

type RetentionPolicy = {
    category: string;
    label: string;
    description: string;
    days: number | null;
    approved_at: string | null;
    approved_by: string | null;
};

type PageProps = {
    policies: RetentionPolicy[];
};

export default function PrivacyRetention({ policies }: PageProps) {
    return (
        <>
            <Head title="Privacy retention" />

            <div className="grid gap-6">
                <PageHeader
                    title="Data retention"
                    description="Retention windows stay disabled until a platform legal administrator records an approved period."
                />

                <p className="rounded-lg border border-border/60 bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                    No deletion runs for these categories until a legally
                    approved window is saved. Existing environment settings
                    can extend an approved period, but cannot enable pruning or
                    shorten it. Legal, billing, suppression and audit evidence
                    is not covered by these automated policies.
                </p>

                <div className="grid gap-4">
                    {policies.map((policy) => (
                        <RetentionPolicyCard
                            key={policy.category}
                            policy={policy}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

PrivacyRetention.layout = {
    breadcrumbs: [
        {
            title: 'Data retention',
            href: urls.admin.legal.privacyRetention,
        },
    ],
};

function RetentionPolicyCard({ policy }: { policy: RetentionPolicy }) {
    const form = useForm({
        days: policy.days === null ? '' : String(policy.days),
        legal_approval: false,
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.put(
            updateRetention.url(policy.category),
            { preserveScroll: true },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>{policy.label}</CardTitle>
                <p className="text-sm text-muted-foreground">
                    {policy.description}
                </p>
            </CardHeader>
            <CardContent className="grid gap-4">
                <p className="text-xs text-muted-foreground">
                    {policy.approved_at
                        ? `Approved by ${policy.approved_by ?? 'a platform administrator'} on ${new Date(policy.approved_at).toLocaleDateString()}.`
                        : 'No approved period; automatic deletion is disabled.'}
                </p>

                <form className="grid gap-3 sm:max-w-xl" onSubmit={submit}>
                    <div className="grid gap-1.5">
                        <Label htmlFor={`retention-${policy.category}`}>
                            Approved retention (days)
                        </Label>
                        <Input
                            id={`retention-${policy.category}`}
                            type="number"
                            min={1}
                            max={36500}
                            value={form.data.days}
                            onChange={(event) =>
                                form.setData('days', event.target.value)
                            }
                            placeholder="No approved period"
                        />
                        {form.errors.days && (
                            <p className="text-xs text-destructive">
                                {form.errors.days}
                            </p>
                        )}
                    </div>

                    <label className="flex items-start gap-2 text-sm">
                        <Checkbox
                            checked={form.data.legal_approval}
                            onCheckedChange={(checked) =>
                                form.setData('legal_approval', checked === true)
                            }
                        />
                        I confirm this retention period has legal approval.
                    </label>
                    {form.errors.legal_approval && (
                        <p className="text-xs text-destructive">
                            {form.errors.legal_approval}
                        </p>
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={form.processing}>
                            Save approved period
                        </Button>
                        {policy.days !== null && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() =>
                                    router.delete(
                                        disableRetention.url(policy.category),
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Disable pruning
                            </Button>
                        )}
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
