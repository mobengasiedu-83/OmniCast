<?php

namespace App\Models;

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
 * @property string|null $first_name
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
 * @property bool $dnd
 * @property Carbon|null $dnd_at
 * @property array<string, mixed>|null $custom_fields
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'client_id', 'first_name', 'last_name', 'phone', 'original_phone', 'alternative_phone',
    'email', 'company', 'account_number', 'customer_id', 'location', 'language', 'timezone',
    'dnd', 'dnd_at', 'custom_fields', 'created_by',
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
            'dnd' => 'boolean',
            'dnd_at' => 'datetime',
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
     * The display name of the contact, falling back to the MSISDN.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(function (): string {
            $name = trim(((string) $this->first_name).' '.((string) $this->last_name));

            return $name !== '' ? $name : $this->phone;
        });
    }

    /**
     * Limit the query to the client an authenticated user may see.
     *
     * @param  Builder<Contact>  $query
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        if ($user->client_id !== null) {
            $query->where($query->qualifyColumn('client_id'), $user->client_id);
        }
    }

    /**
     * Limit the query to contacts that may receive a broadcast.
     *
     * @param  Builder<Contact>  $query
     */
    #[Scope]
    protected function broadcastable(Builder $query): void
    {
        $query->where('dnd', false);
    }

    /**
     * Limit the query to a free-text search over name, phone and account number.
     *
     * @param  Builder<Contact>  $query
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
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('account_number', 'like', "%{$term}%");
        });
    }
}
