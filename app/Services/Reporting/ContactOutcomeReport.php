<?php

namespace App\Services\Reporting;

use App\Enums\CampaignContactStatus;
use App\Models\CampaignContact;
use App\Models\Contact;
use App\Models\ContactSuppression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * How the audience answered the campaigns that reached it.
 *
 * The screen answers the two questions a client asks about its list: how many
 * contacts can still be reached, and which recipients keep failing.
 */
class ContactOutcomeReport
{
    /**
     * @return array<string, mixed>
     */
    public function for(ReportScope $scope, ReportPeriod $period): array
    {
        $contacts = $scope->contacts();
        $outcomes = $scope->campaignContacts()
            ->whereBetween('campaign_contacts.created_at', [$period->from, $period->to]);

        $statusTotals = (clone $outcomes)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $optOuts = (clone $contacts)
            ->whereNotNull('dnd_at')
            ->whereBetween('dnd_at', [$period->from, $period->to]);

        return [
            'totals' => [
                'contacts' => (clone $contacts)->count(),
                'reachable' => (clone $contacts)->where('dnd', false)->count(),
                'dnd' => (clone $contacts)->where('dnd', true)->count(),
                'suppressed' => $this->suppressed($scope),
                'reached' => (clone $outcomes)->distinct()->count('campaign_contacts.contact_id'),
                'delivered' => $this->count($statusTotals, CampaignContactStatus::Delivered)
                    + $this->count($statusTotals, CampaignContactStatus::Sent),
                'failed' => $this->count($statusTotals, CampaignContactStatus::Failed),
                'skipped' => $this->count($statusTotals, CampaignContactStatus::Skipped),
                'pending' => $this->count($statusTotals, CampaignContactStatus::Pending)
                    + $this->count($statusTotals, CampaignContactStatus::Queued),
                'opt_outs' => (clone $optOuts)->count(),
            ],
            'byStatus' => $this->byStatus($statusTotals),
            'topContacts' => $this->topContacts($outcomes),
            'recentOptOuts' => (clone $optOuts)
                ->latest('dnd_at')
                ->limit(10)
                ->get(['id', 'first_name', 'last_name', 'phone', 'email', 'dnd_at'])
                ->map(fn (Contact $contact): array => [
                    'id' => $contact->id,
                    'name' => $contact->full_name,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'dnd_at' => $contact->dnd_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The number of live channel suppressions on the tenant's list.
     */
    protected function suppressed(ReportScope $scope): int
    {
        return ContactSuppression::query()
            ->when(
                $scope->clientId !== null,
                fn (Builder $query): Builder => $query->where('client_id', $scope->clientId),
            )
            ->where('is_active', true)
            ->count();
    }

    /**
     * The recipient outcome split, in enum order.
     *
     * @param  Collection<string, int|string>  $totals
     * @return list<array{status: string, label: string, total: int}>
     */
    protected function byStatus(Collection $totals): array
    {
        return collect(CampaignContactStatus::cases())
            ->map(fn (CampaignContactStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'total' => (int) $totals->get($status->value, 0),
            ])
            ->values()
            ->all();
    }

    /**
     * The recipients with the most failures inside the window.
     *
     * @param  Builder<CampaignContact>  $outcomes
     * @return list<array<string, mixed>>
     */
    protected function topContacts(Builder $outcomes): array
    {
        return (clone $outcomes)
            ->whereNotNull('campaign_contacts.contact_id')
            ->selectRaw(
                'campaign_contacts.contact_id as contact_id, count(*) as recipients, '
                .'sum(case when status = ? then 1 else 0 end) as failed',
                [CampaignContactStatus::Failed->value],
            )
            ->groupBy('campaign_contacts.contact_id')
            ->orderByDesc('failed')
            ->orderByDesc('recipients')
            ->limit(10)
            ->with('contact:id,first_name,last_name,phone,email')
            ->get()
            ->map(fn (CampaignContact $row): array => [
                'contact_id' => $row->contact_id,
                'name' => $row->contact?->full_name ?? 'Unknown recipient',
                'phone' => $row->contact?->phone,
                'email' => $row->contact?->email,
                'recipients' => (int) $row->getAttribute('recipients'),
                'failed' => (int) $row->getAttribute('failed'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, int|string>  $totals
     */
    protected function count(Collection $totals, CampaignContactStatus $status): int
    {
        return (int) $totals->get($status->value, 0);
    }
}
