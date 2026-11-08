<?php

namespace App\Models;

use App\Enums\AutomationRunStatus;
use App\Enums\AutomationTrigger;
use Database\Factories\AutomationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $automation_workflow_id
 * @property int $client_id
 * @property int|null $contact_id
 * @property int|null $campaign_id
 * @property AutomationTrigger $trigger
 * @property AutomationRunStatus $status
 * @property int $current_position
 * @property array<string, mixed>|null $context
 * @property string|null $detail
 * @property Carbon|null $resume_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'automation_workflow_id', 'client_id', 'contact_id', 'campaign_id', 'trigger', 'status',
    'current_position', 'context', 'detail', 'resume_at', 'started_at', 'finished_at',
])]
class AutomationRun extends Model
{
    /** @use HasFactory<AutomationRunFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => AutomationTrigger::class,
            'status' => AutomationRunStatus::class,
            'current_position' => 'integer',
            'context' => 'array',
            'resume_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AutomationWorkflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'automation_workflow_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * The steps the run has worked through so far.
     *
     * @return HasMany<AutomationRunStep, $this>
     */
    public function stepLogs(): HasMany
    {
        return $this->hasMany(AutomationRunStep::class)->orderBy('position');
    }

    /**
     * Scope the query to the runs a user may see.
     *
     * @param  Builder<AutomationRun>  $query
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        if ($user->client_id !== null) {
            $query->where($query->qualifyColumn('client_id'), $user->client_id);
        }
    }

    /**
     * Scope the query to the runs a worker still has to move.
     *
     * @param  Builder<AutomationRun>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('status'), [
            AutomationRunStatus::Queued->value,
            AutomationRunStatus::Running->value,
            AutomationRunStatus::Waiting->value,
        ]);
    }

    /**
     * Whether the workflow has been switched off under a waiting run.
     */
    public function canContinue(): bool
    {
        return $this->status->isOpen();
    }
}
