<?php

namespace App\Services\Automation;

use App\Enums\AutomationStepStatus;

/**
 * What executing one step of a workflow decided.
 *
 * `nextPosition` moves the run somewhere other than the following step, which is
 * how a condition branches; `waitMinutes` pauses the run instead of ending it,
 * and `endsRun` stops a workflow early without calling the run a failure.
 */
final class StepOutcome
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly AutomationStepStatus $status,
        public readonly ?string $detail = null,
        public readonly array $payload = [],
        public readonly ?int $nextPosition = null,
        public readonly ?int $waitMinutes = null,
        public readonly bool $endsRun = false,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function completed(string $detail, array $payload = [], ?int $nextPosition = null): self
    {
        return new self(AutomationStepStatus::Completed, $detail, $payload, $nextPosition);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function skipped(string $detail, array $payload = []): self
    {
        return new self(AutomationStepStatus::Skipped, $detail, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function failed(string $detail, array $payload = []): self
    {
        return new self(AutomationStepStatus::Failed, $detail, $payload);
    }

    /**
     * Pause the run and pick it up again after the delay.
     */
    public static function wait(int $minutes, string $detail): self
    {
        return new self(AutomationStepStatus::Completed, $detail, [], null, $minutes);
    }

    /**
     * Finish the run without working through the remaining steps.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function stop(string $detail, array $payload = []): self
    {
        return new self(AutomationStepStatus::Completed, $detail, $payload, null, null, true);
    }
}
