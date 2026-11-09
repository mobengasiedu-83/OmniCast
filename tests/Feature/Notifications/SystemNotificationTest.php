<?php

use App\Enums\CampaignStatus;
use App\Enums\NotificationCategory;
use App\Models\AutomationRun;
use App\Models\Campaign;
use App\Models\ClientRegistration;
use App\Models\Payment;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Notifications\SystemNotifier;
use App\Services\Webhooks\WebhookSender;

beforeEach(function (): void {
    $this->withoutVite();

    [$this->client, $this->wallet, $this->user] = clientWithWallet();
    $this->admin = platformUser();
});

it('notifies the tenant team when a campaign completes', function (): void {
    $campaign = Campaign::factory()->for($this->client)->create([
        'name' => 'Ramadan promo',
        'status' => CampaignStatus::Completed,
    ]);

    app(SystemNotifier::class)->campaignCompleted($campaign);

    $notification = $this->user->notifications()->sole();

    expect($notification->data['category'])->toBe(NotificationCategory::Campaigns->value)
        ->and($notification->data['title'])->toBe('Campaign completed')
        ->and($notification->data['body'])->toContain('Ramadan promo')
        ->and($notification->data['action_url'])->toBe(route('campaigns.show', $campaign));
});

it('notifies the tenant when a payment settles', function (): void {
    $payment = Payment::factory()->for($this->client)->paid()->create([
        'reference' => 'OC-PAY-123456',
        'amount' => 250.0,
        'amount_paid' => 250.0,
    ]);

    app(SystemNotifier::class)->paymentReceived($payment);

    $notification = $this->user->notifications()->sole();

    expect($notification->data['category'])->toBe(NotificationCategory::Billing->value)
        ->and($notification->data['title'])->toBe('Payment received')
        ->and($notification->data['body'])->toContain('OC-PAY-123456')
        ->and($notification->data['body'])->toContain('250.00')
        ->and($notification->data['action_url'])->toBe(route('wallet.index'));
});

it('notifies the tenant when a webhook is switched off after failures', function (): void {
    config()->set('webhooks.delivery.disable_after_failures', 1);

    $endpoint = WebhookEndpoint::factory()->for($this->client)->create([
        'name' => 'CRM hook',
    ]);

    $delivery = WebhookDelivery::factory()->create([
        'webhook_endpoint_id' => $endpoint->id,
        'client_id' => $this->client->id,
    ]);

    app(WebhookSender::class)->markFinalFailure($delivery);

    expect($endpoint->refresh()->is_active)->toBeFalse();

    $notification = $this->user->notifications()->sole();

    expect($notification->data['category'])->toBe(NotificationCategory::System->value)
        ->and($notification->data['title'])->toBe('Webhook switched off')
        ->and($notification->data['body'])->toContain('CRM hook')
        ->and($notification->data['action_url'])->toBe(route('webhooks.index'));
});

it('notifies the tenant when an automation run fails', function (): void {
    $run = AutomationRun::factory()->for($this->client)->create([
        'detail' => 'The step failed.',
    ]);

    app(SystemNotifier::class)->automationFailed($run, 'The step failed.');

    $notification = $this->user->notifications()->sole();

    expect($notification->data['category'])->toBe(NotificationCategory::Automations->value)
        ->and($notification->data['title'])->toBe('Automation run failed')
        ->and($notification->data['body'])->toContain('The step failed.')
        ->and($notification->data['action_url'])->toBe(route('automations.runs.show', $run));
});

it('notifies the reviewers when a client applies', function (): void {
    $registration = ClientRegistration::factory()->create([
        'company_name' => 'Acme Limited',
    ]);

    app(SystemNotifier::class)->registrationSubmitted($registration);

    $notification = $this->admin->notifications()->sole();

    expect($notification->data['category'])->toBe(NotificationCategory::Compliance->value)
        ->and($notification->data['title'])->toBe('New client application')
        ->and($notification->data['body'])->toContain('Acme Limited')
        ->and($notification->data['action_url'])->toBe(route('admin.client-registrations.index'))
        ->and($this->user->notifications()->count())->toBe(0);
});
