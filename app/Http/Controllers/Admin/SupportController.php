<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\SupportTicketType;
use App\Http\Controllers\Controller;
use App\Models\SupportArticle;
use App\Models\SupportCategory;
use App\Models\SupportTeam;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The two registers an operator reads, and the desk's own settings.
 *
 * Enquiries and tickets share a table but not a screen: an enquiry is answered
 * once and forgotten, while a ticket has an owner and a status the client is
 * watching. Reports read both, because "how many people wrote to us" is one
 * question.
 */
class SupportController extends Controller
{
    public function __construct(protected SupportSettings $settings) {}

    /**
     * The public and sales enquiries waiting to be answered.
     */
    public function enquiries(Request $request): Response
    {
        $filters = $this->filters($request, ['team', 'status', 'search']);

        $enquiries = SupportTicket::query()
            ->ofType(SupportTicketType::Enquiry)
            ->when($filters['team'] !== '', fn (Builder $query) => $query->where('support_team_id', (int) $filters['team']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->search($filters['search']))
            ->with(['category:id,name', 'team:id,name'])
            ->queueOrder()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SupportTicket $ticket): array => $this->row($ticket, 'admin.support.enquiries.show'));

        return Inertia::render('admin/support/enquiries', [
            'enquiries' => $enquiries,
            'filters' => $filters,
            'teams' => $this->teamOptions(),
            'statuses' => SupportTicketStatus::catalogue(),
            'stats' => [
                'new' => SupportTicket::query()->ofType(SupportTicketType::Enquiry)->where('status', SupportTicketStatus::New->value)->count(),
                'open' => SupportTicket::query()->ofType(SupportTicketType::Enquiry)->openConversations()->count(),
                'today' => SupportTicket::query()->ofType(SupportTicketType::Enquiry)->where('created_at', '>=', now()->startOfDay())->count(),
                'resolved' => SupportTicket::query()->ofType(SupportTicketType::Enquiry)
                    ->whereIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value])
                    ->count(),
            ],
        ]);
    }

    /**
     * The client tickets the team owns.
     */
    public function tickets(Request $request): Response
    {
        $filters = $this->filters($request, ['team', 'status', 'priority', 'assignee', 'search']);

        $tickets = SupportTicket::query()
            ->ofType(SupportTicketType::Ticket)
            ->when($filters['team'] !== '', fn (Builder $query) => $query->where('support_team_id', (int) $filters['team']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['priority'] !== '', fn (Builder $query) => $query->where('priority', $filters['priority']))
            ->when($filters['assignee'] === 'unassigned', fn (Builder $query) => $query->whereNull('assigned_to'))
            ->when($filters['assignee'] !== '' && $filters['assignee'] !== 'unassigned', fn (Builder $query) => $query->where('assigned_to', (int) $filters['assignee']))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->search($filters['search']))
            ->with(['category:id,name', 'team:id,name', 'assignee:id,name', 'client:id,name'])
            ->queueOrder()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SupportTicket $ticket): array => [
                ...$this->row($ticket, 'admin.support.tickets.show'),
                'client' => $ticket->client?->name,
                'assignee' => $ticket->assignee?->name,
            ]);

        return Inertia::render('admin/support/tickets', [
            'tickets' => $tickets,
            'filters' => $filters,
            'teams' => $this->teamOptions(),
            'statuses' => SupportTicketStatus::catalogue(),
            'priorities' => SupportPriority::catalogue(),
            'agents' => $this->agentOptions(),
            'stats' => [
                'open' => SupportTicket::query()->ofType(SupportTicketType::Ticket)->openConversations()->count(),
                'unassigned' => SupportTicket::query()->ofType(SupportTicketType::Ticket)->openConversations()->whereNull('assigned_to')->count(),
                'urgent' => SupportTicket::query()->ofType(SupportTicketType::Ticket)->openConversations()->where('priority', SupportPriority::Urgent->value)->count(),
                'resolved_30' => SupportTicket::query()->ofType(SupportTicketType::Ticket)
                    ->whereIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value])
                    ->where('resolved_at', '>=', now()->subDays(30))
                    ->count(),
            ],
        ]);
    }

    /**
     * Where visitors reach the team, and what the desk sends them.
     */
    public function notifications(): Response
    {
        return Inertia::render('admin/support/notifications', [
            'settings' => $this->settings->all(),
        ]);
    }

    /**
     * Save the desk's contact details and notification switches.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_email' => ['nullable', 'string', 'email', 'max:160'],
            'support_email' => ['nullable', 'string', 'email', 'max:160'],
            'sales_email' => ['nullable', 'string', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'office_address' => ['nullable', 'string', 'max:500'],
            'opening_hours' => ['nullable', 'string', 'max:200'],
            'response_time' => ['nullable', 'string', 'max:200'],
            'status_url' => ['nullable', 'string', 'url', 'max:255'],
            'acknowledge_enquiries' => ['required', 'boolean'],
            'notify_on_status_change' => ['required', 'boolean'],
            'notify_team' => ['required', 'boolean'],
        ]);

        $this->settings->update($validated, $request->user()->id);

        activity()
            ->causedBy($request->user())
            ->withProperties(['group' => SupportSettings::GROUP])
            ->log('updated the support desk settings');

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Support desk settings saved.',
        ]);
    }

    /**
     * Volumes, response times and the library's most-read articles.
     */
    public function reports(): Response
    {
        $since = Carbon::now()->subDays(30);

        $byStatus = SupportTicket::query()
            ->selectRaw('status, count(*) as total')
            ->where('created_at', '>=', $since)
            ->groupBy('status')
            ->pluck('total', 'status');

        $byTeam = SupportTicket::query()
            ->selectRaw('support_team_id, count(*) as total')
            ->where('created_at', '>=', $since)
            ->groupBy('support_team_id')
            ->pluck('total', 'support_team_id');

        $byCategory = SupportTicket::query()
            ->selectRaw('support_category_id, count(*) as total')
            ->where('created_at', '>=', $since)
            ->groupBy('support_category_id')
            ->pluck('total', 'support_category_id');

        $answered = SupportTicket::query()->whereNotNull('first_responded_at')->where('created_at', '>=', $since)->get(['created_at', 'first_responded_at', 'resolved_at']);
        $resolved = $answered->filter(fn (SupportTicket $ticket): bool => $ticket->resolved_at !== null);

        return Inertia::render('admin/support/reports', [
            'totals' => [
                'enquiries' => SupportTicket::query()->ofType(SupportTicketType::Enquiry)->count(),
                'tickets' => SupportTicket::query()->ofType(SupportTicketType::Ticket)->count(),
                'open' => SupportTicket::query()->openConversations()->count(),
                'unanswered' => SupportTicket::query()->openConversations()->whereNull('first_responded_at')->count(),
            ],
            'window' => [
                'days' => 30,
                'first_response_hours' => $this->averageHours($answered, 'first_responded_at'),
                'resolution_hours' => $this->averageHours($resolved, 'resolved_at'),
            ],
            'by_status' => array_map(fn (SupportTicketStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'total' => (int) ($byStatus[$status->value] ?? 0),
            ], SupportTicketStatus::cases()),
            'by_team' => SupportTeam::query()->ordered()->get()->map(fn (SupportTeam $team): array => [
                'name' => $team->name,
                'total' => (int) ($byTeam[$team->id] ?? 0),
            ])->all(),
            'by_category' => SupportCategory::query()->ordered()->get()->map(fn (SupportCategory $category): array => [
                'name' => $category->name,
                'total' => (int) ($byCategory[$category->id] ?? 0),
            ])->all(),
            'articles' => SupportArticle::query()
                ->orderByDesc('views')
                ->limit(5)
                ->get(['title', 'slug', 'views', 'is_published'])
                ->map(fn (SupportArticle $article): array => [
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'views' => $article->views,
                    'is_published' => $article->is_published,
                ])
                ->all(),
        ]);
    }

    /**
     * The filters the screen and its links share.
     *
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    protected function filters(Request $request, array $keys): array
    {
        $filters = [];

        foreach ($keys as $key) {
            $filters[$key] = $request->string($key)->toString();
        }

        return $filters;
    }

    /**
     * The columns a register row prints.
     *
     * @return array<string, mixed>
     */
    protected function row(SupportTicket $ticket, string $routeName): array
    {
        return [
            'id' => $ticket->id,
            'reference' => $ticket->reference,
            'subject' => $ticket->subject,
            'requester' => $ticket->name,
            'email' => $ticket->email,
            'company' => $ticket->company,
            'category' => $ticket->category?->name,
            'team' => $ticket->team?->name,
            'priority' => $ticket->priority->value,
            'priority_label' => $ticket->priority->label(),
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'waiting_since' => $ticket->waitingSince(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'url' => route($routeName, $ticket->reference),
        ];
    }

    /**
     * The teams, as select options.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function teamOptions(): array
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
     * The platform users who may be given a conversation.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function agentOptions(): array
    {
        return User::query()
            ->whereNull('client_id')
            ->where('is_active', true)
            ->permission(Permission::ManageSupport->value)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'value' => (string) $user->id,
                'label' => $user->name,
            ])
            ->all();
    }

    /**
     * The mean time to an attribute, in hours, or null when nothing qualifies.
     *
     * @param  Collection<int, SupportTicket>  $tickets
     */
    protected function averageHours($tickets, string $attribute): ?float
    {
        $hours = $tickets
            ->filter(fn (SupportTicket $ticket): bool => $ticket->{$attribute} !== null && $ticket->created_at !== null)
            ->map(fn (SupportTicket $ticket): float => (float) $ticket->created_at->diffInMinutes($ticket->{$attribute}) / 60);

        return $hours->isEmpty() ? null : round((float) $hours->avg(), 1);
    }
}
