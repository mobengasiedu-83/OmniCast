<?php

namespace App\Models;

use App\Enums\ContactConsentStatus;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property string $first_name
 * @property string|null $last_name
 * @property string $phone
 * @property string|null $original_phone
 * @property string|null $alternative_phone
 * @property string|null $email
 * @property string|null $company
 * @property string|null $account_number
 * @property string|null $customer_id
 * @property string|null $location
 * @property string $language
 * @property string $timezone
 * @property ContactConsentStatus $consent_status
 * @property bool $is_do_not_contact
 * @property Carbon|null $opted_out_at
 * @property array<string, mixed>|null $custom_fields
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'client_id', 'first_name', 'last_name', 'phone', 'original_phone', 'alternative_phone',
    'email', 'company', 'account_number', 'customer_id', 'location', 'language', 'timezone',
    'consent_status', 'is_do_not_contact', 'opted_out_at', 'custom_fields', 'created_by',
])]
#[Appends(['full_name'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consent_status' => ContactConsentStatus::class,
            'is_do_not_contact' => 'boolean',
            'opted_out_at' => 'datetime',
            'custom_fields' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<ContactList, $this>
     */
    public function contactLists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class, 'contact_list_contact')
            ->withPivot('added_at')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /**
     * @return HasMany<CampaignContact, $this>
     */
    public function campaignContacts(): HasMany
    {
        return $this->hasMany(CampaignContact::class);
    }

    /**
     * The display name of the contact.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * Limit the query to the client an authenticated user may see.
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        if ($user->client_id !== null) {
            $query->where($query->qualifyColumn('client_id'), $user->client_id);
        }
    }

    /**
     * Limit the query to contacts that may legally receive a broadcast.
     */
    #[Scope]
    protected function broadcastable(Builder $query): void
    {
        $query->where('is_do_not_contact', false)
            ->where('consent_status', '!=', ContactConsentStatus::OptedOut->value);
    }

    /**
     * Limit the query to a free-text search over name, phone and account number.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $query->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('account_number', 'like', "%{$term}%");
        });
    }
}
