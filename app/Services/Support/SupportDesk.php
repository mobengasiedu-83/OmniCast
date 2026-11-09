<?php

namespace App\Services\Support;

use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\SupportTicketType;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opens, replies to and settles a support conversation.
 *
 * Every screen that accepts a message goes through here, so the reference, the
 * thread, the timestamps and the emails behave the same whether the words came
 * from the public form, a client's ticket screen or an agent's reply box.
 */
class SupportDesk
{
    public function __construct(protected SupportNotifier $notifier) {}

    /**
     * Open a conversation and write its opening message into the thread.
     *
     * @param  array{
     *     type: SupportTicketType,
     *     source: string,
     *     name: string,
     *     email: string,
     *     subject: string,
     *     description: string,
     *     phone?: string|null,
     *     company?: string|null,
     *     category?: SupportCategory|null,
     *     priority?: SupportPriority|null,
     *     client_id?: int|null,
     *     user_id?: int|null,
     *     campaign_id?: int|null,
     *     wallet_transaction_id?: int|null,
     *     consented?: bool,
     *     ip_address?: string|null,
     *     user_agent?: string|null,
     *     metadata?: array<string, mixed>,
     * }  $attributes
     */
    public function open(array $attributes): SupportTicket
    {
        $category = $attributes['category'] ?? null;

        $ticket = DB::transaction(function () use ($attributes, $category): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'reference' => $this->reference($attributes['type']),
                'type' => $attributes['type']->value,
                'source' => $attributes['source'],
                'support_category_id' => $category?->id,
                'support_team_id' => $category?->support_team_id,
                'client_id' => $attributes['client_id'] ?? null,
                'user_id' => $attributes['user_id'] ?? null,
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'] ?? null,
                'company' => $attributes['company'] ?? null,
                'subject' => $attributes['subject'],
                'description' => $attributes['description'],
                'priority' => ($attributes['priority'] ?? $category?->default_priority ?? SupportPriority::Normal)->value,
                'status' => SupportTicketStatus::New->value,
                'campaign_id' => $attributes['campaign_id'] ?? null,
                'wallet_transaction_id' => $attributes['wallet_transaction_id'] ?? null,
                'consent_at' => ($attributes['consented'] ?? false) ? now() : null,
                'ip_address' => $attributes['ip_address'] ?? null,
                'user_agent' => $attributes['user_agent'] ?? null,
                'metadata' => $attributes['metadata'] ?? null,
            ]);

            $ticket->messages()->create([
                'user_id' => $attributes['user_id'] ?? null,
                'author_name' => $attributes['name'],
                'author_email' => $attributes['email'],
                'body' => $attributes['description'],
                'is_internal' => false,
                'source' => 'requester',
            ]);

            return $ticket;
        });

        $ticket = $ticket->fresh(['category', 'team', 'client']);

        $this->notifier->conversationOpened($ticket);

        return $ticket;
    }

    /**
     * Add a message to a conversation and move its timestamps on.
     */
    public function reply(
        SupportTicket $ticket,
        string $body,
        ?User $author,
        bool $internal = false,
    ): SupportTicketMessage {
        $fromAgent = $author !== null && $author->isPlatformUser();

        $message = DB::transaction(function () use ($ticket, $body, $author, $internal, $fromAgent): SupportTicketMessage {
            $message = $ticket->messages()->create([
                'user_id' => $author?->id,
                'author_name' => $author?->name ?? $ticket->name,
                'author_email' => $author?->email ?? $ticket->email,
                'body' => $body,
                'is_internal' => $internal,
                'source' => $fromAgent ? 'agent' : 'requester',
            ]);

            $attributes = ['last_reply_at' => now()];

            if ($fromAgent) {
                $attributes['first_responded_at'] = $ticket->first_responded_at ?? now();

                if ($ticket->status === SupportTicketStatus::New) {
                    $attributes['status'] = SupportTicketStatus::Open->value;
                }
            } elseif ($ticket->status->isSettled()) {
                /*
                | A settled conversation the requester writes to again is open
                | again: the problem came back, so the settled stamps are cleared
                | rather than left to contradict the new status.
                */
                $attributes['status'] = SupportTicketStatus::Open->value;
                $attributes['resolved_at'] = null;
                $attributes['closed_at'] = null;
            } elseif ($ticket->status === SupportTicketStatus::Pending) {
                $attributes['status'] = SupportTicketStatus::Open->value;
            }

            $ticket->forceFill($attributes)->save();

            return $message;
        });

        $ticket = $ticket->fresh(['category', 'team', 'client']);

        if ($internal) {
            return $message;
        }

        $fromAgent
            ? $this->notifier->agentReplied($ticket, $message)
            : $this->notifier->requesterReplied($ticket, $message);

        return $message;
    }

    /**
     * Move a conversation to a new state, keeping the audit stamps honest.
     */
    public function transition(
        SupportTicket $ticket,
        SupportTicketStatus $status,
        ?User $actor,
    ): SupportTicket {
        $attributes = ['status' => $status->value];

        if ($status === SupportTicketStatus::Resolved) {
            $attributes['resolved_at'] = $ticket->resolved_at ?? now();
        } elseif ($status === SupportTicketStatus::Closed) {
            $attributes['resolved_at'] = $ticket->resolved_at ?? now();
            $attributes['closed_at'] = $ticket->closed_at ?? now();
        } else {
            $attributes['resolved_at'] = null;
            $attributes['closed_at'] = null;
        }

        $ticket->forceFill($attributes)->save();

        activity()
            ->causedBy($actor)
            ->performedOn($ticket)
            ->withProperties(['status' => $status->value])
            ->log('changed support conversation status');

        $ticket = $ticket->fresh(['category', 'team', 'client']);

        $this->notifier->statusChanged($ticket, $status);

        return $ticket;
    }

    /**
     * Give the conversation an owner, or take the owner away.
     */
    public function assign(SupportTicket $ticket, ?User $assignee): SupportTicket
    {
        $ticket->forceFill(['assigned_to' => $assignee?->id])->save();

        return $ticket->fresh(['assignee']);
    }

    /**
     * A reference the team can quote: `TKT-8F3KQ2`.
     */
    public function reference(SupportTicketType $type): string
    {
        do {
            $reference = $type->prefix().'-'.Str::upper(Str::random(6));
        } while (SupportTicket::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
