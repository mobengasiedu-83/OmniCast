<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PresentsTemplates;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How often each template is used, for the platform's own reporting.
 *
 * Every campaign saved from a template bumps the template's `usage_count`, so
 * this screen answers the question the catalogue exists to answer: which
 * starting points clients actually reach for.
 */
class TemplateUsageController extends Controller
{
    use PresentsTemplates;

    /**
     * List templates by how often they were used in a campaign.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MessageTemplate::class);

        $user = $request->user();

        $filters = [
            'search' => $request->string('search')->toString(),
            'channel' => $request->string('channel')->toString(),
            'ownership' => $request->string('ownership')->toString(),
        ];

        $templates = MessageTemplate::query()
            ->with(['category:id,name,slug', 'client:id,name'])
            ->withCount('campaigns')
            ->withMax('campaigns', 'created_at')
            ->when($filters['channel'] !== '', fn (Builder $query) => $query->where('channel', $filters['channel']))
            ->when(
                $filters['ownership'] === 'system',
                fn (Builder $query) => $query->whereNull('client_id'),
            )
            ->when(
                $filters['ownership'] === 'tenant',
                fn (Builder $query) => $query->whereNotNull('client_id'),
            )
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $needle = '%'.Str::lower($filters['search']).'%';

                $query->where(function (Builder $query) use ($needle): void {
                    $query->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereHas(
                            'client',
                            fn (Builder $client) => $client->whereRaw('LOWER(name) LIKE ?', [$needle]),
                        );
                });
            })
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(function (MessageTemplate $template) use ($user): array {
                $lastUsed = $template->getAttribute('campaigns_max_created_at');

                return [
                    ...$this->templateRow($template, $user),
                    'ownership' => $template->isSystem() ? 'System' : 'Client',
                    'last_used_at' => $lastUsed === null ? null : Carbon::parse($lastUsed)->toIso8601String(),
                ];
            });

        return Inertia::render('admin/templates/usage', [
            'templates' => $templates,
            'filters' => $filters,
            'channels' => $this->templateChannelOptions(),
            'ownerships' => [
                ['value' => 'system', 'label' => 'System templates'],
                ['value' => 'tenant', 'label' => 'Client templates'],
            ],
            'stats' => [
                'uses' => (int) MessageTemplate::query()->sum('usage_count'),
                'templates_used' => MessageTemplate::query()->where('usage_count', '>', 0)->count(),
                'campaigns' => Campaign::query()->whereNotNull('message_template_id')->count(),
            ],
        ]);
    }
}
