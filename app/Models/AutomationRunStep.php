<?php

namespace App\Models;

use App\Enums\AutomationAction;
use App\Enums\AutomationStepStatus;
use Database\Factories\AutomationRunStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $automation_run_id
 * @property int|null $automation_step_id
 * @property int $position
 * @property AutomationAction $action
 * @property AutomationStepStatus $status
 * @property string|null $detail
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $ran_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'automation_run_id', 'automation_step_id', 'position', 'action', 'status', 'detail', 'payload', 'ran_at',
])]
class AutomationRunStep extends Model
{
    /** @use HasFactory<AutomationRunStepFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AutomationAction::class,
            'status' => AutomationStepStatus::class,
            'position' => 'integer',
            'payload' => 'array',
            'ran_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AutomationRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'automation_run_id');
    }

    /**
     * @return BelongsTo<AutomationStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(AutomationStep::class, 'automation_step_id');
    }
}
