<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportCategory;
use App\Models\SupportTeam;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Support\SupportAttachmentStore;
use App\Services\Support\SupportDesk;
use App\Services\Support\SupportLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One conversation, as the agent answering it sees it.
 *
 * The same screen serves an enquiry and a ticket: the thread, the requester, the
 * campaign or wallet movement the client pointed at, and the three controls that
 * move a conversation on — a reply, an owner and a status.
 */
class SupportTicketController extends Controller
{
    public function __construct(
        protected SupportDesk $desk,
        protected SupportLibrary $library,
        protected SupportAttachmentStore $attachments,
    ) {}

    /**
     * Show the conversation and everything needed to answer it.
     */
    public function show(Request $request, SupportTicket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'category:id,name',
            'team:id,name',
            'assignee:id,name',
            'client:id,name,code',
            'user:id,name,email',
            'campaign:id,name,reference,status',
            'walletTransaction:id,description,amount,reference,created_at',
        ]);

        $messages = $ticket->messages()
            ->with(['user:id,name', 'attachments'])
            ->get()
            ->map(fn (SupportTicketMessage $message): array => [
                'id' => $message->id,
                'author_name' => $message->author_name,
                'author_email' => $message->author_email,
                'from_agent' => $message->isFromAgent(),
                'is_internal' => $message->is_internal,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
                'attachments' => $message->attachments->map(fn (SupportTicketAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'size' => $attachment->humanSize(),
                    'url' => route('admin.support.tickets.attachments.download', [$ticket->reference, $attachment->id]),
                ])->all(),
            ]);

        return Inertia::render('admin/support/ticket', [
            'ticket' => [
                'reference' => $ticket->reference,
                'type' => $ticket->type->value,
                'type_label' => $ticket->type->label(),
                'is_ticket' => $ticket->isTicket(),
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'priority' => $ticket->priority->value,
                'priority_label' => $ticket->priority->label(),
                'name' => $ticket->name,
                'email' => $ticket->email,
                'phone' => $ticket->phone,
                'company' => $ticket->company,
                'category' => $ticket->category?->name,
                'category_id' => $ticket->support_category_id,
                'team' => $ticket->team?->name,
                'team_id' => $ticket->support_team_id,
                'assignee' => $ticket->assignee?->name,
                'assigned_to' => $ticket->assigned_to,
                'client' => $ticket->client?->name,
                'client_code' => $ticket->client?->code,
                'campaign' => $ticket->campaign === null ? null : [
                    'name' => $ticket->campaign->name,
                    'reference' => $ticket->campaign->reference,
                    'status' => $ticket->campaign->status->value,
                ],
                'transaction' => $ticket->walletTransaction === null ? null : [
                    'description' => $ticket->walletTransaction->description,
                    'amount' => $ticket->walletTransaction->amount,
                    'reference' => $ticket->walletTransaction->reference,
                    'created_at' => $ticket->walletTransaction->created_at?->toIso8601String(),
                ],
                'ip_address' => $ticket->ip_address,
                'consent_at' => $ticket->consent_at?->toIso8601String(),
                'created_at' => $ticket->created_at?->toIso8601String(),
                'first_responded_at' => $ticket->first_responded_at?->toIso8601String(),
                'resolved_at' => $ticket->resolved_at?->toIso8601String(),
                'closed_at' => $ticket->closed_at?->toIso8601String(),
                'last_reply_at' => $ticket->last_reply_at?->toIso8601String(),
            ],
            'messages' => $messages,
            'statuses' => SupportTicketStatus::catalogue(),
            'priorities' => SupportPriority::catalogue(),
            'teams' => $this->teamOptions(),
            'categories' => $this->categoryOptions(),
            'agents' => $this->agentOptions(),
            'attachments' => $this->library->attachmentLimits(),
            'can_manage' => $request->user()->can('support.manage'),
        ]);
    }

    /**
     * Answer the conversation, publicly or with an internal note.
     */
    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('reply', $ticket);

        $maxFiles = (int) config('support.attachments.max_files', 3);
        $maxKilobytes = (int) config('support.attachments.max_kilobytes', 5120);
        $mimes = implode(',', (array) config('support.attachments.mimes', []));

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'internal' => ['sometimes', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:'.$maxFiles],
            'attachments.*' => ['file', 'mimes:'.$mimes, 'max:'.$maxKilobytes],
        ]);

        $internal = (bool) ($validated['internal'] ?? false);

        $message = $this->desk->reply($ticket, $validated['body'], $request->user(), $internal);

        $this->attachments->store(
            $ticket,
            $message,
            $request->file('attachments', []) ?? [],
            $request->user(),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $internal ? 'Internal note added. The requester cannot see it.' : 'Reply sent to the requester.',
        ]);
    }

    /**
     * Move the conversation: owner, team, priority and status.
     */
    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(SupportTicketStatus::class)],
            'priority' => ['required', Rule::enum(SupportPriority::class)],
            'support_team_id' => ['nullable', 'integer', Rule::exists('support_teams', 'id')],
            'support_category_id' => ['nullable', 'integer', Rule::exists('support_categories', 'id')],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('client_id')],
        ]);

        $ticket->forceFill([
            'priority' => $validated['priority'],
            'support_team_id' => $validated['support_team_id'] ?? null,
            'support_category_id' => $validated['support_category_id'] ?? null,
        ])->save();

        $this->desk->assign($ticket, isset($validated['assigned_to'])
            ? User::query()->find($validated['assigned_to'])
            : null);

        if ($ticket->status->value !== $validated['status']) {
            $this->desk->transition($ticket, SupportTicketStatus::from($validated['status']), $request->user());
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$ticket->reference} updated.",
        ]);
    }

    /**
     * Stream one attachment to an agent.
     */
    public function downloadAttachment(SupportTicket $ticket, SupportTicketAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $ticket);

        abort_unless($attachment->support_ticket_id === $ticket->id, 404);
        abort_unless($this->attachments->exists($attachment), 404);

        return Storage::disk($attachment->storage_disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }

    /**
     * The teams, as select options.
     *
     * @return list<array{value: string, label: string}>
     */
    private function teamOptions(): array
    {
        return SupportTeam::query()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (SupportTeam $team): array => [
                'value' => (string) $team->id,
                'label' => $team->name,
            ])
            ->all();
    }

    /**
     * The categories, as select options for the type of conversation.
     *
     * @return list<array{value: string, label: string}>
     */
    private function categoryOptions(): array
    {
        return SupportCategory::query()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (SupportCategory $category): array => [
                'value' => (string) $category->id,
                'label' => $category->name,
            ])
            ->all();
    }

    /**
     * The platform users who may be given the conversation.
     *
     * @return list<array{value: string, label: string}>
     */
    private function agentOptions(): array
    {
        return User::query()
            ->whereNull('client_id')
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['super-admin', 'compliance-officer', 'finance-officer']))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'value' => (string) $user->id,
                'label' => $user->name,
            ])
            ->all();
    }
}
