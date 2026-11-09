<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementSeverity;
use App\Enums\AnnouncementStatus;
use App\Enums\NotificationCategory;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\Client;
use App\Services\Notifications\AnnouncementDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform messaging centre.
 *
 * One screen composes an announcement, keeps the drafts and lists what has gone
 * out with how much of the audience opened it. Delivery is queued by
 * {@see AnnouncementDispatcher}; nothing is written to an inbox in this request.
 */
class AnnouncementController extends Controller
{
    /**
     * List the announcements and the options the composer needs.
     */
    public function index(Request $request): Response
    {
        $status = AnnouncementStatus::tryFrom($request->string('status')->toString());

        $announcements = Announcement::query()
            ->with(['client:id,name', 'author:id,name'])
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->latestFirst()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Announcement $announcement): array => $this->payload($announcement));

        return Inertia::render('admin/announcements/index', [
            'announcements' => $announcements,
            'filters' => ['status' => $status?->value],
            'stats' => [
                'drafts' => Announcement::query()->where('status', AnnouncementStatus::Draft->value)->count(),
                'sent' => Announcement::query()->where('status', AnnouncementStatus::Sent->value)->count(),
                'recipients' => (int) Announcement::query()->sum('recipient_count'),
                'read' => (int) Announcement::query()->sum('read_count'),
            ],
            'audiences' => AnnouncementAudience::catalogue(),
            'categories' => NotificationCategory::catalogue(),
            'severities' => array_map(fn (AnnouncementSeverity $severity): array => [
                'value' => $severity->value,
                'label' => $severity->label(),
                'tone' => $severity->tone(),
            ], AnnouncementSeverity::cases()),
            'roles' => array_map(fn (Role $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ], Role::cases()),
            'clients' => Client::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Save a draft, or queue it at once when the composer asks to send now.
     */
    public function store(AnnouncementRequest $request, AnnouncementDispatcher $dispatcher): RedirectResponse
    {
        $audience = $request->audience() ?? AnnouncementAudience::Everyone;

        $announcement = Announcement::query()->create([
            'title' => (string) $request->string('title'),
            'body' => (string) $request->string('body'),
            'category' => (string) $request->string('category'),
            'audience' => $audience->value,
            'client_id' => $audience === AnnouncementAudience::Client ? $request->integer('client_id') : null,
            'role' => $audience === AnnouncementAudience::Role ? (string) $request->string('role') : null,
            'severity' => (string) $request->string('severity'),
            'send_email' => $request->boolean('send_email'),
            'status' => AnnouncementStatus::Draft,
            'created_by' => $request->user()?->id,
        ]);

        if ($request->sendNow()) {
            $dispatcher->queue($announcement);

            return back()->with('toast', [
                'type' => 'success',
                'message' => 'The announcement is queued and is being delivered now.',
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'The announcement was saved as a draft.',
        ]);
    }

    /**
     * Deliver a draft that was saved earlier.
     */
    public function send(
        Request $request,
        Announcement $announcement,
        AnnouncementDispatcher $dispatcher,
    ): RedirectResponse {
        if ($announcement->isSent()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'That announcement has already been delivered.',
            ]);
        }

        $dispatcher->queue($announcement);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'The announcement is queued and is being delivered now.',
        ]);
    }

    /**
     * Delete an announcement and its receipts.
     */
    public function destroy(Announcement $announcement): RedirectResponse
    {
        $title = $announcement->title;
        $announcement->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => "\"{$title}\" was deleted.",
        ]);
    }

    /**
     * The screen shape of one announcement.
     *
     * @return array<string, mixed>
     */
    protected function payload(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'category' => $announcement->category->value,
            'category_label' => $announcement->category->label(),
            'audience' => $announcement->audience->value,
            'audience_label' => $announcement->audienceLabel(),
            'severity' => $announcement->severity->value,
            'severity_label' => $announcement->severity->label(),
            'severity_tone' => $announcement->severity->tone(),
            'send_email' => $announcement->send_email,
            'status' => $announcement->status->value,
            'status_label' => $announcement->status->label(),
            'status_tone' => $announcement->status->tone(),
            'recipient_count' => $announcement->recipient_count,
            'read_count' => $announcement->read_count,
            'client' => $announcement->client === null ? null : [
                'id' => $announcement->client->id,
                'name' => $announcement->client->name,
            ],
            'author' => $announcement->author?->name,
            'created_at' => $announcement->created_at?->toIso8601String(),
            'sent_at' => $announcement->sent_at?->toIso8601String(),
        ];
    }
}
