<?php

namespace Database\Seeders;

use App\Enums\SupportCategoryAudience;
use App\Enums\SupportPriority;
use App\Models\SupportArticle;
use App\Models\SupportCategory;
use App\Models\SupportTeam;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The support desk a fresh install opens with.
 *
 * Five teams and the categories that route to them, so the public contact form
 * and the client ticket form are usable before an operator configures anything.
 * The seeder is safe to run again: a team, category or article is created only
 * when its slug is missing, so an operator's later rewording survives.
 */
class SupportDeskSeeder extends Seeder
{
    /**
     * The teams conversations are routed to.
     *
     * @var list<array{slug: string, name: string, description: string}>
     */
    private const TEAMS = [
        ['slug' => 'support-desk', 'name' => 'Support desk', 'description' => 'Campaigns, delivery, billing and account access.'],
        ['slug' => 'sales', 'name' => 'Sales', 'description' => 'Pricing, packages, enterprise requirements and bulk messaging.'],
        ['slug' => 'technical', 'name' => 'Technical', 'description' => 'APIs, integrations, webhooks and campaign configuration.'],
        ['slug' => 'finance', 'name' => 'Finance', 'description' => 'Wallet funding, invoices, refunds and payment reconciliation.'],
        ['slug' => 'compliance', 'name' => 'Compliance', 'description' => 'Unwanted messages, acceptable use and data protection.'],
    ];

