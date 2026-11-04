import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, MailX, ShieldCheck } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';

type PageProps = {
    contact: {
        first_name: string | null;
        phone: string;
        email: string | null;
        organisation: string | null;
        dnd: boolean;
        dnd_at: string | null;
    };
    dndRequestUrl: string;
};

export default function ContactsDnd({ contact, dndRequestUrl }: PageProps) {
    const form = useForm({});

    const alreadyBlocked = contact.dnd;

    const confirm = (): void => {
        form.post(dndRequestUrl);
    };

    return (
        <>
            <Head title="Stop messages" />

            <div className="bg-background flex min-h-screen items-center justify-center px-4 py-10">
                <div className="w-full max-w-md">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {alreadyBlocked ? (
                                    <CheckCircle2 className="text-success size-5" />
                                ) : (
                                    <MailX className="text-brand size-5" />
                                )}
                                {alreadyBlocked
                                    ? 'You are on the DND list'
                                    : 'Stop all messages'}
                            </CardTitle>
                            <CardDescription>
                                {alreadyBlocked
                                    ? 'We are not contacting this number or address on any channel.'
                                    : `Confirm below and ${contact.organisation ?? 'we'} will stop calling, texting and emailing this contact on every channel.`}
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="flex flex-col gap-4">
                            <dl className="grid gap-2 text-sm">
                                <div className="flex items-baseline justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Name
                                    </dt>
                                    <dd className="font-medium">
                                        {contact.first_name ?? '—'}
                                    </dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Phone
                                    </dt>
                                    <dd className="font-mono">{contact.phone}</dd>
                                </div>
                                {contact.email !== null && (
                                    <div className="flex items-baseline justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Email
                                        </dt>
                                        <dd className="font-mono">
                                            {contact.email}
                                        </dd>
                                    </div>
                                )}
                            </dl>

                            {alreadyBlocked ? (
                                <p className="text-muted-foreground flex items-start gap-2 rounded-lg border border-border/60 p-3 text-xs">
                                    <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                                    This decision stays in the DND registry, so
                                    future campaigns cannot reach the number.
                                </p>
                            ) : (
                                <Button
                                    onClick={confirm}
                                    disabled={form.processing}
                                >
                                    <MailX />
                                    {form.processing
                                        ? 'Saving…'
                                        : 'Yes, stop all messages'}
                                </Button>
                            )}
                        </CardContent>
                    </Card>

                    <p className="text-muted-foreground mt-4 text-center text-xs">
                        Changed your mind? Contact the sender directly and they
                        can remove the number from the DND list.
                    </p>
                </div>
            </div>
        </>
    );
}
