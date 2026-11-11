<?php

namespace App\Models;

use App\Enums\PrivacyRequestAction;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacySubjectType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A platform-tracked data subject request.
 *
 * @property int $id
 * @property string $reference
 * @property int $client_id
 * @property int|null $contact_id
 * @property PrivacySubjectType $subject_type
 * @property PrivacyRequestAction $action
 * @property PrivacyRequestStatus $status
 * @property int $requested_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'reference', 'client_id', 'contact_id', 'subject_type', 'action', 'status',
    'requested_by', 'reviewed_by', 'reviewed_at', 'completed_by', 'completed_at',
])]
class PrivacyRequest extends Model
{
    protected function casts(): array
    {
        return [
            'subject_type' => PrivacySubjectType::class,
            'action' => PrivacyRequestAction::class,
            'status' => PrivacyRequestStatus::class,
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The client account covered by the request.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The individual contact, when the request is contact-specific.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    /**
     * The platform administrator who recorded the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * The independent administrator who reviewed the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The administrator who completed an approved request.
     *
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
