<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The receipt a client can download for a settled wallet top-up.
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $payment_id
 * @property string $number
 * @property InvoiceStatus $status
 * @property string $currency
 * @property string $subtotal
 * @property string $tax
 * @property string $total
 * @property list<array{description: string, quantity: int, unit_price: float, amount: float}>|null $lines
 * @property array<string, mixed>|null $metadata
 * @property Carbon $issued_at
 * @property Carbon|null $paid_at
 */
#[Fillable([
    'client_id', 'payment_id', 'number', 'status', 'currency', 'subtotal', 'tax',
    'total', 'lines', 'metadata', 'issued_at', 'paid_at',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'lines' => 'array',
            'metadata' => 'array',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
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
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
