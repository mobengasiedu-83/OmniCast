<?php

namespace App\Http\Controllers\Support;

use App\Enums\SupportTicketStatus;
use App\Enums\SupportTicketType;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\Support\SupportLibrary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in support overview.
 *
 * It answers the three questions a client opens the menu with: what is open, how
 * do I reach a person, and is there an article that already answers this.
 */
class SupportCentreController extends Controller
{
    public function __construct(protected SupportLibrary $library) {}

    /**
     * Show the overview.
     */
    public function index(Request $request): Response
    {
        $clientId = $request->user()->client_id;

        $base = SupportTicket::query()
            ->ofType(SupportTicketType::Ticket)
            ->forClient($clientId);

        $tickets = (clone $base)
            ->with('category:id,name')
            ->latestFirst()
            ->limit(5)
            ->get()
            ->map(fn (SupportTicket $ticket): array => [
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'category' => $ticket->category?->name,
                'waiting_since' => $ticket->waitingSince(),
                'url' => route('support.tickets.show', $ticket->reference),
            ]);

        return Inertia::render('support/index', [
            'stats' => [
                'open' => (clone $base)->openConversations()->count(),
                'awaiting_customer' => (clone $base)->where('status', SupportTicketStatus::Pending->value)->count(),
                'total' => (clone $base)->count(),
                'resolved' => (clone $base)->whereIn('status', [
                    SupportTicketStatus::Resolved->value,
                    SupportTicketStatus::Closed->value,
                ])->count(),
            ],
            'tickets' => $tickets,
            'categories' => $this->library->categories(SupportTicketType::Ticket),
            'articles' => $this->library->featured(4),
            'contact' => $this->library->contact(),
        ]);
    }
}
