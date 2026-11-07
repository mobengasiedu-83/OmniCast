<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TemplateStatus;
use App\Http\Controllers\Concerns\PresentsTemplates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Template\SystemTemplateRequest;
use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform template catalogue: the starting points every client browses.
 *
 * Catalogue rows belong to the platform (`client_id` is null); a client that
 * wants to change one copies it into its own tenant first, so an edit here
 * reaches everybody while a tenant edit reaches nobody else.
 */
class TemplateController extends Controller
{
    use PresentsTemplates;

    /**
     * List the catalogue with its filters applied.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MessageTemplate::class);

        $user = $request->user();

        $filters = [
            'search' => $request->string('search')->toString(),
            'channel' => $request->string('channel')->toString(),
            'status' => $request->string('status')->toString(),
            'category' => $request->string('category')->toString(),
        ];

        $templates = $this->filtered($filters)
            ->with(['category:id,name,slug', 'creator:id,name'])
            ->withCount('campaigns')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MessageTemplate $template): array => $this->templateRow($template, $user));

        return Inertia::render('admin/templates/index', [
            'templates' => $templates,
            'filters' => $filters,
            'categories' => $this->templateCategoryOptions(null),
            'channels' => $this->templateChannelOptions(),
            'statuses' => $this->templateStatusOptions(),
            'stats' => [
                'total' => MessageTemplate::query()->whereNull('client_id')->count(),
                'active' => MessageTemplate::query()
                    ->whereNull('client_id')
                    ->where('status', TemplateStatus::Active->value)
                    ->count(),
                'archived' => MessageTemplate::query()
                    ->whereNull('client_id')
                    ->where('status', TemplateStatus::Archived->value)
                    ->count(),
                'awaiting_review' => MessageTemplate::query()
                    ->whereNotNull('client_id')
                    ->where('status', TemplateStatus::Pending->value)
                    ->count(),
            ],
            'can' => [
                'create' => Gate::allows('create', MessageTemplate::class),
                'approve' => $user->can('templates.approve'),
            ],
        ]);
    }

    /**
     * Show the editor for a new catalogue template.
     */
    public function create(): Response
    {
        Gate::authorize('create', MessageTemplate::class);

        return Inertia::render('admin/templates/create', [
            ...$this->formOptions(),
            'template' => null,
        ]);
    }

    /**
     * Add a template to the catalogue.
     */
    public function store(SystemTemplateRequest $request): RedirectResponse
    {
        Gate::authorize('create', MessageTemplate::class);

        $status = $request->requestedStatus();

        $template = MessageTemplate::query()->create([
            ...$request->templateAttributes(),
            'status' => $status->value,
            'created_by' => $request->user()->id,
            ...$this->reviewStamp($status, $request),
        ]);

        return to_route('admin.templates.index')->with('toast', [
            'type' => 'success',
            'message' => "Catalogue template \"{$template->name}\" saved as {$status->label()}.",
        ]);
    }

    /**
     * Show the editor for a catalogue template.
     */
    public function edit(Request $request, MessageTemplate $template): Response
    {
        Gate::authorize('manageCatalogue', $template);

        return Inertia::render('admin/templates/edit', [
            ...$this->formOptions(),
            'template' => $this->templateRow($template, $request->user()),
        ]);
    }

    /**
     * Save the changes to a catalogue template.
     */
    public function update(SystemTemplateRequest $request, MessageTemplate $template): RedirectResponse
    {
        Gate::authorize('manageCatalogue', $template);

        $status = $request->requestedStatus();

        $template->update([
            ...$request->templateAttributes(),
            'status' => $status->value,
            ...$this->reviewStamp($status, $request),
        ]);

        return to_route('admin.templates.index')->with('toast', [
            'type' => 'success',
            'message' => "Catalogue template \"{$template->name}\" saved as {$status->label()}.",
        ]);
    }

    /**
     * Take a template out of the library without losing its history.
     */
    public function archive(MessageTemplate $template): RedirectResponse
    {
        Gate::authorize('manageCatalogue', $template);

        $template->update(['status' => TemplateStatus::Archived->value]);

        return back()->with('toast', [
            'type' => 'warning',
            'message' => "\"{$template->name}\" is archived and is no longer offered to clients.",
        ]);
    }

    /**
     * Remove a catalogue template for good.
     */
    public function destroy(MessageTemplate $template): RedirectResponse
    {
        Gate::authorize('manageCatalogue', $template);

        $name = $template->name;
        $template->delete();

        return to_route('admin.templates.index')->with('toast', [
            'type' => 'success',
            'message' => "Catalogue template \"{$name}\" was removed.",
        ]);
    }

    /**
     * The options shared by the create and edit screens.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'categories' => $this->templateCategoryOptions(null),
            'channels' => $this->templateChannelOptions(),
            'statuses' => $this->templateStatusOptions(),
        ];
    }

    /**
     * A catalogue template the platform publishes is already reviewed.
     *
     * @return array<string, mixed>
     */
    protected function reviewStamp(TemplateStatus $status, Request $request): array
    {
        if ($status !== TemplateStatus::Active) {
            return ['rejection_reason' => null];
        }

        return [
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ];
    }

    /**
     * The catalogue query with the filters of the current page applied.
     *
     * @param  array<string, string>  $filters
     * @return Builder<MessageTemplate>
     */
    protected function filtered(array $filters): Builder
    {
        return MessageTemplate::query()
            ->whereNull('client_id')
            ->when($filters['channel'] !== '', fn (Builder $query) => $query->where('channel', $filters['channel']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['category'] !== '', fn (Builder $query) => $query->whereHas(
                'category',
                fn (Builder $category) => $category->where('slug', $filters['category']),
            ))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $needle = '%'.Str::lower($filters['search']).'%';

                $query->where(function (Builder $query) use ($needle): void {
                    $query->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(body) LIKE ?', [$needle])
                        ->orWhereRaw("LOWER(COALESCE(description, '')) LIKE ?", [$needle]);
                });
            });
    }
}
