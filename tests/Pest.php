<?php

use App\Enums\Channel;
use App\Enums\LegalDocumentType;
use App\Enums\Role as RoleEnum;
use App\Models\Client;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\Rate;
use App\Models\RateCard;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->seed(RoleSeeder::class);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a client tenant with a funded wallet, an SMS rate and a signed-in capable user.
 *
 * @return array{0: Client, 1: Wallet, 2: User}
 */
function clientWithWallet(
    float $balance = 100.0,
    float $smsPrice = 0.05,
    RoleEnum $role = RoleEnum::ClientAdmin,
): array {
    $client = Client::factory()->create();
    $wallet = Wallet::factory()->for($client)->create(['balance' => $balance]);

    $rateCard = RateCard::factory()->for($client)->create(['channel' => Channel::Sms]);

    Rate::factory()->for($rateCard, 'rateCard')->create([
        'channel' => Channel::Sms,
        'unit_price' => $smsPrice,
    ]);

    $user = User::factory()->forClient($client)->create();
    $user->syncRoles([$role->value]);

    return [$client, $wallet, $user];
}

/**
 * Publish one of the platform's policy documents with a live version.
 */
function legalDocument(
    LegalDocumentType $type,
    string $version = '1.0',
    array $overrides = [],
): LegalDocument {
    $document = LegalDocument::factory()->type($type)->create($overrides);

    LegalDocumentVersion::factory()->for($document, 'document')->published()->create([
        'version' => $version,
        'body' => "# {$type->label()}\n\n## 1. A clause\n\nThis policy is binding.\n\n- One bullet\n- Another bullet",
    ]);

    return $document->fresh();
}

/**
 * Publish all five documents the platform ships with.
 */
function publishLegalDocuments(): void
{
    foreach (LegalDocumentType::cases() as $type) {
        legalDocument($type);
    }
}

/**
 * Create a platform team member holding the given role.
 */
function platformUser(RoleEnum $role = RoleEnum::SuperAdmin): User
{
    $user = User::factory()->create(['client_id' => null]);
    $user->syncRoles([$role->value]);

    return $user;
}