    /**
     * The categories both forms offer.
     *
     * @var list<array{slug: string, name: string, team: string, priority: SupportPriority, description: string}>
     */
    private const CATEGORIES = [
        ['slug' => 'customer-support', 'name' => 'Customer support', 'team' => 'support-desk', 'priority' => SupportPriority::Normal, 'description' => 'Help with campaigns, delivery, billing or an account.'],
        ['slug' => 'sales', 'name' => 'Talk to sales', 'team' => 'sales', 'priority' => SupportPriority::Normal, 'description' => 'Pricing, enterprise requirements and bulk messaging.'],
        ['slug' => 'technical-assistance', 'name' => 'Technical assistance', 'team' => 'technical', 'priority' => SupportPriority::High, 'description' => 'APIs, integrations and campaign configuration.'],
        ['slug' => 'compliance-privacy', 'name' => 'Compliance & privacy', 'team' => 'compliance', 'priority' => SupportPriority::High, 'description' => 'Unwanted messages, privacy requests and acceptable use.'],
        ['slug' => 'billing-wallet', 'name' => 'Billing & wallet', 'team' => 'finance', 'priority' => SupportPriority::Normal, 'description' => 'Top-ups, invoices, refunds and wallet movements.'],
        ['slug' => 'campaign-delivery', 'name' => 'Campaign delivery', 'team' => 'support-desk', 'priority' => SupportPriority::High, 'description' => 'A campaign that is queued, slow or showing failures.'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::TEAMS as $index => $team) {
            SupportTeam::query()->firstOrCreate(
                ['slug' => $team['slug']],
                [
                    'name' => $team['name'],
                    'description' => $team['description'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }

        foreach (self::CATEGORIES as $index => $category) {
            $team = SupportTeam::query()->where('slug', $category['team'])->first();

            SupportCategory::query()->firstOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'audience' => SupportCategoryAudience::Both,
                    'support_team_id' => $team?->id,
                    'default_priority' => $category['priority'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }

        $this->seedArticles();
    }

    /**
     * A short, accurate library: each article explains a flow the platform
     * actually has, so the help centre is useful on day one.
     */
    private function seedArticles(): void
    {
        foreach ($this->articles() as $index => $article) {
            SupportArticle::query()->firstOrCreate(
                ['slug' => Str::slug($article['title'])],
                [
                    'title' => $article['title'],
                    'category' => $article['category'],
                    'excerpt' => $article['excerpt'],
                    'body' => $article['body'],
                    'is_published' => true,
                    'is_featured' => $index < 2,
                    'sort_order' => $index + 1,
                    'published_at' => now(),
                ],
            );
        }
    }

    /**
     * The shipped articles.
     *
     * @return list<array{title: string, category: string, excerpt: string, body: string}>
     */
    private function articles(): array
    {
        return [
            [
                'title' => 'Create and launch your first SMS campaign',
                'category' => 'Getting started',
                'excerpt' => 'Build an audience, write the message, review the cost, then launch.',
                'body' => implode("\n\n", [
                    '## 1. Build the audience',
                    'Open **Campaigns → Create campaign** and pick the SMS channel. Choose a contact list, a tag or a segment, and import contacts by CSV if the list is still empty.',
                    '## 2. Write the message',
                    'Type the message or pick an approved template. The composer counts characters and shows how many SMS segments the message will use, because each segment is billed separately.',
                    '## 3. Check the cost',
                    'The wallet estimate on the review step is the amount that will be reserved. Submitting a campaign reserves credit; launching captures it and starts delivery.',
                    '## 4. Launch',
                    'Once the campaign is approved (when your account requires approval), launch it. Delivery is queued per recipient and every message keeps its own delivery status.',
                ]),
            ],
            [
                'title' => 'Fund your wallet and understand the ledger',
                'category' => 'Billing',
                'excerpt' => 'Top up with a card or mobile money, then follow every movement on the ledger.',
                'body' => implode("\n\n", [
                    '## Top up',
                    'Open **Wallet → Top up**, choose a package or enter an amount, and pay by card or mobile money. A mobile money payment asks for a one-time code before it is captured.',
                    '## Reserved and captured',
                    'Submitting a campaign *reserves* credit: the amount is held so a launch cannot overspend. Launching *captures* the reservation and turns it into a spend. A cancelled campaign releases the reservation back to the balance.',
                    '## The ledger',
                    'Every top-up, reservation, capture, release and refund is a row on the wallet ledger with a reference. Quote the reference when you ask Finance about a movement.',
                ]),
            ],
            [
                'title' => 'Register a sender ID',
                'category' => 'Delivering messages',
                'excerpt' => 'Request the name recipients see, and understand why it needs approval.',
                'body' => implode("\n\n", [
                    '## Request the sender ID',
                    'Open **Sender IDs → New sender ID** and enter the identifier exactly as it should appear, together with the country and the intended use.',
                    '## Approval',
                    'A compliance reviewer checks the request against the destination country rules. Approved sender IDs can be selected on a campaign; a rejected one shows the reviewer’s reason.',
                    '## Email and voice',
                    'Email campaigns need an approved sending domain, and voice campaigns dial from the platform number the operator configures. Both are requested from the same screen.',
                ]),
            ],
            [
                'title' => 'Read a delivery report',
                'category' => 'Reporting',
                'excerpt' => 'Find out which recipients were delivered, failed or suppressed, and why.',
                'body' => implode("\n\n", [
                    '## Campaign report',
                    'Open **Reports → Campaigns** and choose the campaign. The summary counts delivered, failed, queued and skipped messages; the table below lists each recipient with its provider response.',
                    '## Suppression',
                    'A contact on the DND registry, or one who replied STOP, is skipped rather than sent. The report names the reason, so a low delivery count is never a mystery.',
                    '## Exports',
                    'Every report can be exported to CSV. Exports run in the background and appear under **Reports → Exports** when they are ready to download.',
                ]),
            ],
            [
                'title' => 'Connect to the API',
                'category' => 'Developers',
                'excerpt' => 'Create a token, call the versioned endpoints and follow the rate limits.',
                'body' => implode("\n\n", [
                    '## Create a token',
                    'Open **API tokens** and create a token for the integration. Copy it once: the platform stores only a hash.',
                    '## Call the API',
                    'Requests go to `/api/v1` with `Authorization: Bearer <token>`. The full reference, including payloads and error codes, is on the [API docs](/api-docs) page.',
                    '## Rate limits',
                    'Read requests and write requests have separate per-minute limits. A throttled response carries `Retry-After`; back off rather than retrying immediately.',
                ]),
            ],
            [
                'title' => 'Set up a webhook',
                'category' => 'Developers',
                'excerpt' => 'Receive delivery events, verify the signature and replay a failed delivery.',
                'body' => implode("\n\n", [
                    '## Add the endpoint',
                    'Open **Webhooks → New endpoint**, paste the HTTPS URL and choose the events. The signing secret is shown once and can be rotated later.',
                    '## Verify the signature',
                    'Each delivery carries a signature header computed over the raw body with the secret. Compare it before acting on the payload.',
                    '## Failures',
                    'A failing endpoint is retried with backoff and disabled after repeated failures. The delivery log on the endpoint shows the response code and body, and a test event can be sent from the same screen.',
                ]),
            ],
        ];
    }
}
