<?php

namespace App\Http\Controllers;

use App\Enums\TemplateStatus;
use App\Http\Controllers\Concerns\PresentsTemplates;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Http\Requests\Template\TemplateRequest;
use App\Models\Client;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\ModuleAvailability;
use App\Services\TemplateLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant template library: browse the platform catalogue, keep the tenant's
 * own copies in order, and open any starting point in the campaign editor.
 */
class TemplateController extends Controller
{
    use PresentsTemplates, ResolvesTenantClient;

    public function __construct(
        protected TemplateLibrary $library,
        protected ModuleAvailability $modules,
    ) {}

    /**
     * List the templates the signed-in user may use for a campaign.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MessageTemplate::class);

        $user = $request->user();

        $filters = [
            'search' => $request->string('search')->toString(),
            'channel' => $request->string('channel')->toString(),
            'category' => $request->string('category')->toString(),
            'favourites' => $request->boolean('favourites'),
            'mine' => $request->boolean('mine'),
        ];

        $templates = $this->filtered($request, $filters)
            ->orderByRaw('client_id is null')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (MessageTemplate $template): array => $this->templateRow($template, $user));

        return Inertia::render('templates/index', [
            'templates' => $templates,
            'filters' => $filters,
            'categories' => $this->categoryOptions($request),
            'channels' => $this->channelOptions($user->client),
            'statuses' => $this->statusOptions(),
            'counts' => $this->channelCounts($user),
            'stats' => [
                'total' => $this->library->query($user)->count(),
                'mine' => $this->library->query($user)->whereNotNull('client_id')->count(),
                'favourites' => $this->library->query($user)
                    ->whereHas('favouritedBy', fn (Builder $query) => $query->where('users.id', $user->id))
                    ->count(),
                'awaiting_review' => $this->library->query($user)
                    ->where('status', TemplateStatus::Pending->value)
                    ->count(),
            ],
            'can' => [
                'create' => Gate::allows('create', MessageTemplate::class),
                'manage_categories' => $user->can('template-categories.manage'),
            ],
        ]);
    }

    /**
     * Show the editor for a new template.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', MessageTemplate::class);

        return Inertia::render('templates/create', [
            ...$this->formOptions($request),
            'template' => null,
        ]);
    }

    /**
     * Persist a new template in the tenant's library.
     */
    public function store(TemplateRequest $request): RedirectResponse
    {
        Gate::authorize('create', MessageTemplate::class);

        $status = $request->requestedStatus();

        $template = MessageTemplate::query()->create([
            ...$request->templateAttributes(),
            'status' => $status->value,
            'created_by' => $request->user()->id,
        ]);

        return to_route('templates.index')->with('toast', [
            'type' => 'success',
            'message' => "Template \"{$template->name}\" saved as {$status->label()}.",
        ]);
    }

    /**
     * Show the editor for a template the tenant owns.
     */
    public function edit(Request $request, MessageTemplate $template): Response
    {
        Gate::authorize('update', $template);

        return Inertia::render('templates/edit', [
            ...$this->formOptions($request),
            'template' => $this->templateRow($template, $request->user()),
        ]);
    }

    /**
     * Save the changes to a template the tenant owns.
     */
    public function update(TemplateRequest $request, MessageTemplate $template): RedirectResponse
    {
        Gate::authorize('update', $template);

        $status = $request->requestedStatus();

        $template->update([
            ...$request->templateAttributes(),
            'status' => $status->value,
            'rejection_reason' => $status === TemplateStatus::Rejected ? $template->rejection_reason : null,
        ]);

        return to_route('templates.index')->with('toast', [
            'type' => 'success',
            'message' => "Template \"{$template->name}\" saved as {$status->label()}.",
        ]);
    }

