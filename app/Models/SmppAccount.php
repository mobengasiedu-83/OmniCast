<?php

namespace App\Models;

use App\Enums\SmppBindMode;
use Database\Factories\SmppAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An ESME allowed to bind to the SMPP server on behalf of one client.
 *
 * The password is stored as a hash: the server verifies a `bind_*` PDU against
 * it and never keeps the plaintext after the account is created.
 *
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property string $system_id
 * @property string $password_hash
 * @property SmppBindMode $bind_mode
 * @property list<string>|null $allowed_sources
 * @property int $throughput_per_second
 * @property bool $is_active
 * @property Carbon|null $last_bound_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'client_id', 'name', 'system_id', 'password_hash', 'bind_mode', 'allowed_sources',
    'throughput_per_second', 'is_active', 'last_bound_at', 'created_by',
])]
#[Hidden(['password_hash'])]
class SmppAccount extends Model
{
    /** @use HasFactory<SmppAccountFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bind_mode' => SmppBindMode::class,
            'allowed_sources' => 'array',
            'throughput_per_second' => 'integer',
            'is_active' => 'boolean',
            'last_bound_at' => 'datetime',
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
     * @return HasMany<SmppSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(SmppSession::class);
    }

    /**
     * @return HasMany<SmppMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SmppMessage::class);
    }

    /**
     * Whether the source address may be used on this account.
     */
    public function allowsSource(string $source): bool
    {
        $allowed = $this->allowed_sources ?? [];

        return $allowed === [] || in_array(strtoupper($source), array_map('strtoupper', $allowed), true);
    }

    /**
     * Whether the account may open the given kind of bind.
     *
     * A transceiver account may bind any of the three modes; a one-way account
     * is refused the other direction because its provisioning never included it.
     */
    public function allowsBindMode(SmppBindMode $mode): bool
    {
        return $this->is_active
            && ($this->bind_mode === SmppBindMode::Transceiver || $this->bind_mode === $mode);
    }
}
