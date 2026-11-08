<?php

namespace App\Models;

use App\Enums\AutomationAction;
use Database\Factories\AutomationStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $automation_workflow_id
 * @property int $position
 * @property AutomationAction $action
 * @property string|null $label
 * @property array<string, mixed>|null $config
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['automation_workflow_id', 'position', 'action', 'label', 'config'])]
class AutomationStep extends Model
{
    /** @use HasFactory<AutomationStepFactory> */
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
            'config' => 'array',
            'position' => 'integer',
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
     * The configuration value, read as a trimmed string when it is scalar.
     */
    public function value(string $key): ?string
    {
        $value = $this->config[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * The configuration value, read as an integer when it is numeric.
     */
    public function integer(string $key): ?int
    {
        $value = $this->config[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * The configuration value, read as a boolean.
     */
    public function boolean(string $key): bool
    {
        return filter_var($this->config[$key] ?? false, FILTER_VALIDATE_BOOL);
    }

    /**
     * The human wording shown for the step on a timeline.
     */
    public function describe(): string
    {
        $label = trim((string) $this->label);

        return $label !== '' ? $label : $this->action->label();
    }
}
