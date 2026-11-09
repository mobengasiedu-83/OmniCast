<?php

namespace App\Services\Automation;

use App\Enums\AutomationAction;
use App\Enums\AutomationRunStatus;
use App\Jobs\RunAutomationStep;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Tag;
use App\Services\ContactDndService;
use App\Services\Notifications\SystemNotifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Works a run forward, one step at a time.
 *
 * Steps run inside the job that claimed the run, so a workflow of five immediate
 * steps costs one worker slot. A wait step re-queues the run with a delay and an
 * exhausted step budget stops the run, which is what keeps a workflow that jumps
 * backwards from holding a worker forever.
 */
class AutomationRunner
{
    public function __construct(
        private readonly AutomationMessenger $messenger,
        private readonly AutomationWebhookCaller $webhooks,
        private readonly ContactDndService $dnd,
        private readonly AutomationEngine $engine,
        private readonly SystemNotifier $notifications,
    ) {}

    /**
     * Move the run as far as it can go without another delay.
     */
    public function advance(AutomationRun $run): AutomationRun
    {
        $run->refresh();

        if (! $run->status->isOpen()) {
            return $run;
        }

        if ($run->status === AutomationRunStatus::Waiting && $run->resume_at?->isFuture()) {
            $this->reschedule($run, $run->resume_at);

            return $run;
        }

        $workflow = $run->workflow;

        if ($workflow === null || ! $workflow->is_active) {
            return $this->finish($run, AutomationRunStatus::Cancelled, 'The workflow was switched off or deleted.');
        }

        if (! $this->engine->enabled($run->client)) {
            return $this->finish($run, AutomationRunStatus::Cancelled, 'Automations are switched off for this tenant.');
        }

        $run->forceFill([
            'status' => AutomationRunStatus::Running,
            'started_at' => $run->started_at ?? now(),
            'resume_at' => null,
            'detail' => null,
        ])->save();

        $limit = max(1, (int) config('automations.max_steps_per_run', 50));

        for ($executed = 0; $executed < $limit; $executed++) {
            $step = $workflow->stepAt($run->current_position);

            if ($step === null) {
                return $this->finish($run, AutomationRunStatus::Completed, 'The workflow ran to the end.');
            }

            $outcome = $this->perform($run, $step);

            $this->log($run, $step, $outcome);

            if ($outcome->waitMinutes !== null) {
                return $this->pause($run, $step, $outcome);
            }

            if (! $outcome->status->allowsProgression()) {
                return $this->finish($run, AutomationRunStatus::Failed, (string) $outcome->detail);
            }

            if ($outcome->endsRun) {
                return $this->finish($run, AutomationRunStatus::Completed, (string) ($outcome->detail ?? 'The workflow stopped.'));
            }

            $run->forceFill([
                'current_position' => $outcome->nextPosition ?? $step->position + 1,
            ])->save();
        }

        return $this->finish(
            $run,
            AutomationRunStatus::Failed,
            'The run passed the step limit, so the workflow is probably looping.',
        );
    }

    /**
     * Execute one step and report what happened.
     */
    protected function perform(AutomationRun $run, AutomationStep $step): StepOutcome
    {
        try {
            return match ($step->action) {
                AutomationAction::SendSms => $this->sendSms($run, $step),
                AutomationAction::SendEmail => $this->sendEmail($run, $step),
                AutomationAction::AddTag => $this->addTag($run, $step),
                AutomationAction::RemoveTag => $this->removeTag($run, $step),
                AutomationAction::AddToList => $this->addToList($run, $step),
                AutomationAction::RemoveFromList => $this->removeFromList($run, $step),
                AutomationAction::SetDnd => $this->setDnd($run),
                AutomationAction::LiftDnd => $this->liftDnd($run),
                AutomationAction::Condition => $this->condition($run, $step),
                AutomationAction::Wait => $this->wait($step),
                AutomationAction::Webhook => $this->webhook($run, $step),
                AutomationAction::Stop => StepOutcome::stop('Step '.$step->position.' ended the workflow.'),
            };
        } catch (Throwable $exception) {
            report($exception);

            Log::error('An automation step threw an exception.', [
                'run_id' => $run->id,
                'position' => $step->position,
                'action' => $step->action->value,
                'exception' => $exception::class,
            ]);

            return StepOutcome::failed('The step failed: '.$exception->getMessage());
        }
    }

    protected function sendSms(AutomationRun $run, AutomationStep $step): StepOutcome
    {
        $contact = $this->contact($run);

        if (! $contact instanceof Contact) {
            return StepOutcome::skipped('The run has no contact to text.');
        }

        if ((bool) $contact->dnd) {
            return StepOutcome::skipped('The contact is on do-not-disturb, so no text was sent.');
        }

        $body = $step->value('body');

        if ($body === null) {
            return StepOutcome::failed('The step has no message body.');
        }

        $result = $this->messenger->sms($contact, $body, $step->value('from'));

        return $result['accepted']
            ? StepOutcome::completed($result['detail'], $result['payload'])
            : StepOutcome::failed($result['detail'], $result['payload']);
    }

