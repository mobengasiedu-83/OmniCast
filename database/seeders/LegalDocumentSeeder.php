<?php

namespace Database\Seeders;

use App\Enums\LegalDocumentStatus;
use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Publishes the five policy documents the platform launches with.
 *
 * The wording lives in `database/seeders/data/legal`, converted from the drafts
 * the operator supplied, so it is versioned with the code and can be reviewed in
 * a diff. The seeder is safe to run repeatedly: it creates a document only when
 * its slug is missing and a version only when the document has none, so it never
 * overwrites wording an administrator has since corrected in the admin screens.
 */
class LegalDocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (LegalDocumentType::cases() as $index => $type) {
            $document = LegalDocument::query()->firstOrCreate(
                ['slug' => $type->value],
                [
                    'title' => $type->label(),
                    'summary' => $type->summary(),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'requires_acceptance_at_registration' => $type->isAcceptedAtRegistration(),
                    'requires_acceptance_on_campaign_submission' => $type->isAcceptedOnCampaignSubmission(),
                    'requires_acceptance_on_wallet_top_up' => $type->isAcceptedOnWalletTopUp(),
                ],
            );

            if ($document->versions()->exists()) {
                continue;
            }

            $path = database_path('seeders/data/legal/'.$type->value.'.md');

            if (! is_file($path)) {
                continue;
            }

            $document->versions()->create([
                'version' => '1.0',
                'status' => LegalDocumentStatus::Published,
                'body' => (string) file_get_contents($path),
                'change_summary' => 'Initial publication of the policy text supplied for launch.',
                'effective_on' => now()->toDateString(),
                'requires_reacceptance' => true,
                'published_at' => now(),
            ]);
        }
    }
}