    /**
     * Remove a tenant template from the library.
     */
    public function destroy(MessageTemplate $template): RedirectResponse
    {
        Gate::authorize('delete', $template);

        $template->delete();

        return to_route('templates.index')->with('toast', [
            'type' => 'success',
            'message' => "Template \"{$template->name}\" was removed from the library.",
        ]);
    }

    /**
     * Copy a catalogue template into the tenant so it can be edited freely.
     */
    public function customise(Request $request, MessageTemplate $template): RedirectResponse
    {
        Gate::authorize('view', $template);

        $copy = $this->library->customise($template, $request->user());

        return to_route('templates.edit', $copy)->with('toast', [
            'type' => 'success',
            'message' => "A copy of \"{$template->name}\" is now yours to edit.",
        ]);
    }

    /**
     * Add or remove a template from the operator's shortlist.
     */
    public function favourite(Request $request, MessageTemplate $template): RedirectResponse
    {
        Gate::authorize('view', $template);

        $favourite = $this->library->toggleFavourite($template, $request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => $favourite
                ? "\"{$template->name}\" was added to your favourites."
                : "\"{$template->name}\" was removed from your favourites.",
        ]);
    }

    /**
     * The library query with the filters of the current page applied.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<MessageTemplate>
     */
    protected function filtered(Request $request, array $filters): Builder
    {
        $user = $request->user();

        return $this->library->query($user)
            ->with([
                'category:id,name,slug',
                'creator:id,name',
                'client:id,name',
                'favouritedBy' => fn ($query) => $query->where('users.id', $user->id),
            ])
            ->withCount('campaigns')
            ->when($filters['channel'] !== '', fn (Builder $query) => $query->where('channel', $filters['channel']))
            ->when($filters['category'] !== '', fn (Builder $query) => $query->whereHas(
                'category',
                fn (Builder $category) => $category->where('slug', $filters['category']),
            ))
            ->when($filters['mine'], fn (Builder $query) => $query->whereNotNull('client_id'))
            ->when($filters['favourites'], fn (Builder $query) => $query->whereHas(
                'favouritedBy',
                fn (Builder $favourites) => $favourites->where('users.id', $user->id),
            ))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                /**
                 * Template copy keeps the case the author typed, so the search
                 * folds both sides instead of trusting the collation.
                 */
                $needle = '%'.Str::lower($filters['search']).'%';

                $query->where(function (Builder $query) use ($needle): void {
                    $query->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(body) LIKE ?', [$needle])
                        ->orWhereRaw("LOWER(COALESCE(description, '')) LIKE ?", [$needle]);
                });
            });
    }

    /**
     * The categories the account may file a template under.
     *
     * @return list<array<string, mixed>>
     */
    protected function categoryOptions(Request $request): array
    {
        return $this->templateCategoryOptions($request->user()->client_id);
    }

    /**
     * The options shared by the create and edit forms.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(Request $request): array
    {
        return [
            'categories' => $this->categoryOptions($request),
            'channels' => $this->channelOptions($this->tenantClient($request)),
            'statuses' => $this->statusOptions(),
            'clients' => $this->selectableClients($request),
            'tenantClientId' => $this->tenantClient($request)?->id,
        ];
    }

    /**
     * The channels this tenant can still write a template for.
     *
     * @return list<array<string, string>>
     */
    protected function channelOptions(?Client $client = null): array
    {
        return $this->modules->channelOptions($client);
    }

    /**
     * @return list<array<string, string>>
     */
    protected function statusOptions(): array
    {
        return array_map(
            fn (TemplateStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            TemplateStatus::cases(),
        );
    }

    /**
     * How many templates each channel tab holds for this account.
     *
     * @return array<string, int>
     */
    protected function channelCounts(User $user): array
    {
        $counts = ['all' => $this->library->query($user)->count()];

        foreach ($this->channelOptions($user->client) as $channel) {
            $counts[$channel['value']] = $this->library->query($user)
                ->where('channel', $channel['value'])
                ->count();
        }

        return $counts;
    }
}
