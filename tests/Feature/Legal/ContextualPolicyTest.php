<?php

use App\Enums\LegalAcceptanceContext;
use App\Enums\LegalDocumentType;
use App\Exceptions\PaymentGatewayException;
use App\Models\Campaign;
use App\Models\LegalAcceptance;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\CreditEstimator;
use App\Services\Billing\PackageCatalogue;
use App\Services\CampaignService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

it('prints the refund policy above the wallet top up', function (): void {
    [, , $user] = clientWithWallet();
    legalDocument(LegalDocumentType::RefundWallet, '1.3');

    $this->actingAs($user)
        ->get(route('wallet.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('wallet/index')
            ->where('refund_policy.slug', 'refund-wallet')
            ->where('refund_policy.title', 'Refund, Cancellation & Wallet Policy')
            ->where('refund_policy.version', '1.3')
            ->where('refund_policy.url', route('legal.show', 'refund-wallet')));
});

it('prints the acceptable use policy above the submit button', function (): void {
    [$client, , $user] = clientWithWallet();
    $campaign = Campaign::factory()->forClient($client)->create();
    legalDocument(LegalDocumentType::AcceptableUse);

    $this->actingAs($user)
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('acceptableUse.slug', 'acceptable-use')
            ->where('acceptableUse.url', route('legal.show', 'acceptable-use')));
});

it('records the acceptable use acknowledgement when a campaign is submitted', function (): void {
    [$client, , $user] = clientWithWallet();
    $campaign = Campaign::factory()->forClient($client)->create();
    legalDocument(LegalDocumentType::AcceptableUse);

    $this->mock(CampaignService::class, function ($mock) use ($campaign): void {
        $mock->shouldReceive('submit')->once()->andReturn($campaign);
    });

    $this->actingAs($user)
        ->post(route('campaigns.submit', $campaign))
        ->assertRedirect();

    $acceptance = LegalAcceptance::query()->where('user_id', $user->id)->sole();

    expect($acceptance->context)->toBe(LegalAcceptanceContext::CampaignSubmission)
        ->and($acceptance->client_id)->toBe($client->id);
});

it('records the refund policy acknowledgement before the gateway is called', function (): void {
    [, , $user] = clientWithWallet();
    legalDocument(LegalDocumentType::RefundWallet);

    $estimator = app(CreditEstimator::class);

    $payload = $estimator->dynamicPricingEnabled()
        ? ['amount' => $estimator->limits()['min']]
        : ['package' => app(PackageCatalogue::class)->keys()->first()];

    $this->mock(CheckoutService::class, function ($mock): void {
        $mock->shouldReceive('start')->once()->andThrow(new PaymentGatewayException('Gateway offline.'));
    });

    $this->actingAs($user)
        ->post(route('wallet.checkout.store'), $payload)
        ->assertRedirect();

    $acceptance = LegalAcceptance::query()->where('user_id', $user->id)->sole();

    expect($acceptance->context)->toBe(LegalAcceptanceContext::WalletTopUp);
});
