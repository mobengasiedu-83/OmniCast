<?php

namespace App\Models;

use App\Enums\AutomationAction;
use App\Enums\AutomationTrigger;
use Database\Factories\AutomationWorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property string|null $description
 * @property AutomationTrigger $trigger
 * @property array<string, mixed>|null $trigger_config
 * @property bool $is_active
 * @property int $runs_count
 * @property Carbon|null $last_run_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'client_id', 'name', 'description', 'trigger', 'trigger_config', 'is_active', 'created_by',
])]
class AutomationWorkflow extends Model
{
    /** @use HasFactory<AutomationWorkflowFactory> */
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
            'trigger_config' => 'array',
            'is_active' => 'boolean',
            'runs_count' => 'integer',
            'last_run_at' => 'datetime',
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
     * The ordered steps the run works through.
     *
     * @return HasMany<AutomationStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(AutomationStep::class)->orderBy('position');
    }

    /**
     * @return HasMany<AutomationRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    /**
     * The step the run should execute at the given position.
     */
    public function stepAt(int $position): ?AutomationStep
    {
        return $this->steps()->where('position', $position)->first();
    }

    /**
     * The trigger configuration value, as a trimmed string.
     */
    public function triggerValue(string $key): ?string
    {
        $value = $this->trigger_config[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Scope the query to the workflows that act for the given tenant.
     *
     * @param  Builder<AutomationWorkflow>  $query
     */
    #[Scope]
    protected function forClient(Builder $query, ?int $clientId): void
    {
        $query->where($query->qualifyColumn('client_id'), $clientId);
    }

    /**
     * Scope the query to the workflows a user may see.
     *
     * @param  Builder<AutomationWorkflow>  $query
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        if ($user->client_id !== null) {
            $query->where($query->qualifyColumn('client_id'), $user->client_id);
        }
    }

    /**
     * Scope the query to the workflows that are switched on.
     *
     * @param  Builder<AutomationWorkflow>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_active'), true);
    }

    /**
     * The step actions the workflow asks for, in order.
     *
     * @return Collection<int, AutomationAction>
     */
    public function actions(): Collection
    {
        return $this->steps->map(fn (AutomationStep $step): AutomationAction => $step->action);
    }
}