    protected function sendEmail(AutomationRun $run, AutomationStep $step): StepOutcome
    {
        $contact = $this->contact($run);

        if (! $contact instanceof Contact) {
            return StepOutcome::skipped('The run has no contact to email.');
        }

        if ((bool) $contact->dnd) {
            return StepOutcome::skipped('The contact is on do-not-disturb, so no email was sent.');
        }

        if (trim((string) $contact->email) === '') {
            return StepOutcome::skipped('The contact has no email address, so no email was sent.');
        }

        $body = $step->value('body');

        if ($body === null) {
            return StepOutcome::failed('The step has no email body.');
        }

        $result = $this->messenger->email($contact, (string) $step->value('subject'), $body, $step->value('from'));

        return $result['accepted']
            ? StepOutcome::completed($result['detail'], $result['payload'])
            : StepOutcome::failed($result['detail'], $result['payload']);
    }

    protected function addTag(AutomationRun $run, AutomationStep $step): StepOutcome
    {
        $contact = $this->contact($run);
        $tag = $this->tag($run, $step);

        if (! $contact instanceof Contact) {
            return StepOutcome::skipped('The run has no contact to tag.');
        }

        if (! $tag instanceof Tag) {
            return StepOutcome::failed('The tag this step needs no longer exists.');
        }

        if ($contact->tags()->whereKey($tag->id)->exists()) {
            return StepOutcome::skipped('The contact already carries the tag "'.$tag->name.'".');
        }

        $contact->tags()->attach($tag->id);

        return StepOutcome::completed('Tagged the contact "'.$tag->name.'".', ['tag_id' => $tag->id]);
    }

    protected function removeTag(AutomationRun $run, AutomationStep $step): StepOutcome
    {
        $contact = $this->contact($run);
        $tag = $this->tag($run, $step);

        if (! $contact instanceof Contact) {
            return StepOutcome::skipped('The run has no contact to change.');
        }

        if (! $tag instanceof Tag) {
            return StepOutcome::failed('The tag this step needs no longer exists.');
        }

        if ($contact->tags()->whereKey($tag->id)->doesntExist()) {
            return StepOutcome::skipped('The contact did not carry the tag "'.$tag->name.'".');
        }

        $contact->tags()->detach($tag->id);

        return StepOutcome::completed('Removed the tag "'.$tag->name.'".', ['tag_id' => $tag->id]);
    }

    protected function addToList(AutomationRun $run, AutomationStep $step): StepOutcome
    {
        $contact = $this->contact($run);
        $list = $this->contactList($run, $step);

        if (! $contact instanceof Contact) {
            return StepOutcome::skipped('The run has no contact to add.');
        }

        if (! $list instanceof ContactList) {
            return StepOutcome::failed('The list this step needs no longer exists.');
        }

        if ($contact->contactLists()->whereKey($list->id)->exists()) {
            return StepOutcome::skipped('The contact is already on the list "'.$list->name.'".');
        }

        $contact->contactLists()->syncWithoutDetaching([$list->id => ['added_at' => now()]]);

        return StepOutcome::completed('Added the contact to "'.$list->name.'".', ['contact_list_id' => $list->id]);
    }

    protected function removeFromList(AutomationRun $run, AutomationStep $step): StepOutcome
    {
        $contact = $this->contact($run);
        $list = $this->contactList($run, $step);

        if (! $contact instanceof Contact) {
            return StepOutcome::skipped('The run has no contact to change.');
        }

        if (! $list instanceof ContactList) {
            return StepOutcome::failed('The list this step needs no longer exists.');
        }

        if ($contact->contactLists()->whereKey($list->id)->doesntExist()) {
            return StepOutcome::skipped('The contact was not on the list "'.$list->name.'".');
        }

        $contact->contactLists()->detach($list->id);

        return StepOutcome::completed('Took the contact off "'.$list->name.'".', ['contact_list_id' => $list->id]);
    }

    protected function setDnd(AutomationRun $run): StepOutcome
    {
        $contact = $this->contact($run);

        if (! $contact instanceof Contact) {
            return StepOutcome::skipped('The run has no contact to change.');
        }

        if ((bool) $contact->dnd) {
            return StepOutcome::skipped('The contact is already on do-not-disturb.');
        }

        $this->dnd->apply($contact, 'automation');

        return StepOutcome::completed('Put the contact on do-not-disturb.');
    }

    protected function liftDnd(AutomationRun $run): StepOutcome
    {
        $contact = $this->contact($run);

        if (! $contact instanceof Contact) {
            return StepOutcome::skipped('The run has no contact to change.');
        }

        if (! (bool) $contact->dnd) {
            return StepOutcome::skipped('The contact is not on do-not-disturb.');
        }

        $this->dnd->lift($contact);

        return StepOutcome::completed('Took the contact off do-not-disturb.');
    }

