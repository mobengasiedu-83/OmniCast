<?php

namespace App\Http\Controllers\Support;

use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\SupportTicketType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreTicketRequest;
use App\Models\Campaign;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\WalletTransaction;
use App\Services\Support\SupportAttachmentStore;
use App\Services\Support\SupportDesk;
use App\Services\Support\SupportLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A client's own support tickets.
 *
 * Every route is scoped to the requester's own organisation by the ticket
 * policy, so a reference belonging to another tenant is refused rather than
 * rendered.
 */
class SupportTicketController extends Controller
{
    public function __construct(
        protected SupportDesk $desk,
        protected SupportLibrary $library,
        protected SupportAttachmentStore $attachments,
    ) {}

    /**
     * List the organisation's tickets.
     */
    public function index(Request $request): Response
    {
        $clientId = $request->user()->client_id;
        $status = $request->string('status')->toString();
        $search = $request->string('search')->toString();

        $tickets = SupportTicket::query()
            ->ofType(SupportTicketType::Ticket)
            ->forClient($clientId)
            ->when($status !== '' && in_array($status, SupportTicketStatus::values(), true), fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->latestFirst()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SupportTicket $ticket): array => $this->row($ticket));

        return Inertia::render('support/tickets/index', [
            'tickets' => $tickets,
            'filters' => ['status' => $status, 'search' => $search],
            'statuses' => SupportTicketStatus::catalogue(),
            'stats' => [
                'open' => $this->baseQuery($clientId)->openConversations()->count(),
                'total' => $this->baseQuery($clientId)->count(),
                'resolved' => $this->baseQuery($clientId)->whereIn('status', [
                    SupportTicketStatus::Resolved->value,
                    SupportTicketStatus::Closed->value,
                ])->count(),
            ],
        ]);
    }

    /**
     * Show one ticket and its thread.
     */
    public function show(SupportTicket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'category:id,name',
            'team:id,name',
            'assignee:id,name',
            'campaign:id,name,reference',
            'walletTransaction:id,description,amount,reference,created_at',
        ]);

        $messages = $ticket->messages()
            ->visibleToRequester()
            ->with(['user:id,name', 'attachments'])
            ->get()
            ->map(fn (SupportTicketMessage $message): array => [
                'id' => $message->id,
                'author_name' => $message->author_name,
                'from_agent' => $message->isFromAgent(),
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
                'attachments' => $message->attachments->map(fn (SupportTicketAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'size' => $attachment->humanSize(),
                    'url' => route('support.tickets.attachments.download', [$ticket->reference, $attachment->id]),
                ])->all(),
            ]);

        return Inertia::render('support/tickets/show', [
            'ticket' => [
                ...$this->row($ticket),
                'description' => $ticket->description,
                'category' => $ticket->category?->name,
                'team' => $ticket->team?->name,
                'assignee' => $ticket->assignee?->name,
                'first_responded_at' => $ticket->first_responded_at?->toIso8601String(),
                'resolved_at' => $ticket->resolved_at?->toIso8601String(),
                'closed_at' => $ticket->closed_at?->toIso8601String(),
                'campaign' => $ticket->campaign === null ? null : [
                    'name' => $ticket->campaign->name,
                    'reference' => $ticket->campaign->reference,
                ],
                'transaction' => $ticket->walletTransaction === null ? null : [
                    'description' => $ticket->walletTransaction->description,
                    'amount' => $ticket->walletTransaction->amount,
                    'reference' => $ticket->walletTransaction->reference,
                ],
            ],
            'messages' => $messages,
            'can_reply' => $ticket->isOpen(),
            'attachments' => $this->library->attachmentLimits(),
            'contact' => $this->library->contact(),
        ]);
    }

    /**
     * Show the form for opening a ticket.
     */
    public function create(Request $request): Response
    {
        $clientId = $request->user()->client_id;

        return Inertia::render('support/tickets/create', [
            'categories' => $this->library->categories(SupportTicketType::Ticket),
            'priorities' => SupportPriority::catalogue(),
            'campaigns' => Campaign::query()
                ->where('client_id', $clientId)
                ->latest()
                ->limit(50)
                ->get(['id', 'name', 'reference'])
                ->map(fn (Campaign $campaign): array => [
                    'value' => $campaign->id,
                    'label' => $campaign->name.' · '.$campaign->reference,
                ])
                ->all(),
            'transactions' => WalletTransaction::query()
                ->whereHas('wallet', fn (Builder $query) => $query->where('client_id', $clientId))
                ->latest()
                ->limit(50)
                ->get(['id', 'description', 'amount', 'reference', 'created_at'])
                ->map(fn (WalletTransaction $transaction): array => [
                    'value' => $transaction->id,
                    'label' => trim(($transaction->reference ?? 'Wallet').' · '.$transaction->description.' · '.$transaction->amount),
                ])
                ->all(),
            'attachments' => $this->library->attachmentLimits(),
            'contact' => $this->library->contact(),
        ]);
    }

    /**
     * Open the ticket and return the client to its thread.
     */
    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $category = SupportCategory::query()
            ->where('slug', $validated['category'])
            ->firstOrFail();

        $ticket = $this->desk->open([
            'type' => SupportTicketType::Ticket,
            'source' => 'client',
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'company' => $user->client?->name,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'category' => $category,
            'priority' => isset($validated['priority']) ? SupportPriority::from($validated['priority']) : null,
            'client_id' => $user->client_id,
            'user_id' => $user->id,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'wallet_transaction_id' => $validated['wallet_transaction_id'] ?? null,
            'consented' => true,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        $this->attachments->store(
            $ticket,
            $ticket->messages()->first(),
            $request->file('attachments', []) ?? [],
            $user,
        );

        return to_route('support.tickets.show', $ticket->reference)->with('toast', [
            'type' => 'success',
            'message' => "Ticket {$ticket->reference} is open. The {$category->name} team will answer it here.",
        ]);
    }

    /**
     * Add the client's reply to the thread.
     */
    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('reply', $ticket);

        $maxFiles = (int) config('support.attachments.max_files', 3);
        $maxKilobytes = (int) config('support.attachments.max_kilobytes', 5120);
        $mimes = implode(',', (array) config('support.attachments.mimes', []));

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:'.$maxFiles],
            'attachments.*' => ['file', 'mimes:'.$mimes, 'max:'.$maxKilobytes],
        ]);

        $message = $this->desk->reply($ticket, $validated['body'], $request->user());

        $this->attachments->store(
            $ticket,
            $message,
            $request->file('attachments', []) ?? [],
            $request->user(),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Your reply was added to the ticket.',
        ]);
    }

    /**
     * Close the ticket from the client's side.
     */
    public function close(Request $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('close', $ticket);

        $this->desk->transition($ticket, SupportTicketStatus::Closed, $request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Ticket {$ticket->reference} is closed. Reply again if the problem returns.",
        ]);
    }

    /**
     * Stream one attachment to the client who owns the ticket.
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
     * The columns a list row prints.
     *
     * @return array<string, mixed>
     */
    private function row(SupportTicket $ticket): array
    {
        return [
            'reference' => $ticket->reference,
            'subject' => $ticket->subject,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'priority' => $ticket->priority->value,
            'priority_label' => $ticket->priority->label(),
            'category' => $ticket->category?->name,
            'waiting_since' => $ticket->waitingSince(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'last_reply_at' => $ticket->last_reply_at?->toIso8601String(),
            'url' => route('support.tickets.show', $ticket->reference),
        ];
    }

    /**
     * The organisation's tickets, as a query the counters can reuse.
     *
     * @return Builder<SupportTicket>
     */
    private function baseQuery(?int $clientId): Builder
    {
        return SupportTicket::query()
            ->ofType(SupportTicketType::Ticket)
            ->forClient($clientId);
    }
}
