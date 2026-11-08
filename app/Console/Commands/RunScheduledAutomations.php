<?php

namespace App\Console\Commands;

use App\Enums\AutomationTrigger;
use App\Models\AutomationWorkflow;
use App\Services\Automation\AutomationEngine;
use Illuminate\Console\Command;

/**
 * Starts the workflows that run on a daily schedule.
 *
 * A scheduled workflow stores the hour it wants in `trigger_config`, so the
 * minute scheduler only starts the ones whose hour has arrived. The run has no
 * contact: the steps that need one skip themselves, which is how a daily
 * housekeeping workflow stays harmless.
 */
class RunScheduledAutomations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automations:run-scheduled
        {--hour= : Override the hour of the day that is due}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the automation workflows whose scheduled hour has arrived';

    /**
     * Execute the console command.
     */
    public function handle(AutomationEngine $engine): int
    {
        $hour = $this->option('hour');
        $hour = $hour === null ? (int) now()->format('G') : max(0, min(23, (int) $hour));

        $workflows = AutomationWorkflow::query()
            ->active()
            ->where('trigger', AutomationTrigger::Schedule->value)
            ->with('client')
            ->orderBy('id')
            ->get()
            ->filter(fn (AutomationWorkflow $workflow): bool => (int) ($workflow->trigger_config['hour'] ?? 9) === $hour);

        $started = 0;

        foreach ($workflows as $workflow) {
            if ($engine->start($workflow) !== null) {
                $started++;
            }
        }

        $this->info($started === 0
            ? "No scheduled workflow was due at {$hour}:00."
            : "Started {$started} scheduled workflow(s) for {$hour}:00.");

        return self::SUCCESS;
    }
}
