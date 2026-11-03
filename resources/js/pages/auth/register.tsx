import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { create } from '@/routes/client-registration';

export default function RegisterRedirect() {
    useEffect(() => {
        router.visit(create.url());
    }, []);

    return <>
        <Head title="Client account signup" />
        <p className="text-sm text-muted-foreground">Opening client account signup…</p>
        <Button asChild variant="link"><Link href={create()}>Continue</Link></Button>
    </>;
}

RegisterRedirect.layout = { title: 'Client account signup', description: 'Create a verified client account' };
