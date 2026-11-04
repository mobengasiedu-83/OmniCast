import { Head } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';
import CampaignForm from '@/components/campaign-form';
import PageHeader from '@/components/page-header';
import { urls } from '@/lib/urls';
import type { CampaignFormOptions, CampaignFormValues } from '@/types';

type EditableCampaign = {
    id: number;
    reference: string;
    name: string;
    channel: string;
    contact_list_id: number | null;
    contact_segment_id: number | null;
    sender_id: number | null;
    caller_id: number | null;
    sms_message: string | null;
    voice_script: string | null;
    voice_recording_path: string | null;
    voice_max_duration_seconds: number | null;
    ivr_enabled: boolean;
    ivr_prompt: string | null;
    ivr_timeout_seconds: number;
    ivr_max_attempts: number;
    ivr_options: Record<string, string>;
    schedule_type: string | null;
    scheduled_at: string | null;
    timezone: string | null;
    max_retries: number | null;
    retry_delay_minutes: number | null;
    notes: string | null;
};

type PageProps = CampaignFormOptions & {
    campaign: EditableCampaign;
};

/**
 * Turns the persisted campaign into form strings, keeping server defaults for
 * anything that is still null.
 */
function initialValues(campaign: EditableCampaign): Partial<CampaignFormValues> {
    const text = (value: string | number | null): string =>
        value === null ? '' : String(value);

    return {
        name: campaign.name,
        channel: campaign.channel,
        contact_list_id: text(campaign.contact_list_id),
        contact_segment_id: text(campaign.contact_segment_id),
        sender_id: text(campaign.sender_id),
        caller_id: text(campaign.caller_id),
        sms_message: text(campaign.sms_message),
        voice_script: text(campaign.voice_script),
        voice_max_duration_seconds: text(
            campaign.voice_max_duration_seconds,
        ),
        ivr_enabled: campaign.ivr_enabled ? '1' : '0',
        ivr_prompt: text(campaign.ivr_prompt),
        ivr_timeout_seconds: text(campaign.ivr_timeout_seconds),
        ivr_max_attempts: text(campaign.ivr_max_attempts),
        ivr_options: campaign.ivr_options ?? {},
        schedule_type: campaign.schedule_type ?? 'now',
        scheduled_at: text(campaign.scheduled_at),
        timezone: text(campaign.timezone),
        max_retries: text(campaign.max_retries),
        retry_delay_minutes: text(campaign.retry_delay_minutes),
        notes: text(campaign.notes),
    };
}

export default function CampaignsEdit({ campaign, ...options }: PageProps) {
    return (
        <>
            <Head title={`Edit ${campaign.name}`} />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Megaphone}
                    title="Edit campaign"
                    description={`${campaign.reference} · saving re-prices the campaign and its hold on the wallet.`}
                />

                <CampaignForm
                action={urls.campaigns.update(campaign.id)}
                method="put"
                options={options}
                initial={initialValues(campaign)}
                submitLabel="Save changes"
                cancelHref={urls.campaigns.show(campaign.id)}
                />
            </div>
        </>
    );
}

CampaignsEdit.layout = {
    breadcrumbs: [
        {
            title: 'Campaigns',
            href: urls.campaigns.index,
        },
        {
            title: 'Edit campaign',
            href: urls.campaigns.index,
        },
    ],
};
