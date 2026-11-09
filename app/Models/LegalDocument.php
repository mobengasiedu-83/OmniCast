<?php

namespace App\Models;

use App\Enums\LegalAcceptanceContext;
use App\Enums\LegalDocumentStatus;
use Database\Factories\LegalDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A published policy document and the rules about when it must be accepted.
 *
 * The document is the stable identity a customer's acceptance points at; the
 * wording lives in {@see LegalDocumentVersion} rows, so replacing the text never
 * rewrites history.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $summary
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $requires_acceptance_at_registration
 * @property bool $requires_acceptance_on_campaign_submission
 * @property bool $requires_acceptance_on_wallet_top_up
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'slug', 'title', 'summary', 'sort_order', 'is_active',
    'requires_acceptance_at_registration',
    'requires_acceptance_on_campaign_submission',
    'requires_acceptance_on_wallet_top_up',
])]
class LegalDocument extends Model
{
    /** @use HasFactory<LegalDocumentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'requires_acceptance_at_registration' => 'boolean',
            'requires_acceptance_on_campaign_submission' => 'boolean',
            'requires_acceptance_on_wallet_top_up' => 'boolean',
        ];
    }

    /**
     * Every version ever written for the document, newest first.
     *
     * @return HasMany<LegalDocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(LegalDocumentVersion::class)->latestFirst();
    }

    /**
     * The version customers read and accept right now.
     *
     * @return HasOne<LegalDocumentVersion, $this>
     */
    public function publishedVersion(): HasOne
    {
        return $this->hasOne(LegalDocumentVersion::class)
            ->where('status', LegalDocumentStatus::Published->value);
    }

    /**
     * Every acknowledgement recorded against any version of the document.
     *
     * @return HasMany<LegalAcceptance, $this>
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(LegalAcceptance::class);
    }

    /**
     * Every account the document was announced to.
     *
     * @return HasMany<LegalNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(LegalNotification::class);
    }

    /**
     * Limit the query to documents the public site shows.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Limit the query to documents that have something published to read.
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereHas('versions', fn (Builder $query) => $query->where('status', LegalDocumentStatus::Published->value));
    }

    /**
     * The order the public page and the footer list the documents in.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('title');
    }

    /**
     * The address of the public page for the document.
     */
    public function url(): string
    {
        return route('legal.show', $this->slug);
    }

    /**
     * The print-ready page a customer saves as a PDF.
     */
    public function printUrl(): string
    {
        return route('legal.print', $this->slug);
    }

    /**
     * Whether the document can be removed without destroying evidence.
     *
     * A document any account has accepted, or been told about, is part of the
     * compliance record: it can be withdrawn from the site but not deleted.
     */
    public function canBeDeleted(): bool
    {
        return $this->acceptances()->doesntExist()
            && $this->notifications()->doesntExist();
    }

    /**
     * Whether the document has a version customers can read.
     */
    public function isPublished(): bool
    {
        return $this->publishedVersion()->exists();
    }

    /**
     * Whether acceptance is collected on a given activity.
     */
    public function requiresAcceptanceOn(LegalAcceptanceContext $context): bool
    {
        return match ($context) {
            LegalAcceptanceContext::Registration => $this->requires_acceptance_at_registration,
            LegalAcceptanceContext::CampaignSubmission => $this->requires_acceptance_on_campaign_submission,
            LegalAcceptanceContext::WalletTopUp => $this->requires_acceptance_on_wallet_top_up,
            LegalAcceptanceContext::OrganisationSettings,
            LegalAcceptanceContext::Dpa => true,
        };
    }
}
