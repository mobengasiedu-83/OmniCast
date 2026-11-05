<?php

use App\Enums\PaymentStatus;
use App\Enums\Role as RoleEnum;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rate;
use App\Models\RateCard;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('billing.driver', 'log');

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 10.0);
});

it('shows a tenant its own wallet even when another client is asked for', function (): void {
    [$otherClient] = clientWithWallet(balance: 999.0);

    $this->actingAs($this->user)
        ->get(route('wallet.index', ['client_id' => $otherClient->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('wallet/index')
            ->where('client.id', $this->client->id)
            ->where('wallet.balance', 10),
        );
});

it('shows the published package price list', function (): void {
    $this->actingAs($this->user)
        ->get(route('wallet.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('wallet/index')
            ->has('packages', 4)
            ->where('packages', fn (Collection $packages): bool => $packages
                ->mapWithKeys(fn (array $package): array => [
                    $package['key'] => [
                        $package['price'],
                        ...array_column($package['units'], 'quantity'),
                    ],
                ])
                ->all() === [
                    'starter' => [51.25, 1300, 136, 1025],
                    'growth' => [243.75, 6050, 650, 4875],
                    'business' => [2153.75, 55000, 5743, 43075],
                    'enterprise' => [3990, 100000, 10640, 79800],
                ])
            ->where('packages.0.units.1.label', 'Voice minutes')
            ->where('packages.0.units.1.formatted', '136'),
        );
});

it('refuses a manual wallet credit from a tenant user', function (): void {
    $this->actingAs($this->user)
        ->post(route('wallet.top-up'), ['amount' => 100])
        ->assertForbidden();

    expect((float) $this->wallet->refresh()->balance)->toBe(10.0)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('lets the platform team credit a wallet directly', function (): void {
    $this->actingAs(platformUser())
        ->post(route('wallet.top-up'), [
            'client_id' => $this->client->id,
            'amount' => 100,
            'description' => 'Bank transfer received',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast');

    expect((float) $this->wallet->refresh()->balance)->toBe(110.0);

    $transaction = WalletTransaction::query()->sole();

    expect((float) $transaction->amount)->toBe(100.0)
        ->and($transaction->description)->toBe('Bank transfer received');
});

it('refuses a manual credit from a platform user who may only reconcile', function (): void {
    $this->actingAs(platformUser(RoleEnum::FinanceOfficer))
        ->post(route('wallet.top-up'), [
            'client_id' => $this->client->id,
            'amount' => 100,
        ])
        ->assertForbidden();

    expect((float) $this->wallet->refresh()->balance)->toBe(10.0)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('keeps a tenant out of the platform payment register', function (): void {
    $this->actingAs($this->user)
        ->get(route('admin.payments.index'))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('admin.invoices.index'))
        ->assertForbidden();
});

it('shows platform finance every payment and verifies an open top up', function (): void {
    $payment = Payment::factory()->for($this->client)->create([
        'wallet_id' => $this->wallet->id,
        'provider' => 'log',
    ]);

    $finance = platformUser(RoleEnum::FinanceOfficer);

    $this->actingAs($finance)
        ->get(route('admin.payments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/billing/payments')
            ->where('can.verify', true)
            ->where('stats.pending', 1)
            ->has('payments.data', 1)
            ->where('payments.data.0.reference', $payment->reference)
            ->where('payments.data.0.is_open', true),
        );

    $this->actingAs($finance)
        ->post(route('admin.payments.verify', $payment))
        ->assertSessionHas('toast');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and((float) $this->wallet->refresh()->balance)->toBe(round(10.0 + (float) $payment->amount, 2))
        ->and(WalletTransaction::query()->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(1);
});

it('keeps a compliance officer out of the money screens', function (): void {
    $compliance = platformUser(RoleEnum::ComplianceOfficer);
    $payment = Payment::factory()->for($this->client)->create(['provider' => 'log']);

    $this->actingAs($compliance)
        ->get(route('admin.payments.index'))
        ->assertForbidden();

    $this->actingAs($compliance)
        ->post(route('admin.payments.verify', $payment))
        ->assertForbidden();

    $this->actingAs($compliance)
        ->get(route('admin.invoices.index'))
        ->assertForbidden();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('lists the invoices with the amounts billed and voided', function (): void {
    Invoice::factory()->for($this->client)->create(['total' => 51.25, 'subtotal' => 51.25]);
    Invoice::factory()->for($this->client)->voided()->create(['total' => 25.0, 'subtotal' => 25.0]);

    $this->actingAs(platformUser(RoleEnum::FinanceOfficer))
        ->get(route('admin.invoices.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/billing/invoices')
            ->where('stats.total', 2)
            ->where('stats.paid', 1)
            ->where('stats.void', 1)
            ->where('stats.billed', 51.25)
            ->has('invoices.data', 2)
            ->has('statuses'),
        );
});

it('lists the rate cards with the prices on them', function (): void {
    $this->actingAs(platformUser(RoleEnum::FinanceOfficer))
        ->get(route('admin.rate-cards.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/billing/rate-cards')
            ->where('can.manage', true)
            ->where('stats.cards', 1)
            ->where('stats.rates', 1)
            ->where('rateCards.0.client', $this->client->name)
            ->where('rateCards.0.channel_label', 'SMS')
            ->has('rateCards.0.rates', 1)
            ->has('channels'),
        );
});

it('lets platform finance reprice a destination', function (): void {
    $rate = Rate::query()
        ->whereIn('rate_card_id', RateCard::query()->where('client_id', $this->client->id)->select('id'))
        ->sole();

    $this->actingAs(platformUser(RoleEnum::FinanceOfficer))
        ->put(route('admin.rates.update', $rate), ['unit_price' => 0.0425, 'supplier_cost' => 0.0310])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast');

    expect((float) $rate->refresh()->unit_price)->toBe(0.0425)
        ->and((float) $rate->supplier_cost)->toBe(0.031);
});

it('refuses a reprice from a platform user without the rates permission', function (): void {
    $rate = Rate::query()
        ->whereIn('rate_card_id', RateCard::query()->where('client_id', $this->client->id)->select('id'))
        ->sole();

    $this->actingAs(platformUser(RoleEnum::ComplianceOfficer))
        ->put(route('admin.rates.update', $rate), ['unit_price' => 0.0425, 'supplier_cost' => 0.031])
        ->assertForbidden();

    expect((float) $rate->refresh()->unit_price)->toBe(0.05);
});
