import { Head, router } from '@inertiajs/react';
import { Inbox, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import SupportTicketTable from '@/components/support/ticket-table';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/admin/support/enquiries';
import type { Paginated, SupportOption, SupportTicketRow } from '@/types';

type PageProps = {
    enquiries: Paginated<SupportTicketRow>;
    filters: { team: string; status: string; search: string };
    teams: SupportOption[];
    statuses: SupportOption[];
    stats: { new: number; open: number; today: number; resolved: number };
};

/**
 * The public and sales enquiries.
 *
 * This is the screen a sales or support colleague reads: one row per message from
 * the contact page, with the reason it was sent and the team that owns it. Urgent
 * rows sit at the top because the server orders the queue, not the table.
 */
export default function SupportEnquiries({
    enquiries,
    filters,
    teams,
    statuses,
    stats,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [team, setTeam] = useState(filters.team);
    const [status, setStatus] = useState(filters.status);

    const apply = (event?: FormEvent): void => {
        event?.preventDefault();

        router.get(
            index.url(),
            {
                search: search || null,
                team: team || null,
                status: status || null,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Contact enquiries" />

            <div className="grid gap-6">
                <PageHeader
                    icon={Inbox}
                    title="Contact enquiries"
                    description="Every message from the public contact form, routed to the team its category names."
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="New"
                        value={stats.new}
                        tone="warning"
                        description="Nobody has picked them up"
                    />
                    <StatCard
                        title="Open"
                        value={stats.open}
                        tone="signal"
                        description="Waiting on a reply"
                    />
                    <StatCard
                        title="Today"
                        value={stats.today}
                        tone="brand"
                        description="Received since midnight"
                    />
                    <StatCard
                        title="Settled"
                        value={stats.resolved}
                        tone="success"
                        description="Resolved or closed"
                    />
                </div>

                <Card className="gap-0 overflow-hidden py-0">
                    <form
                        onSubmit={apply}
                        className="flex flex-wrap items-center gap-2 border-b border-border/60 p-3"
                    >
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Reference, subject, name or email"
                            className="max-w-xs"
                        />

                        <Select
                            value={team || 'all'}
                            onValueChange={(value) =>
                                setTeam(value === 'all' ? '' : value)
                            }
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All teams" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All teams</SelectItem>
                                {teams.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={status || 'all'}
                            onValueChange={(value) =>
                                setStatus(value === 'all' ? '' : value)
                            }
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                {statuses.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Button type="submit" variant="outline">
                            <Search />
                            Filter
                        </Button>
                    </form>

                    <SupportTicketTable
                        rows={enquiries}
                        emptyMessage="No enquiry matches these filters."
                    />
                </Card>

                <Pagination paginator={enquiries} noun="enquiries" />
            </div>
        </>
    );
}
