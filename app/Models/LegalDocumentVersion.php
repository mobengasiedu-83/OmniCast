<?php

namespace App\Models;

use App\Enums\LegalDocumentStatus;
use Database\Factories\LegalDocumentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One piece of policy text, with the publication state that dates it.
 *
 * A version is written as a draft and only becomes the text customers read when
 * it is published. Publishing archives whatever was published before, so a
 * document has exactly one live version at any moment and an acceptance can
 * always be read back against the wording of its day.
 *
 * @property int $id
 * @property int $legal_document_id
 * @property string $version
 * @property LegalDocumentStatus $status
 * @property string $body
 * @property string|null $change_summary
 * @property Carbon|null $effective_on
 * @property bool $requires_reacceptance
 * @property Carbon|null $published_at
 * @property int|null $published_by
 * @property Carbon|null $notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'legal_document_id', 'version', 'status', 'body', 'change_summary',
    'effective_on', 'requires_reacceptance', 'published_at', 'published_by',
    'notified_at',
])]
class LegalDocumentVersion extends Model
{
    /** @use HasFactory<LegalDocumentVersionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LegalDocumentStatus::class,
            'effective_on' => 'date',
            'requires_reacceptance' => 'boolean',
            'published_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * The document this version belongs to.
     *
     * @return BelongsTo<LegalDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    /**
     * The administrator who published the version.
     *
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Order the newest version first.
     */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->orderByDesc('effective_on')->orderByDesc('id');
    }

    /**
     * Limit the query to versions customers can read.
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', LegalDocumentStatus::Published->value);
    }

    /**
     * Whether the version is the live text of its document.
     */
    public function isPublished(): bool
    {
        return $this->status->isPublished();
    }

    /**
     * Whether the version is still being written.
     */
    public function isDraft(): bool
    {
        return $this->status === LegalDocumentStatus::Draft;
    }

    /**
     * Whether the wording may still be corrected in place.
     *
     * A version becomes history the moment somebody accepts it or is told about
     * it: from then on the stored text is the evidence, and a change has to be a
     * new version. Anything nobody has relied on yet — a draft, or the seeded
     * first publication of a document — stays editable.
     */
    public function canBeEdited(): bool
    {
        return $this->usageCount('acceptances_count', fn (): int => $this->acceptances()->count()) === 0
            && $this->usageCount('notifications_count', fn (): int => $this->notifications()->count()) === 0;
    }

    /**
     * Whether the version can be removed without destroying evidence.
     */
    public function canBeDeleted(): bool
    {
        return $this->canBeEdited();
    }

    /**
     * A usage tally, reusing the `withCount` value when the query loaded one.
     *
     * @param  callable(): int  $resolve
     */
    protected function usageCount(string $attribute, callable $resolve): int
    {
        $loaded = $this->getAttribute($attribute);

        return $loaded === null ? $resolve() : (int) $loaded;
    }

    /**
     * The short name the screens print, e.g. `v1.1`.
     */
    public function label(): string
    {
        return 'v'.$this->version;
    }

    /**
     * The policy text rendered to HTML for the public page.
     */
    public function html(): string
    {
        return Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Make this version the live text, archiving whatever was live before.
     */
    public function publish(User $actor): void
    {
        DB::transaction(function () use ($actor): void {
            static::query()
                ->where('legal_document_id', $this->legal_document_id)
                ->whereKeyNot($this->getKey())
                ->where('status', LegalDocumentStatus::Published->value)
                ->update(['status' => LegalDocumentStatus::Archived->value]);

            $this->forceFill([
                'status' => LegalDocumentStatus::Published,
                'published_at' => now(),
                'published_by' => $actor->id,
                'effective_on' => $this->effective_on ?? now()->toDateString(),
            ])->save();
        });
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
     * Every acknowledgement recorded against this version.
     *
     * @return HasMany<LegalAcceptance, $this>
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(LegalAcceptance::class);
    }

    /**
     * Retire a version without replacing it.
     */
    public function archive(): void
    {
        $this->forceFill(['status' => LegalDocumentStatus::Archived])->save();
    }

    /**
     * Record that every client account has been told about the version.
     */
    public function markNotified(): void
    {
        $this->forceFill(['notified_at' => now()])->save();
    }
}
