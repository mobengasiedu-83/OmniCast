<?php

namespace App\Http\Controllers;

use App\Enums\ContactConsentStatus;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Http\Requests\Contact\ContactRequest;
use App\Http\Requests\Contact\ImportContactsRequest;
use App\Models\Contact;
use App\Models\ContactList;
use App\Services\ContactImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

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
        $consent = $request->string('consent_status')->toString();

        $contacts = Contact::query()
            ->forUser($request->user())
            ->with('contactLists:id,name')
            ->search($search)
            ->when($contactListId > 0, fn ($query) => $query->whereHas(
                'contactLists',
                fn ($query) => $query->whereKey($contactListId),
            ))
            ->when($consent !== '', fn ($query) => $query->where('consent_status', $consent))
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
                'consent_status' => $contact->consent_status->value,
                'consent_label' => $contact->consent_status->label(),
                'is_do_not_contact' => $contact->is_do_not_contact,
                'lists' => $contact->contactLists->pluck('name')->all(),
                'created_at' => $contact->created_at?->toIso8601String(),
            ]);

        $base = Contact::query()->forUser($request->user());

        return Inertia::render('contacts/index', [
            'contacts' => $contacts,
            'filters' => [
                'search' => $search,
                'contact_list_id' => $contactListId > 0 ? $contactListId : null,
                'consent_status' => $consent,
            ],
            'contactLists' => $this->contactListOptions($request),
            'consentStatuses' => $this->consentOptions(),
            'stats' => [
                'total' => (clone $base)->count(),
                'broadcastable' => (clone $base)->broadcastable()->count(),
                'do_not_contact' => (clone $base)->where('is_do_not_contact', true)->count(),
            ],
            'can' => [
                'create' => Gate::allows('create', Contact::class),
                'import' => Gate::allows('import', Contact::class),
            ],
            'clients' => $this->selectableClients($request),
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
            'consentStatuses' => $this->consentOptions(),
            'clients' => $this->selectableClients($request),
            'contact' => null,
        ]);
    }

    /**
     * Store a new contact in the book.
     */
    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = Contact::query()->create([
            ...$request->contactAttributes(),
            'created_by' => $request->user()->id,
        ]);

        $contact->contactLists()->sync($this->listPivot($request->input('contact_list_ids', [])));

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
            'consentStatuses' => $this->consentOptions(),
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
                'consent_status' => $contact->consent_status->value,
                'is_do_not_contact' => $contact->is_do_not_contact,
                'contact_list_ids' => $contact->contactLists()->pluck('contact_lists.id')->all(),
            ],
        ]);
    }

    /**
     * Update a contact.
     */
    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $contact->update($request->contactAttributes());
        $contact->contactLists()->sync($this->listPivot($request->input('contact_list_ids', [])));

        return to_route('contacts.index')->with('toast', [
            'type' => 'success',
            'message' => "{$contact->full_name} updated.",
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

        return ContactList::query()
            ->where('client_id', $client->id)
            ->withCount('contacts')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ContactList $list): array => [
                'id' => $list->id,
                'name' => $list->name,
                'contacts_count' => $list->contacts_count,
            ])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function consentOptions(): array
    {
        return array_map(
            fn (ContactConsentStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            ContactConsentStatus::cases(),
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
