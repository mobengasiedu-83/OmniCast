<?php

namespace App\Http\Controllers;

use App\Enums\AutomationTrigger;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Http\Requests\Contact\ContactRequest;
use App\Http\Requests\Contact\ImportContactsRequest;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Tag;
use App\Services\Automation\AutomationEngine;
use App\Services\ContactDndService;
use App\Services\ContactEligibility;
use App\Services\ContactImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    use ResolvesTenantClient;

    /**
     * List the contact book of the client tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Contact::class);

        $search = $request->string('search')->toString();
        $contactListId = $request->integer('contact_list_id');
        $tagId = $request->integer('tag_id');
        $quality = $request->string('quality')->toString();
        $clientId = $this->tenantClient($request)?->id;

        $contacts = Contact::query()
            ->forUser($request->user())
            ->with([
                'contactLists:id,name',
                'tags:id,name',
            ])
            ->search($search)
            ->when($contactListId > 0, fn ($query) => $query->whereHas(
                'contactLists',
                fn ($query) => $query->whereKey($contactListId),
            ))
            ->when($tagId > 0, fn ($query) => $query->whereHas('tags', fn ($query) => $query->whereKey($tagId)))
            ->when($quality === 'missing_email', fn ($query) => $query->where(
                fn ($inner) => $inner->whereNull('email')->orWhere('email', ''),
            ))
            ->when($quality === 'invalid_email', fn ($query) => $query->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereRaw("LOWER(contacts.email) NOT LIKE '%_@_%._%'"))
            ->when($quality === 'dnd', fn ($query) => $query->where('dnd', true))
            ->when($quality === 'duplicate_email' && $clientId !== null, fn ($query) => $query->whereIn(
                DB::raw('LOWER(contacts.email)'),
                Contact::query()
                    ->selectRaw('LOWER(email)')
                    ->where('client_id', $clientId)
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->groupBy(DB::raw('LOWER(email)'))
                    ->havingRaw('COUNT(*) > 1'),
            ))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Contact $contact): array => [
                'id' => $contact->id,
                'name' => $contact->full_name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'company' => $contact->company,
                'account_number' => $contact->account_number,
                'location' => $contact->location,
                'dnd' => (bool) $contact->dnd,
                'dnd_at' => $contact->dnd_at?->toIso8601String(),
                'lists' => $contact->contactLists->pluck('name')->all(),
                'tags' => $contact->tags->pluck('name')->all(),
                'created_at' => $contact->created_at?->toIso8601String(),
            ]);

        $base = Contact::query()->forUser($request->user());

        return Inertia::render('contacts/index', [
            'contacts' => $contacts,
            'filters' => [
                'search' => $search,
                'contact_list_id' => $contactListId > 0 ? $contactListId : null,
                'tag_id' => $tagId > 0 ? $tagId : null,
                'quality' => $quality,
            ],
            'contactLists' => $this->contactListOptions($request),
            'qualityFilters' => $this->qualityOptions(),
            'tags' => $this->tagOptions($request),
            'stats' => [
                'total' => (clone $base)->count(),
                'broadcastable' => (clone $base)->broadcastable()->count(),
                'dnd' => (clone $base)->where('dnd', true)->count(),
                'missing_email' => (clone $base)->where(
                    fn ($query) => $query->whereNull('email')->orWhere('email', ''),
                )->count(),
            ],
            'can' => [
                'create' => Gate::allows('create', Contact::class),
                'import' => Gate::allows('import', Contact::class),
                'update' => Gate::allows('create', Contact::class),
            ],
            'clients' => $this->selectableClients($request),
            'tenantClientId' => $this->tenantClient($request)?->id,
        ]);
    }

    /**
     * Show a single contact with its channel eligibility and DND state.
     */
    public function show(Request $request, Contact $contact, ContactEligibility $eligibility): Response
    {
        Gate::authorize('view', $contact);

        $contact->load(['contactLists:id,name', 'tags:id,name,color', 'creator:id,name']);

        $campaigns = [];

        foreach ($contact->campaignContacts()->with('campaign:id,name,reference,channel,status')->latest('id')->limit(10)->get() as $campaignContact) {
            if ($campaignContact->campaign === null) {
                continue;
            }

            $campaigns[] = [
                'id' => $campaignContact->campaign->id,
                'name' => $campaignContact->campaign->name,
                'reference' => $campaignContact->campaign->reference,
                'channel' => $campaignContact->campaign->channel,
                'status' => $campaignContact->campaign->status,
            ];
        }

        return Inertia::render('contacts/show', [
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->full_name,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'phone' => $contact->phone,
                'original_phone' => $contact->original_phone,
                'alternative_phone' => $contact->alternative_phone,
                'email' => $contact->email,
                'company' => $contact->company,
                'account_number' => $contact->account_number,
                'customer_id' => $contact->customer_id,
                'location' => $contact->location,
                'language' => $contact->language,
                'timezone' => $contact->timezone,
                'dnd' => (bool) $contact->dnd,
                'dnd_at' => $contact->dnd_at?->toIso8601String(),
                'custom_fields' => $contact->custom_fields ?? [],
                'lists' => $contact->contactLists->pluck('name')->all(),
                'tags' => $contact->tags->map(fn (Tag $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ])->all(),
                'creator' => $contact->creator?->name,
                'created_at' => $contact->created_at?->toIso8601String(),
                'updated_at' => $contact->updated_at?->toIso8601String(),
            ],
            'eligibility' => $eligibility->snapshot($contact, ContactEligibility::CHANNELS),
            'campaigns' => $campaigns,
            'can' => [
                'update' => Gate::allows('update', $contact),
                'delete' => Gate::allows('delete', $contact),
            ],
            'clients' => $this->selectableClients($request),
            'dndRequestUrl' => $contact->trashed() ? null : $this->dndRequestUrl($contact),
        ]);
    }

    /**
     * Show the form for creating a contact.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Contact::class);

        return Inertia::render('contacts/create', [
            'contactLists' => $this->contactListOptions($request),
            'tags' => $this->tagOptions($request),
            'clients' => $this->selectableClients($request),
            'contact' => null,
        ]);
    }

    /**
     * Store a new contact in the book.
     */
    public function store(ContactRequest $request, ContactDndService $dnd, AutomationEngine $automations): RedirectResponse
    {
        $contact = Contact::query()->create([
            ...$request->contactAttributes(),
            'created_by' => $request->user()->id,
        ]);

        $contact->contactLists()->sync($this->listPivot($request->input('contact_list_ids', [])));
        $contact->tags()->sync($request->input('tag_ids', []));

        if ($contact->dnd) {
            $dnd->apply($contact, 'contact_form', $request->user());
        }

        $automations->emit(AutomationTrigger::ContactCreated, $contact);

        if ($contact->tags()->exists()) {
            $automations->emit(AutomationTrigger::ContactTagged, $contact);
        }

        return to_route('contacts.index')->with('toast', [
            'type' => 'success',
            'message' => "{$contact->full_name} added to the contact book.",
        ]);
    }

    /**
     * Show the form for editing a contact.
     */
    public function edit(Request $request, Contact $contact): Response
    {
        Gate::authorize('update', $contact);

        return Inertia::render('contacts/edit', [
            'contactLists' => $this->contactListOptions($request),
            'tags' => $this->tagOptions($request),
            'clients' => $this->selectableClients($request),
            'contact' => [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'phone' => $contact->phone,
                'alternative_phone' => $contact->alternative_phone,
                'email' => $contact->email,
                'company' => $contact->company,
                'account_number' => $contact->account_number,
                'customer_id' => $contact->customer_id,
                'location' => $contact->location,
                'language' => $contact->language,
                'timezone' => $contact->timezone,
                'dnd' => (bool) $contact->dnd,
                'contact_list_ids' => $contact->contactLists()->pluck('contact_lists.id')->all(),
                'tag_ids' => $contact->tags()->pluck('tags.id')->all(),
                'custom_fields_json' => json_encode($contact->custom_fields ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ],
        ]);
    }

    /**
     * Update a contact.
     */
    public function update(ContactRequest $request, Contact $contact, ContactDndService $dnd, AutomationEngine $automations): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $wasDnd = (bool) $contact->dnd;
        $previousTagIds = $contact->tags()->pluck('tags.id')->all();

        $contact->update($request->contactAttributes());
        $contact->contactLists()->sync($this->listPivot($request->input('contact_list_ids', [])));
        $contact->tags()->sync($request->input('tag_ids', []));

        if ($request->has('dnd') && $request->boolean('dnd') !== $wasDnd) {
            $dnd->sync($contact, $request->boolean('dnd'), 'contact_form', $request->user());
        }

        $addedTagIds = array_diff($contact->tags()->pluck('tags.id')->all(), $previousTagIds);

        if ($addedTagIds !== []) {
            $automations->emit(AutomationTrigger::ContactTagged, $contact);
        }

        return to_route('contacts.index')->with('toast', [
            'type' => 'success',
            'message' => "{$contact->full_name} updated.",
        ]);
    }

    /**
     * Turn the DND flag on or off for a single contact.
     */
    public function dnd(Request $request, Contact $contact, ContactDndService $dnd): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $data = $request->validate([
            'dnd' => ['required', 'boolean'],
        ]);

        $dnd->sync($contact, (bool) $data['dnd'], 'contact_detail', $request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => $data['dnd']
                ? "{$contact->full_name} is now on the DND list."
                : "{$contact->full_name} can receive campaigns again.",
        ]);
    }

    /**
     * Apply an action to a selection of contacts.
     */
    public function bulk(Request $request, ContactDndService $dnd, AutomationEngine $automations): RedirectResponse
    {
        Gate::authorize('create', Contact::class);

        $client = $this->tenantClient($request);
        abort_if($client === null, 403);

        $data = $request->validate([
            'action' => ['required', Rule::in(['add_to_list', 'remove_from_list', 'add_tag', 'mark_dnd', 'clear_dnd', 'delete'])],
            'contact_ids' => ['required', 'array', 'min:1', 'max:500'],
            'contact_ids.*' => ['integer', Rule::exists('contacts', 'id')->where('client_id', $client->id)->whereNull('deleted_at')],
            'contact_list_id' => ['nullable', 'integer', Rule::exists('contact_lists', 'id')->where('client_id', $client->id)],
            'tag_id' => ['nullable', 'integer', Rule::exists('tags', 'id')->where('client_id', $client->id)],
        ]);

        if (in_array($data['action'], ['add_to_list', 'remove_from_list'], true) && blank($data['contact_list_id'] ?? null)) {
            throw ValidationException::withMessages(['contact_list_id' => 'Choose the contact list to update.']);
        }

        if ($data['action'] === 'add_tag' && blank($data['tag_id'] ?? null)) {
            throw ValidationException::withMessages(['tag_id' => 'Choose the tag to attach.']);
        }

        $contacts = Contact::query()->where('client_id', $client->id)->whereKey($data['contact_ids'])->get();
        $listId = $data['contact_list_id'] ?? null;
        $tagId = $data['tag_id'] ?? null;
        $processed = 0;

        foreach ($contacts as $contact) {
            $tagged = false;

            match ($data['action']) {
                'add_to_list' => $contact->contactLists()->syncWithoutDetaching([(int) $listId => ['added_at' => now()]]),
                'remove_from_list' => $contact->contactLists()->detach((int) $listId),
                'add_tag' => $tagged = $contact->tags()->syncWithoutDetaching([(int) $tagId])['attached'] !== [],
                'mark_dnd' => $dnd->apply($contact, 'bulk_action', $request->user()),
                'clear_dnd' => $dnd->lift($contact),
                'delete' => $contact->delete(),
                default => throw new LogicException('Unsupported bulk action reached the handler.'),
            };

            if ($tagged) {
                $automations->emit(AutomationTrigger::ContactTagged, $contact);
            }

            $processed++;
        }

        activity()
            ->causedBy($request->user())
            ->withProperties(['action' => $data['action'], 'contact_ids' => $data['contact_ids']])
            ->log('contact bulk action');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$processed} ".($processed === 1 ? 'contact' : 'contacts').' updated.',
        ]);
    }

    /**
     * Remove a contact from the book.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        $contact->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Contact removed from the book.',
        ]);
    }

    /**
     * Show the CSV import screen.
     */
    public function showImport(Request $request): Response
    {
        Gate::authorize('import', Contact::class);

        return Inertia::render('contacts/import', [
            'contactLists' => $this->contactListOptions($request),
            'clients' => $this->selectableClients($request),
        ]);
    }

    /**
     * Import contacts from an uploaded CSV file.
     */
    public function import(
        ImportContactsRequest $request,
        ContactImportService $importer,
    ): RedirectResponse {
        $client = $this->tenantClient($request);

        abort_if($client === null, 403, 'Select a client account before importing contacts.');

        $contactListId = $request->integer('contact_list_id');

        $contactList = $contactListId > 0
            ? ContactList::query()->where('client_id', $client->id)->find($contactListId)
            : null;

        $result = $importer->import($client, $request->file('file'), $contactList, $request->user());

        return to_route('contacts.index')->with('import', $result)->with('toast', [
            'type' => $result['skipped'] > 0 ? 'warning' : 'success',
            'message' => "{$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.",
        ]);
    }

    /**
     * Download the CSV template contacts should be uploaded with.
     */
    public function template(): StreamedResponse
    {
        Gate::authorize('import', Contact::class);

        return response()->streamDownload(
            function (): void {
                echo ContactImportService::templateCsv();
            },
            'contact-upload-template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * The contact lists of the tenant, for filtering and assignment.
     *
     * @return list<array{id: int, name: string, contacts_count: int}>
     */
    protected function contactListOptions(Request $request): array
    {
        $client = $this->tenantClient($request);

        if ($client === null) {
            return [];
        }

        return array_values(ContactList::query()
            ->where('client_id', $client->id)
            ->withCount('contacts')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ContactList $list): array => [
                'id' => $list->id,
                'name' => $list->name,
                'contacts_count' => $list->contacts_count,
            ])
            ->all());
    }

    /** @return list<array{id: int, name: string, color: string}> */
    protected function tagOptions(Request $request): array
    {
        $client = $this->tenantClient($request);

        return $client === null ? [] : array_values(Tag::query()->where('client_id', $client->id)->orderBy('name')
            ->get(['id', 'name', 'color'])->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->all());
    }

    /**
     * The data quality filters offered on the contact book.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function qualityOptions(): array
    {
        return [
            ['value' => 'missing_email', 'label' => 'Missing email'],
            ['value' => 'invalid_email', 'label' => 'Invalid email'],
            ['value' => 'duplicate_email', 'label' => 'Duplicate email'],
            ['value' => 'dnd', 'label' => 'On the DND list'],
        ];
    }

    /**
     * A signed link a contact can use to add their number to the DND list.
     */
    protected function dndRequestUrl(Contact $contact): string
    {
        return URL::temporarySignedRoute(
            'contacts.dnd.request',
            now()->addDays(60),
            ['contact' => $contact->id],
        );
    }

    /**
     * Build the pivot payload for the contact lists a contact belongs to.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, array{added_at: string}>
     */
    protected function listPivot(array $ids): array
    {
        return collect($ids)
            ->mapWithKeys(fn ($id): array => [(int) $id => ['added_at' => now()->toDateTimeString()]])
            ->all();
    }
}
