import { Head, router } from '@inertiajs/react';
import { LifeBuoy, Search } from 'lucide-react';
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
import { index } from '@/routes/admin/support/tickets';
import type { Paginated, SupportOption, SupportTicketRow } from '@/types';

type PageProps = {
    tickets: Paginated<SupportTicketRow>;
    filters: {
        team: string;
        status: string;
        priority: string;
        assignee: string;
        search: string;
    };
    teams: SupportOption[];
    statuses: SupportOption[];
    priorities: SupportOption[];
    agents: SupportOption[];
    stats: {
        open: number;
        unassigned: number;
        urgent: number;
        resolved_30: number;
    };
};

/**
 * The client support queue.
 *
 * Rows are ordered by priority and age on the server, so the top row is the one to
 * answer next. The counters above answer the question a team lead actually asks:
 * how much is open, how much has nobody taken, and what is urgent.
 */
export default function SupportTickets({
    tickets,
    filters,
    teams,
    statuses,
    priorities,
    agents,
    stats,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [team, setTeam] = useState(filters.team);
    const [status, setStatus] = useState(filters.status);
    const [priority, setPriority] = useState(filters.priority);
    const [assignee, setAssignee] = useState(filters.assignee);

    const apply = (event?: FormEvent): void => {
        event?.preventDefault();

        router.get(
            index.url(),
            {
                search: search || null,
                team: team || null,
                status: status || null,
                priority: priority || null,
                assignee: assignee || null,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Support tickets" />

            <div className="grid gap-6">
                <PageHeader
                    icon={LifeBuoy}
                    title="Support tickets"
                    description="Client conversations with an owner, a priority and a status the client is watching."
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Open"
                        value={stats.open}
                        tone="warning"
                        description="Needs someone"
                    />
                    <StatCard
                        title="Unassigned"
                        value={stats.unassigned}
                        tone="danger"
                        description="Nobody owns them yet"
                    />
                    <StatCard
                        title="Urgent"
                        value={stats.urgent}
                        tone="signal"
                        description="Open and urgent"
                    />
                    <StatCard
                        title="Resolved (30d)"
                        value={stats.resolved_30}
                        tone="success"
                        description="Settled this month"
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
                            placeholder="Reference, subject, name, email"
                            className="max-w-xs"
                        />

                        <Select
                            value={team || 'all'}
                            onValueChange={(value) =>
                                setTeam(value === 'all' ? '' : value)
                            }
                        >
                            <SelectTrigger className="w-40">
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
                            <SelectTrigger className="w-40">
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

                        <Select
                            value={priority || 'all'}
                            onValueChange={(value) =>
                                setPriority(value === 'all' ? '' : value)
                            }
                        >
                            <SelectTrigger className="w-36">
                                <SelectValue placeholder="Any priority" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Any priority</SelectItem>
                                {priorities.map((option) => (
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
                            value={assignee || 'all'}
                            onValueChange={(value) =>
                                setAssignee(value === 'all' ? '' : value)
                            }
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Any owner" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Any owner</SelectItem>
                                <SelectItem value="unassigned">
                                    Unassigned
                                </SelectItem>
                                {agents.map((option) => (
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
                        rows={tickets}
                        showClient
                        showAssignee
                        emptyMessage="No ticket matches these filters."
                    />
                </Card>

                <Pagination paginator={tickets} noun="tickets" />
            </div>
        </>
    );
}
