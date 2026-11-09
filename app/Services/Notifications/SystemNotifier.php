<?php

namespace App\Services\Notifications;

use App\Enums\AnnouncementSeverity;
use App\Enums\NotificationCategory;
use App\Enums\Permission as PermissionEnum;
use App\Models\AutomationRun;
use App\Models\Campaign;
use App\Models\ClientRegistration;
use App\Models\OrganisationDpa;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns the platform's own events into inbox messages.
 *
 * Every method resolves the accounts that should hear about the event and hands
 * them to the dispatcher, which decides the channels. A notification that cannot
 * be delivered is logged rather than thrown: a campaign that finished must not
 * fail because the mail transport is down.
 */
class SystemNotifier
{
    public function __construct(protected NotificationDispatcher $notifications) {}

    /**
     * A campaign has finished delivering.
     */
    public function campaignCompleted(Campaign $campaign): void
    {
        $this->guard(function () use ($campaign): void {
            $this->notifications->sendMany(
                $this->tenantTeam($campaign->client_id),
                NotificationCategory::Campaigns,
                'Campaign completed',
                sprintf('%s finished delivering to its audience.', $campaign->name),
                [
                    'action_url' => route('campaigns.show', $campaign),
                    'action_label' => 'Open the campaign',
                    'meta' => ['campaign_id' => $campaign->id],
                    'severity' => AnnouncementSeverity::Success,
                ],
            );
        });
    }

    /**
     * A payment settled into the tenant's wallet.
     */
    public function paymentReceived(Payment $payment): void
    {
        $this->guard(function () use ($payment): void {
            $amount = (float) $payment->amount_paid > 0 ? (float) $payment->amount_paid : (float) $payment->amount;

            $this->notifications->sendMany(
                $this->tenantTeam($payment->client_id),
                NotificationCategory::Billing,
                'Payment received',
                sprintf(
                    '%s %s was added to your wallet. Reference %s.',
                    $payment->currency,
                    number_format($amount, 2),
                    $payment->reference,
                ),
                [
                    'action_url' => route('wallet.index'),
                    'action_label' => 'Open the wallet',
                    'meta' => ['payment_id' => $payment->id, 'reference' => $payment->reference],
                    'severity' => AnnouncementSeverity::Success,
                ],
            );
        });
    }

    /**
     * A webhook subscription was switched off after repeated failures.
     */
    public function webhookDisabled(WebhookEndpoint $endpoint): void
    {
        $this->guard(function () use ($endpoint): void {
            $platform = $endpoint->client_id === null;

            $this->notifications->sendMany(
                $platform
                    ? $this->platformTeam(PermissionEnum::ManageWebhooks)
                    : $this->tenantTeam($endpoint->client_id),
                NotificationCategory::System,
                'Webhook switched off',
                sprintf(
                    '%s stopped receiving events after %d consecutive failures.',
                    $endpoint->name,
                    $endpoint->consecutive_failures,
                ),
                [
                    'action_url' => $platform ? route('admin.webhooks.index') : route('webhooks.index'),
                    'action_label' => 'Review the subscription',
                    'meta' => ['webhook_endpoint_id' => $endpoint->id],
                    'severity' => AnnouncementSeverity::Warning,
                ],
            );
        });
    }

    /**
     * A workflow run stopped because a step failed.
     */
    public function automationFailed(AutomationRun $run, string $detail): void
    {
        $this->guard(function () use ($run, $detail): void {
            $name = $run->workflow?->name ?? 'An automation';

            $this->notifications->sendMany(
                $this->tenantTeam($run->client_id),
                NotificationCategory::Automations,
                'Automation run failed',
                sprintf('%s stopped at step %d: %s', $name, $run->current_position, $detail),
                [
                    'action_url' => route('automations.runs.show', $run),
                    'action_label' => 'Open the run',
                    'meta' => ['automation_run_id' => $run->id, 'automation_workflow_id' => $run->automation_workflow_id],
                    'severity' => AnnouncementSeverity::Warning,
                ],
            );
        });
    }

    /**
     * A client completed registration and is waiting for review.
     */
    public function registrationSubmitted(ClientRegistration $registration): void
    {
        $this->guard(function () use ($registration): void {
            $this->notifications->sendMany(
                $this->platformTeam(PermissionEnum::ApproveClients),
                NotificationCategory::Compliance,
                'New client application',
                sprintf(
                    '%s applied for an account and is waiting for review.',
                    $registration->company_name ?? $registration->contact_name ?? 'A new client',
                ),
                [
                    'action_url' => route('admin.client-registrations.index'),
                    'action_label' => 'Open the review queue',
                    'meta' => ['client_registration_id' => $registration->id],
                    'severity' => AnnouncementSeverity::Warning,
                ],
            );
        });
    }

    /**
     * A client asked for a countersigned data processing agreement.
     */
    public function dpaRequested(OrganisationDpa $dpa): void
    {
        $this->guard(function () use ($dpa): void {
            $this->notifications->sendMany(
                $this->platformTeam(PermissionEnum::ManageClients),
                NotificationCategory::Compliance,
                'Data processing agreement requested',
                sprintf(
                    '%s asked for a data processing agreement. Record the signature once the countersigned copy comes back.',
                    $dpa->client?->name ?? 'A client account',
                ),
                [
                    'action_url' => route('admin.legal-dpas.index'),
                    'action_label' => 'Open DPA management',
                    'meta' => ['client_id' => $dpa->client_id, 'organisation_dpa_id' => $dpa->id],
                    'severity' => AnnouncementSeverity::Warning,
                ],
            );
        });
    }

    /**
     * Every active account of one tenant.
     *
     * @return Collection<int, User>
     */
    protected function tenantTeam(?int $clientId): Collection
    {
        if ($clientId === null) {
            return collect();
        }

        return User::query()
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->whereNull('suspended_at')
            ->get();
    }

    /**
     * Every active platform account holding one permission.
     *
     * @return Collection<int, User>
     */
    protected function platformTeam(PermissionEnum $permission): Collection
    {
        return User::query()
            ->permission($permission->value)
            ->where('is_active', true)
            ->whereNull('suspended_at')
            ->get();
    }

    /**
     * Run the fan-out without letting a delivery problem escape.
     */
    protected function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning('A system notification could not be delivered.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
