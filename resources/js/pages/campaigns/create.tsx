import { Head } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';
import CampaignForm from '@/components/campaign-form';
import PageHeader from '@/components/page-header';
import { urls } from '@/lib/urls';
import type { CampaignFormOptions, CampaignFormValues } from '@/types';

type PageProps = CampaignFormOptions & {
    campaign: null;
    /**
     * The template the editor was opened from, when the operator pressed "Use
     * template" in the library.
     */
    initial?: Partial<CampaignFormValues>;
};

export default function CampaignsCreate({ initial, ...options }: PageProps) {
    return (
        <>
            <Head title="New campaign" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Megaphone}
                    title="New campaign"
                    description="Draft a campaign, review the estimated cost, then submit it for approval."
                />

                <CampaignForm
                    action={urls.campaigns.store}
                    method="post"
                    options={options}
                    initial={{
                        client_id:
                            options.tenantClientId === null
                                ? ''
                                : String(options.tenantClientId),
                        ...initial,
                    }}
                    submitLabel="Save draft"
                    cancelHref={urls.campaigns.index}
                />
            </div>
        </>
    );
}

CampaignsCreate.layout = {
    breadcrumbs: [
        {
            title: 'Campaigns',
            href: urls.campaigns.index,
        },
        {
            title: 'New campaign',
            href: urls.campaigns.create,
        },
    ],
};
