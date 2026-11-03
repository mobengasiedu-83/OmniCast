<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LoginEvent;
use App\Http\Controllers\Controller;
use App\Models\LoginAudit;
use App\Models\User;
use App\Services\SessionMonitor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The privileged audit trail of every authentication attempt.
 */
class LoginAuditController extends Controller
{
    /**
     * List the recorded authentication events.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'event' => $request->string('event')->toString(),
        ];

        /** @var LengthAwarePaginator<int, array<string, mixed>> $audits */
        $audits = LoginAudit::query()
            ->with(['user:id,name,email', 'client:id,name'])
            ->when($filters['event'] !== '', fn ($query) => $query->where('event', $filters['event']))
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $term = '%'.$filters['search'].'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('email', 'like', $term)
                        ->orWhere('ip_address', 'like', $term)
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', $term));
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (LoginAudit $audit): array => [
                'id' => $audit->id,
                'email' => $audit->email,
                'user' => $audit->user?->name,
                'client' => $audit->client?->name,
                'event' => $audit->event->value,
                'event_label' => $audit->event->label(),
                'event_tone' => $audit->event->tone(),
                'ip_address' => $audit->ip_address,
                'device' => SessionMonitor::describeDevice($audit->user_agent),
                'context' => $audit->context,
                'created_at' => $audit->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users/login-audit', [
            'audits' => $audits,
            'filters' => $filters,
            'events' => array_map(
                fn (LoginEvent $event): array => ['value' => $event->value, 'label' => $event->label()],
                LoginEvent::cases(),
            ),
            'stats' => [
                'today' => LoginAudit::query()->where('created_at', '>=', now()->startOfDay())->count(),
                'failures_today' => LoginAudit::query()
                    ->where('created_at', '>=', now()->startOfDay())
                    ->whereIn('event', [
                        LoginEvent::LoginFailed->value,
                        LoginEvent::LoginBlocked->value,
                        LoginEvent::Lockout->value,
                    ])
                    ->count(),
                'blocked' => LoginAudit::query()
                    ->where('event', LoginEvent::LoginBlocked->value)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'unique_ips' => LoginAudit::query()
                    ->where('created_at', '>=', now()->subDay())
                    ->distinct()
                    ->count('ip_address'),
            ],
        ]);
    }
}