    protected function condition(AutomationRun $run, AutomationStep $step): StepOutcome
    {
        $contact = $this->contact($run);

        if (! $contact instanceof Contact) {
            return StepOutcome::skipped('The run has no contact to check.');
        }

        if ($this->conditionMet($contact, $step)) {
            return StepOutcome::completed('The check passed.');
        }

        $target = $step->integer('on_false_position');

        if ($target === null) {
            return StepOutcome::stop('The check failed, so the workflow ended.');
        }

        return StepOutcome::completed(
            'The check failed, so the workflow jumped to step '.$target.'.',
            nextPosition: $target,
        );
    }

    protected function wait(AutomationStep $step): StepOutcome
    {
        $minutes = max(1, $step->integer('minutes') ?? 60);

        return StepOutcome::wait($minutes, 'Paused the run for '.$minutes.' minute(s).');
    }

    protected function webhook(AutomationRun $run, AutomationStep $step): StepOutcome
    {
        $result = $this->webhooks->send($run, $step);

        return $result['accepted']
            ? StepOutcome::completed($result['detail'], $result['payload'])
            : StepOutcome::failed($result['detail'], $result['payload']);
    }

    /**
     * Whether the contact satisfies the condition step's check.
     */
    protected function conditionMet(Contact $contact, AutomationStep $step): bool
    {
        return match ($step->value('check') ?? 'has_tag') {
            'has_tag' => $contact->tags()->whereKey((int) $step->integer('tag_id'))->exists(),
            'in_list' => $contact->contactLists()->whereKey((int) $step->integer('contact_list_id'))->exists(),
            'dnd' => (bool) $contact->dnd,
            'not_dnd' => ! (bool) $contact->dnd,
            'has_email' => trim((string) $contact->email) !== '',
            'has_phone' => trim((string) $contact->phone) !== '',
            'custom_field' => (string) data_get($contact->custom_fields ?? [], (string) ($step->value('field') ?? ''))
                === (string) $step->value('value'),
            default => false,
        };
    }

    /**
     * Pause the run and queue the job that will pick it back up.
     */
    protected function pause(AutomationRun $run, AutomationStep $step, StepOutcome $outcome): AutomationRun
    {
        $minutes = min(
            max(1, (int) $outcome->waitMinutes),
            max(1, (int) config('automations.max_wait_minutes', 43200)),
        );

        $resumeAt = now()->addMinutes($minutes);

        $run->forceFill([
            'status' => AutomationRunStatus::Waiting,
            'current_position' => $step->position + 1,
            'resume_at' => $resumeAt,
            'detail' => 'Waiting until '.$resumeAt->toDateTimeString().'.',
        ])->save();

        $this->reschedule($run, $resumeAt);

        return $run;
    }

    /**
     * Record what the step did on the run timeline.
     */
    protected function log(AutomationRun $run, AutomationStep $step, StepOutcome $outcome): void
    {
        $run->stepLogs()->create([
            'automation_step_id' => $step->id,
            'position' => $step->position,
            'action' => $step->action,
            'status' => $outcome->status,
            'detail' => $outcome->detail === null ? null : Str::limit($outcome->detail, 250),
            'payload' => $outcome->payload === [] ? null : $outcome->payload,
            'ran_at' => now(),
        ]);
    }

    /**
     * Queue the next slice of work for the run.
     */
    protected function reschedule(AutomationRun $run, ?CarbonInterface $dueAt = null): void
    {
        $job = RunAutomationStep::dispatch($run->id)
            ->onQueue((string) config('automations.queue', 'default'));

        if ($dueAt !== null) {
            $job->delay($dueAt);
        }
    }

    /**
     * Close the run with the given status.
     */
    protected function finish(AutomationRun $run, AutomationRunStatus $status, string $detail): AutomationRun
    {
        $run->forceFill([
            'status' => $status,
            'detail' => Str::limit($detail, 250),
            'resume_at' => null,
            'finished_at' => now(),
        ])->save();

        if ($status === AutomationRunStatus::Failed) {
            $this->notifications->automationFailed($run, $detail);
        }

        return $run;
    }

    /**
     * The contact the run acts on, re-read so a step sees the columns the last one changed.
     */
    protected function contact(AutomationRun $run): ?Contact
    {
        return $run->contact_id === null ? null : Contact::query()->find($run->contact_id);
    }

    /**
     * The tag the step names, when it belongs to the run's tenant.
     */
    protected function tag(AutomationRun $run, AutomationStep $step): ?Tag
    {
        $tagId = $step->integer('tag_id');

        if ($tagId === null) {
            return null;
        }

        return Tag::query()->where('client_id', $run->client_id)->find($tagId);
    }

    /**
     * The list the step names, when it belongs to the run's tenant.
     */
    protected function contactList(AutomationRun $run, AutomationStep $step): ?ContactList
    {
        $listId = $step->integer('contact_list_id');

        if ($listId === null) {
            return null;
        }

        return ContactList::query()->where('client_id', $run->client_id)->find($listId);
    }
}
