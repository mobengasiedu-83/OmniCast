<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Models\Client;
use App\Models\Contact;
use App\Models\ContactList;
use App\Services\ContactImportService;
use App\Services\PlatformConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages the reusable contact lists campaigns are addressed to: viewing the
 * members of a list, renaming it, uploading contacts straight into it and
 * archiving it once it is no longer used.
 */
class ContactListController extends Controller
{
    use ResolvesTenantClient;

    /**
     * The list library of the tenant, with the size of every list.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Contact::class);

        $client = $this->tenantClient($request);
        $search = $request->string('search')->toString();

        $lists = $this->scopedLists($client)
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->with('client:id,name')
            ->withCount(['contacts', 'campaigns'])
            ->orderBy('name')
            ->get();

        $tenantLists = $this->scopedLists($client)->withCount('contacts')->get();
        $listIds = $tenantLists->pluck('id')->all();
        $largest = $tenantLists->sortByDesc('contacts_count')->first();

        return Inertia::render('contacts/lists/index', [
            'lists' => $lists->map(fn (ContactList $list): array => [
                'id' => $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'client' => $list->client?->name,
                'contacts_count' => $list->contacts_count,
                'campaigns_count' => $list->campaigns_count,
                'updated_at' => $list->updated_at?->toIso8601String(),
            ]),
            'filters' => ['search' => $search],
            'stats' => [
                'lists' => $tenantLists->count(),
                'assigned' => $this->contactsInLists($client, $listIds),
                'unassigned' => $this->tenantContacts($client)->whereDoesntHave('contactLists')->count(),
                'empty' => $tenantLists->filter(fn (ContactList $list): bool => $list->contacts_count === 0)->count(),
                'largest' => $largest?->name,
                'largest_count' => $largest?->contacts_count ?? 0,
            ],
            'can' => $this->abilities(),
            'clients' => $this->selectableClients($request),
            'tenantClientId' => $client?->id,
        ]);
    }

    /**
     * Create a list, from either the list library or the audiences workspace.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Contact::class);

        $client = $this->tenantClient($request);
        abort_if($client === null, 403, 'Select a client account before creating a contact list.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('contact_lists', 'name')->where('client_id', $client->id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $list = ContactList::query()->create([...$data, 'client_id' => $client->id, 'created_by' => $request->user()->id]);

        return back()->with('toast', ['type' => 'success', 'message' => "{$list->name} list created."]);
    }

    /**
     * The members of a single list, plus the contacts that can still be added.
     */
    public function show(Request $request, ContactList $list): Response
    {
        Gate::authorize('viewAny', Contact::class);
        $this->authorizeListAccess($request, $list);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $addSearch = $request->string('add_search')->toString();

        $members = $list->contacts()
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->when($status === 'dnd', fn (Builder $query) => $query->where('contacts.dnd', true))
            ->when($status === 'reachable', fn (Builder $query) => $query->where('contacts.dnd', false))
            ->orderByDesc('contact_list_contact.added_at')
            ->orderByDesc('contacts.id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Contact $contact): array => [
                'id' => $contact->id,
                'name' => $contact->full_name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'company' => $contact->company,
                'dnd' => (bool) $contact->dnd,
                'added_at' => filled($contact->pivot->added_at ?? null)
                    ? Carbon::parse($contact->pivot->added_at)->toIso8601String()
                    : null,
            ]);

        $candidates = $this->addableContacts($list, $addSearch)->limit(20)
            ->get(['id', 'first_name', 'last_name', 'phone', 'email']);

