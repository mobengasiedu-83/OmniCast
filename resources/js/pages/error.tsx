import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CircleAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { urls } from '@/lib/urls';

type PageProps = {
    status: number;
};

const errorPages: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Access denied',
        description: "You don't have permission to view this page.",
    },
    404: {
        title: 'Page not found',
        description: "The page you're looking for may have moved or no longer exists.",
    },
    419: {
        title: 'Session expired',
        description: 'Your session has expired. Return to the dashboard and try again.',
    },
    429: {
        title: 'Too many requests',
        description: 'Please wait a moment before trying again.',
    },
    500: {
        title: 'Something went wrong',
        description: 'An unexpected error occurred. Please try again later.',
    },
};

export default function ErrorPage({ status }: PageProps) {
    const error = errorPages[status] ?? {
        title: 'Request failed',
        description: 'We could not complete your request.',
    };

    return (
        <>
            <Head title={`${status} - ${error.title}`} />

            <main className="flex min-h-screen items-center justify-center bg-muted/40 p-4">
                <Card className="w-full max-w-lg">
                    <CardHeader className="items-center text-center">
                        <span className="flex size-12 items-center justify-center rounded-full bg-destructive-soft text-destructive">
                            <CircleAlert className="size-6" />
                        </span>
                        <p className="text-sm font-semibold tracking-widest text-muted-foreground uppercase">
                            Error {status}
                        </p>
                        <CardTitle className="text-2xl">{error.title}</CardTitle>
                        <CardDescription>{error.description}</CardDescription>
                    </CardHeader>
                    <CardContent className="flex justify-center">
                        <Button asChild variant="outline">
                            <Link href={urls.dashboard}>
                                <ArrowLeft />
                                Return to dashboard
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
