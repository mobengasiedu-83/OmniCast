import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, PlusCircle } from 'lucide-react';
import PageHeader from '@/components/page-header';
import TurnstileField from '@/components/support/turnstile-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store } from '@/routes/support/tickets';
import type {
    SupportAttachmentLimits,
    SupportCategoryOption,
    SupportContact,
    SupportOption,
} from '@/types';

type PageProps = {
    categories: SupportCategoryOption[];
    priorities: SupportOption[];
    campaigns: SupportOption[];
    transactions: SupportOption[];
    attachments: SupportAttachmentLimits;
    contact: SupportContact;
};

/**
 * Open a support ticket.
 *
 * The category and priority decide who answers and how fast; the optional campaign
 * or wallet movement saves the first round trip, because the agent can see exactly
 * which send or charge the client means. The challenge in front of the form is the
 * same Turnstile check the public contact page uses.
 */
export default function CreateTicket({
    categories,
    priorities,
    campaigns,
    transactions,
    attachments,
    contact,
}: PageProps) {
    return (
        <>
            <Head title="New ticket" />

            <div className="grid gap-6">
                <PageHeader
                    icon={PlusCircle}
                    title="New support ticket"
                    description="Give the team enough to act on the first reply: what you expected, what happened, and which send or charge it concerns."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={index()}>
                                <ArrowLeft />
                                My tickets
                            </Link>
                        </Button>
                    }
                />

                <Card>
                    <CardContent>
                        <Form
                            {...store.form()}
                            encType="multipart/form-data"
                            className="grid max-w-2xl gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="category">
                                                Category *
                                            </Label>
                                            <select
                                                id="category"
                                                name="category"
                                                required
                                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                            >
                                                {categories.map((option) => (
                                                    <option
                                                        key={option.slug}
                                                        value={option.slug}
                                                    >
                                                        {option.name}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors.category && (
                                                <p className="text-xs text-destructive">
                                                    {errors.category}
                                                </p>
                                            )}
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="priority">
                                                Priority
                                            </Label>
                                            <select
                                                id="priority"
                                                name="priority"
                                                defaultValue="normal"
                                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                            >
                                                {priorities.map((option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors.priority && (
                                                <p className="text-xs text-destructive">
                                                    {errors.priority}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="subject">Subject *</Label>
                                        <Input
                                            id="subject"
                                            name="subject"
                                            required
                                            maxLength={200}
                                        />
                                        {errors.subject && (
                                            <p className="text-xs text-destructive">
                                                {errors.subject}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="description">
                                            What is happening? *
                                        </Label>
                                        <textarea
                                            id="description"
                                            name="description"
                                            required
                                            rows={6}
                                            minLength={20}
                                            placeholder="Which campaign, contact list or payment is involved, and what did you expect instead?"
                                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        />
                                        {errors.description && (
                                            <p className="text-xs text-destructive">
                                                {errors.description}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="campaign_id">
                                                Related campaign
                                            </Label>
                                            <select
                                                id="campaign_id"
                                                name="campaign_id"
                                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                            >
                                                <option value="">
                                                    Not about a campaign
                                                </option>
                                                {campaigns.map((option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors.campaign_id && (
                                                <p className="text-xs text-destructive">
                                                    {errors.campaign_id}
                                                </p>
                                            )}
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="wallet_transaction_id">
                                                Related wallet movement
                                            </Label>
                                            <select
                                                id="wallet_transaction_id"
                                                name="wallet_transaction_id"
                                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                            >
                                                <option value="">
                                                    Not about a payment
                                                </option>
                                                {transactions.map((option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors.wallet_transaction_id && (
                                                <p className="text-xs text-destructive">
                                                    {errors.wallet_transaction_id}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="attachments">
                                            Screenshots or files
                                        </Label>
                                        <input
                                            id="attachments"
                                            name="attachments[]"
                                            type="file"
                                            multiple
                                            accept={attachments.mimes
                                                .map((mime) => `.${mime}`)
                                                .join(',')}
                                            className="block w-full rounded-md border border-input bg-background px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-2 file:py-1 file:text-xs"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Up to {attachments.max_files} files,{' '}
                                            {Math.round(
                                                attachments.max_kilobytes / 1024,
                                            )}{' '}
                                            MB each. Files stay private to the
                                            ticket.
                                        </p>
                                        {errors['attachments.0'] && (
                                            <p className="text-xs text-destructive">
                                                {errors['attachments.0']}
                                            </p>
                                        )}
                                    </div>

                                    <TurnstileField
                                        error={errors['cf-turnstile-response']}
                                    />

                                    <div className="flex flex-wrap items-center gap-3">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Open ticket
                                        </Button>
                                        <p className="text-xs text-muted-foreground">
                                            {contact.response_time ??
                                                'You get a reference number as soon as the ticket is open.'}
                                        </p>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
