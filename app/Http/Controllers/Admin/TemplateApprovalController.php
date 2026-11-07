<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\TemplateStatus;
use App\Http\Controllers\Concerns\PresentsTemplates;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The review queue for tenant templates.
 *
 * A client submits a template for review when it wants the platform to vouch
 * for the copy before it is used in a campaign; approving it marks the template
 * active, rejecting it sends it back with a reason the author can act on.
 */
class TemplateApprovalController extends Controller
{
    use PresentsTemplates;

    /**
     * List the tenant templates waiting for a decision.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MessageTemplate::class);

        $user = $request->user();

        $filters = [
            'search' => $request->string('search')->toString(),
            'channel' => $request->string('channel')->toString(),
            'client_id' => $request->integer('client_id'),
        ];

        $templates = MessageTemplate::query()
            ->whereNotNull('client_id')
            ->where('status', TemplateStatus::Pending->value)
            ->with(['category:id,name,slug', 'creator:id,name', 'client:id,name'])
            ->withCount('campaigns')
            ->when($filters['channel'] !== '', fn (Builder $query) => $query->where('channel', $filters['channel']))
            ->when($filters['client_id'] > 0, fn (Builder $query) => $query->where('client_id', $filters['client_id']))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $needle = '%'.Str::lower($filters['search']).'%';

                $query->where(function (Builder $query) use ($needle): void {
                    $query->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(body) LIKE ?', [$needle]);
                });
            })
            ->orderBy('updated_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (MessageTemplate $template): array => $this->templateRow($template, $user));

        return Inertia::render('admin/templates/approvals', [
            'templates' => $templates,
            'filters' => $filters,
            'channels' => $this->templateChannelOptions(),
            'clients' => Client::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
                ->all(),
            'stats' => [
                'pending' => MessageTemplate::query()
                    ->whereNotNull('client_id')
                    ->where('status', TemplateStatus::Pending->value)
                    ->count(),
                'approved_today' => MessageTemplate::query()
                    ->whereNotNull('client_id')
                    ->where('status', TemplateStatus::Active->value)
                    ->where('approved_at', '>=', now()->startOfDay())
                    ->count(),
                'rejected' => MessageTemplate::query()
                    ->whereNotNull('client_id')
                    ->where('status', TemplateStatus::Rejected->value)
                    ->count(),
            ],
            'can' => [
                'approve' => $user->can(Permission::ApproveTemplates->value),
            ],
        ]);
    }

    /**
     * Publish a template the tenant submitted.
     */
    public function approve(Request $request, MessageTemplate $template): RedirectResponse
    {
        Gate::authorize('approve', $template);

        $template->update([
            'status' => TemplateStatus::Active->value,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "\"{$template->name}\" was approved and is now active for its client.",
        ]);
    }

    /**
     * Send a template back to its author with a reason.
     */
    public function reject(Request $request, MessageTemplate $template): RedirectResponse
    {
        Gate::authorize('approve', $template);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $template->update([
            'status' => TemplateStatus::Rejected->value,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $validated['reason'],
        ]);

        return back()->with('toast', [
            'type' => 'warning',
            'message' => "\"{$template->name}\" was returned to its author.",
        ]);
    }
}
