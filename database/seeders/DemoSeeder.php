<?php

namespace Database\Seeders;

use App\Enums\BillingUnit;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\Role;
use App\Enums\SenderIdStatus;
use App\Enums\SmsEncoding;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\PlatformSetting;
use App\Models\Rate;
use App\Models\RateCard;
use App\Models\SenderId;
use App\Models\Tag;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Seed a demo client tenant with staff, contacts, sender IDs and campaigns.
     */
    public function run(): void
    {
        $client = Client::query()->firstOrCreate(
            ['code' => 'KUDIGO'],
            [
                'name' => 'Kudigo Retail',
                'email' => 'ops@kudigo.test',
                'phone' => '+233201234567',
                'timezone' => 'Africa/Accra',
                'credit_limit' => 10000,
            ],
        );

        Wallet::query()->firstOrCreate(
            ['client_id' => $client->id],
            ['currency' => 'GHS', 'balance' => 2500, 'reserved_balance' => 0, 'low_balance_threshold' => 200],
        );

        $rateCard = RateCard::query()->firstOrCreate(
            ['client_id' => $client->id, 'name' => 'Kudigo 2026 Rates'],
            [
                'channel' => Channel::Sms,
                'currency' => 'GHS',
                'is_default' => true,
                'effective_from' => now()->startOfYear(),
            ],
        );

        $this->seedRates($rateCard);

        PlatformSetting::query()->firstOrCreate(
            ['group' => 'voice', 'key' => 'outbound_caller_id'],
            [
                'value' => '+233200000000',
                'is_secret' => false,
            ],
        );

        $this->seedUsers($client);

        $senderId = SenderId::query()->firstOrCreate(
            ['client_id' => $client->id, 'name' => 'KUDIGO'],
            [
                'country' => 'GH',
                'purpose' => 'Transactional and promotional customer notifications',
                'status' => SenderIdStatus::Approved,
                'requested_at' => now()->subWeeks(3),
                'submitted_at' => now()->subWeeks(2),
                'approved_at' => now()->subWeek(),
            ],
        );

        $this->seedContacts($client);
        $this->seedCampaigns($client, $senderId);
    }

    /**
     * Seed the per-channel rates for the demo rate card.
     */
    protected function seedRates(RateCard $rateCard): void
    {
        Rate::query()->firstOrCreate(
            ['rate_card_id' => $rateCard->id, 'channel' => Channel::Sms, 'destination' => 'GH'],
            [
                'destination_label' => 'Ghana',
                'billing_unit' => BillingUnit::PerSmsPart,
                'unit_price' => 0.0350,
                'supplier_cost' => 0.0250,
            ],
        );

        Rate::query()->firstOrCreate(
            ['rate_card_id' => $rateCard->id, 'channel' => Channel::Voice, 'destination' => 'GH'],
            [
                'destination_label' => 'Ghana',
                'billing_unit' => BillingUnit::PerMinute,
                'unit_price' => 0.1800,
                'supplier_cost' => 0.1200,
            ],
        );
    }

    /**
     * Seed the platform staff and the demo client's team.
     */
    protected function seedUsers(Client $client): void
    {
        $people = [
            ['name' => 'Ama Owusu', 'email' => 'admin@omnicast.test', 'role' => Role::SuperAdmin, 'job_title' => 'Platform Owner', 'client' => null],
            ['name' => 'Kofi Mensah', 'email' => 'compliance@omnicast.test', 'role' => Role::ComplianceOfficer, 'job_title' => 'Compliance Officer', 'client' => null],
            ['name' => 'Efua Boateng', 'email' => 'finance@omnicast.test', 'role' => Role::FinanceOfficer, 'job_title' => 'Finance Officer', 'client' => null],
            ['name' => 'Yaw Asante', 'email' => 'admin@kudigo.test', 'role' => Role::ClientAdmin, 'job_title' => 'Head of Marketing', 'client' => $client],
            ['name' => 'Adwoa Sarpong', 'email' => 'ops@kudigo.test', 'role' => Role::ClientOperator, 'job_title' => 'Campaign Operator', 'client' => $client],
        ];

        foreach ($people as $person) {
            $user = User::query()->firstOrCreate(
                ['email' => $person['email']],
                [
                    'name' => $person['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'client_id' => $person['client']?->id,
                    'job_title' => $person['job_title'],
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$person['role']->value]);
        }
    }

    /**
     * Seed contacts, tags and contact lists for the demo client.
     */
    protected function seedContacts(Client $client): void
    {
        if ($client->contacts()->exists()) {
            return;
        }

        $tag = Tag::query()->firstOrCreate(
            ['client_id' => $client->id, 'name' => 'Loyalty'],
            ['color' => 'emerald'],
        );

        $contacts = Contact::factory()->count(40)->forClient($client)->create();

        $contacts->take(10)->each(fn (Contact $contact) => $contact->tags()->syncWithoutDetaching([$tag->id]));

        $contacts->take(5)->each(fn (Contact $contact) => $contact->update([
            'dnd' => true,
            'dnd_at' => now()->subDays(3),
        ]));

        $all = ContactList::query()->firstOrCreate(
            ['client_id' => $client->id, 'name' => 'All Customers'],
            ['description' => 'Every customer captured in the CRM export'],
        );

        $all->contacts()->syncWithoutDetaching($contacts->pluck('id')->all());

        $vip = ContactList::query()->firstOrCreate(
            ['client_id' => $client->id, 'name' => 'VIP Customers'],
            ['description' => 'Top spenders invited to the loyalty launch'],
        );

        $vip->contacts()->syncWithoutDetaching($contacts->take(15)->pluck('id')->all());
    }

    /**
     * Seed a few campaigns in different stages of the workflow.
     */
    protected function seedCampaigns(Client $client, SenderId $senderId): void
    {
        if ($client->campaigns()->exists()) {
            return;
        }

        $list = $client->contactLists()->where('name', 'All Customers')->first();
        $admin = User::query()->where('email', 'admin@kudigo.test')->first();

        Campaign::factory()->forClient($client)->create([
            'name' => 'December Stock Clearance',
            'status' => CampaignStatus::Draft,
            'channel' => Channel::Sms,
            'contact_list_id' => $list?->id,
            'sender_id' => $senderId->id,
            'sms_message' => 'Kudigo: our December clearance starts Friday. Show this message for 20% off any item in store.',
            'sms_encoding' => SmsEncoding::Gsm7,
            'estimated_recipients' => 35,
            'estimated_sms_parts' => 1,
            'estimated_cost' => 1.23,
            'created_by' => $admin?->id,
        ]);

        Campaign::factory()->forClient($client)->pendingApproval()->create([
            'name' => 'Loyalty Launch Reminder',
            'channel' => Channel::Sms,
            'contact_list_id' => $list?->id,
            'sender_id' => $senderId->id,
            'sms_message' => 'You are invited to the Kudigo loyalty launch this Saturday at 10am. Reply STOP to opt out.',
            'sms_encoding' => SmsEncoding::Gsm7,
            'estimated_recipients' => 35,
            'estimated_sms_parts' => 1,
            'estimated_cost' => 1.23,
            'submitted_at' => now()->subHours(6),
            'created_by' => $admin?->id,
        ]);

        Campaign::factory()->forClient($client)->approved()->create([
            'name' => 'Account Balance Nudge',
            'channel' => Channel::Voice,
            'contact_list_id' => $list?->id,
            'sender_id' => $senderId->id,
            'estimated_recipients' => 35,
            'estimated_cost' => 18.90,
            'created_by' => $admin?->id,
        ]);
    }
}
