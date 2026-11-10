import { Head } from '@inertiajs/react';
import { Activity, Building2, Megaphone, ShieldCheck } from 'lucide-react';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';

type PageProps = {
    stats: {
        clients: number;
        pending_clients: number;
        campaigns: number;
        pending_campaigns: number;
    };
};

export default function AdminDashboard({ stats }: PageProps) {
    const overview = [
        {
            title: 'Client accounts',
            value: stats.clients,
            description: 'Across the platform',
            icon: Building2,
            tone: 'brand' as const,
        },
        {
            title: 'Pending clients',
            value: stats.pending_clients,
            description: 'Awaiting activation',
            icon: ShieldCheck,
            tone: 'warning' as const,
        },
        {
            title: 'Campaigns',
            value: stats.campaigns,
            description: 'Across all workspaces',
            icon: Megaphone,
            tone: 'signal' as const,
        },
        {
            title: 'Pending approvals',
            value: stats.pending_campaigns,
            description: 'Campaigns awaiting review',
            icon: Activity,
            tone: 'warning' as const,
        },
    ];

    return (
        <>
            <Head title="Platform dashboard" />

            <div className="flex flex-col gap-5">
                <PageHeader
                    icon={Activity}
                    title="Platform overview"
                    description="Client onboarding and campaign activity across OmniCast."
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {overview.map((item) => (
                        <StatCard key={item.title} {...item} />
                    ))}
                </div>
            </div>
        </>
    );
}
