import { router } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ClientOption } from '@/types';

/**
 * Lets a platform operator scope the current screen to a client account.
 */
export default function ClientFilter({
    clients,
    value,
    query = {},
}: {
    clients: ClientOption[];
    value: number | null;
    query?: Record<string, string | number | null>;
}) {
    if (clients.length === 0) {
        return null;
    }

    const change = (next: string): void => {
        router.get(
            window.location.pathname,
            { ...query, client_id: next === 'all' ? null : next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <Select value={value ? String(value) : 'all'} onValueChange={change}>
            <SelectTrigger className="w-52" aria-label="Client account">
                <SelectValue placeholder="All clients" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All clients</SelectItem>
                {clients.map((client) => (
                    <SelectItem key={client.id} value={String(client.id)}>
                        {client.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
