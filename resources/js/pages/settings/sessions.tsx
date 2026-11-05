import { Head, router } from '@inertiajs/react';
import { MonitorSmartphone, ShieldAlert } from 'lucide-react';
import { useConfirm } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { urls } from '@/lib/urls';
import type { SessionRow } from '@/types';

type PageProps = {
    sessions: SessionRow[];
    supported: boolean;
};

export default function AccountSessions({ sessions, supported }: PageProps) {
    const confirm = useConfirm();

    const endSession = async (session: SessionRow): Promise<void> => {
        const confirmed = await confirm({
            title: `Sign out ${session.device}?`,
            description:
                'The device is signed out immediately and has to sign in again to get back in.',
            confirmLabel: 'Sign out device',
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        router.delete(urls.account.sessions.destroy(session.session_id), {
            preserveScroll: true,
        });
    };

    const endOthers = async (): Promise<void> => {
        const confirmed = await confirm({
            title: 'Sign out every other session?',
            description:
                'All sessions besides this device end immediately. You stay signed in here.',
            confirmLabel: 'Sign out other sessions',
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        router.delete(urls.account.sessions.destroyOthers, {
            preserveScroll: true,
        });
    };

    const others = sessions.filter((session) => !session.is_current).length;

    return (
        <>
            <Head title="Sessions" />

            <h1 className="sr-only">Sessions</h1>

            <Heading
                variant="small"
                title="Active sessions"
                description="Devices signed in to your account. Sign out anything you do not recognise."
            />

            {!supported ? (
                <Card className="border-warning/30 bg-warning-soft">
                    <CardContent className="text-sm">
                        This application is not storing sessions in the
                        database, so the device list is unavailable.
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <MonitorSmartphone className="size-4" />
                            {sessions.length}{' '}
                            {sessions.length === 1 ? 'session' : 'sessions'}
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Sessions expire after inactivity; signing out ends
                            them immediately.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="divide-y divide-border/60 px-0">
                        {sessions.length === 0 ? (
                            <p className="text-muted-foreground px-4 py-6 text-sm">
                                No sessions are recorded for this account.
                            </p>
                        ) : (
                            sessions.map((session) => (
                                <div
                                    key={session.session_id}
                                    className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                                >
                                    <div className="space-y-1">
                                        <p className="flex items-center gap-2 text-sm font-medium">
                                            {session.device}
                                            {session.is_current && (
                                                <Badge variant="success">
                                                    This device
                                                </Badge>
                                            )}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {session.ip_address ?? 'Unknown IP'}{' '}
                                            · last active{' '}
                                            {new Date(
                                                session.last_active_at,
                                            ).toLocaleString()}
                                        </p>
                                    </div>

                                    {!session.is_current && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                void endSession(session)
                                            }
                                        >
                                            Sign out
                                        </Button>
                                    )}
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            )}

            <Separator />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-start gap-2">
                    <ShieldAlert className="text-muted-foreground mt-0.5 size-4" />
                    <p className="text-muted-foreground max-w-md text-sm">
                        Signing out other devices keeps you signed in here.
                        Password and two-factor settings live on the security
                        screen.
                    </p>
                </div>

                <Button
                    variant="destructive"
                    disabled={others === 0}
                    onClick={() => void endOthers()}
                >
                    Sign out other sessions
                </Button>
            </div>
        </>
    );
}

AccountSessions.layout = {
    breadcrumbs: [
        {
            title: 'Sessions',
            href: urls.account.sessions.index,
        },
    ],
};