        return Inertia::render('contacts/lists/show', [
            'list' => [
                'id' => $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'client_name' => $list->client?->name,
                'campaigns_count' => $list->campaigns()->count(),
                'created_at' => $list->created_at?->toIso8601String(),
                'updated_at' => $list->updated_at?->toIso8601String(),
            ],
            'contacts' => $members,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'addSearch' => $addSearch,
            'candidates' => $candidates->map(fn (Contact $contact): array => [
                'id' => $contact->id,
                'name' => $contact->full_name,
                'phone' => $contact->phone,
                'email' => $contact->email,
            ]),
            'candidatesTotal' => $this->addableContacts($list, $addSearch)->count(),
            'stats' => [
                'contacts' => $list->contacts()->count(),
                'reachable' => $list->contacts()->where('contacts.dnd', false)->count(),
                'dnd' => $list->contacts()->where('contacts.dnd', true)->count(),
                'missing_email' => $list->contacts()
                    ->where(fn (Builder $query) => $query->whereNull('contacts.email')->orWhere('contacts.email', ''))
                    ->count(),
            ],
            'can' => $this->abilities(),
        ]);
    }

    /**
     * Rename a list or change the note staff see when picking an audience.
     */
    public function update(Request $request, ContactList $list): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        $this->authorizeListAccess($request, $list);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('contact_lists', 'name')->where('client_id', $list->client_id)->ignore($list->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $list->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => 'Contact list updated.']);
    }

    /**
     * Archive a list. The contacts themselves stay in the book.
     */
    public function destroy(Request $request, ContactList $list): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        $this->authorizeListAccess($request, $list);

        $list->delete();

        $toast = ['type' => 'success', 'message' => "{$list->name} list archived. Contacts stay in the book."];

        // The list library keeps the reader on the index; the audiences
        // workspace simply returns to the screen staff came from.
        return $request->routeIs('contacts.lists.destroy')
            ? to_route('contacts.lists.index')->with('toast', $toast)
            : back()->with('toast', $toast);
    }

    /**
     * Upload a CSV export straight into the list that is open.
     */
    public function import(Request $request, ContactList $list, ContactImportService $importer): RedirectResponse
    {
        Gate::authorize('import', Contact::class);
        $this->authorizeListAccess($request, $list);

        $client = $list->client;
        abort_if($client === null, 403, 'This list is not attached to a client account.');

        $maximumUploadKilobytes = max(1, (int) app(PlatformConfiguration::class)->resolve(
            'storage',
            'max_upload_kb',
            4096,
            $client,
        ));

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.$maximumUploadKilobytes],
        ]);

        $result = $importer->import($client, $request->file('file'), $list, $request->user());

        return back()->with('import', $result)->with('toast', [
            'type' => $result['skipped'] > 0 ? 'warning' : 'success',
            'message' => "{$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped in {$list->name}.",
        ]);
    }

    /**
     * Add contacts that already exist in the book to the list.
     */
    public function attach(Request $request, ContactList $list): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        $this->authorizeListAccess($request, $list);

        $data = $request->validate([
            'contact_ids' => ['required', 'array', 'min:1', 'max:500'],
            'contact_ids.*' => [
                'integer',
                Rule::exists('contacts', 'id')->where('client_id', $list->client_id)->whereNull('deleted_at'),
            ],
        ]);

        $pivot = collect($data['contact_ids'])
            ->mapWithKeys(fn (int|string $id): array => [(int) $id => ['added_at' => now()->toDateTimeString()]])
            ->all();

        $list->contacts()->syncWithoutDetaching($pivot);

        $count = count($data['contact_ids']);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$count} ".($count === 1 ? 'contact' : 'contacts')." added to {$list->name}.",
        ]);
    }

    /**
     * Remove contacts from the list without deleting them from the book.
     */
    public function detach(Request $request, ContactList $list): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        $this->authorizeListAccess($request, $list);

        $data = $request->validate([
            'contact_ids' => ['required', 'array', 'min:1', 'max:500'],
            'contact_ids.*' => ['integer'],
        ]);

        $list->contacts()->detach($data['contact_ids']);

        $count = count($data['contact_ids']);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$count} ".($count === 1 ? 'contact' : 'contacts')." removed from {$list->name}.",
        ]);
    }

    /**
     * The lists a request is scoped to, or every list when a platform operator
     * has not picked a client account yet.
     *
     * @return Builder<ContactList>
     */
    protected function scopedLists(?Client $client): Builder
    {
        return ContactList::query()
            ->when($client !== null, fn (Builder $query) => $query->where('client_id', $client->id));
    }

    /**
     * The contact book of the tenant a request is scoped to.
     *
     * @return Builder<Contact>
     */
    protected function tenantContacts(?Client $client): Builder
    {
        return Contact::query()
            ->when($client !== null, fn (Builder $query) => $query->where('client_id', $client->id));
    }

    /**
     * Contacts of the list's tenant that are not in the list yet.
     *
     * @return Builder<Contact>
     */
    protected function addableContacts(ContactList $list, string $search): Builder
    {
        return Contact::query()
            ->where('client_id', $list->client_id)
            ->whereDoesntHave('contactLists', fn (Builder $query) => $query->whereKey($list->id))
            ->search($search)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('phone');
    }

    /**
     * How many contacts sit in at least one of the given lists.
     *
     * @param  list<int>  $listIds
     */
    protected function contactsInLists(?Client $client, array $listIds): int
    {
        if ($listIds === []) {
            return 0;
        }

        return $this->tenantContacts($client)
            ->whereHas('contactLists', fn (Builder $query) => $query->whereIn('contact_lists.id', $listIds))
            ->count();
    }

    /**
     * @return array{create: bool, update: bool, import: bool, delete: bool}
     */
    protected function abilities(): array
    {
        $manage = Gate::allows('create', Contact::class);

        return [
            'create' => $manage,
            'update' => $manage,
            'import' => Gate::allows('import', Contact::class),
            'delete' => $manage,
        ];
    }

    /**
     * Only the tenant that owns the list may open it.
     */
    protected function authorizeListAccess(Request $request, ContactList $list): void
    {
        if ($request->user()->client_id !== null) {
            abort_unless($list->client_id === $request->user()->client_id, 404);

            return;
        }

        $client = $this->tenantClient($request);

        abort_if($client !== null && $list->client_id !== $client->id, 404);
    }
}
