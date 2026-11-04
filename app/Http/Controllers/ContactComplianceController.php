<?php

namespace App\Http\Controllers;

use App\Enums\ContactSuppressionReason;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactSegment;
use App\Models\ContactSuppression;
use App\Models\Tag;
use App\Services\ContactDuplicates;
use App\Services\ContactQualityReport;
use App\Services\ContactSegmentQuery;
use App\Services\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactComplianceController extends Controller
{
    use ResolvesTenantClient;

    public function index(
        Request $request,
        ContactSegmentQuery $segments,
        ContactQualityReport $quality,
        ContactDuplicates $duplicates,
    ): Response {
        Gate::authorize('viewAny', Contact::class);
        $client = $this->tenantClient($request);
        abort_if($client === null, 403);

        return Inertia::render('contacts/compliance', [
            'quality' => $quality->summary($client),
            'duplicateGroups' => $duplicates->groups($client->id),
            'clientId' => $client->id,
            'segments' => ContactSegment::query()->where('client_id', $client->id)->latest()->get()->map(fn (ContactSegment $segment): array => [
                'id' => $segment->id,
                'name' => $segment->name,
                'description' => $segment->description,
                'filters' => $segment->filters,
                'contacts_count' => $segments->count($segment),
            ]),
            'tags' => Tag::query()->where('client_id', $client->id)->withCount('contacts')->orderBy('name')->get(['id', 'name', 'color']),
            'lists' => ContactList::query()->where('client_id', $client->id)->orderBy('name')->get(['id', 'name']),
            'suppressions' => ContactSuppression::query()
                ->where(fn ($query) => $query->whereNull('client_id')->orWhere('client_id', $client->id))
                ->where('is_active', true)->latest()->limit(100)->get()
                ->map(fn (ContactSuppression $suppression): array => [
                    'id' => $suppression->id,
                    'channel' => $suppression->channel,
                    'identifier' => $suppression->identifier,
                    'reason' => $suppression->reason->value,
                    'reason_label' => $suppression->reason_label,
                    'source' => $suppression->source,
                    'is_global' => $suppression->client_id === null,
                    'suppressed_at' => $suppression->suppressed_at?->toIso8601String(),
                ]),
            'channels' => [
                ['value' => 'sms', 'label' => 'SMS'],
                ['value' => 'voice', 'label' => 'Voice'],
                ['value' => 'email', 'label' => 'Email'],
                ['value' => ContactSuppression::ALL_CHANNELS, 'label' => 'Every channel (DND)'],
            ],
            'reasons' => ContactSuppressionReason::options(),
            'canManageGlobalSuppressions' => $request->user()->isPlatformUser() && $request->user()->isSuperAdmin(),
            'clients' => $this->selectableClients($request),
        ]);
    }

    public function storeTag(Request $request): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'color' => ['nullable', 'string', 'max:24']]);
        $client = $this->tenantClient($request);
        abort_if($client === null, 403);
        Tag::query()->firstOrCreate(['client_id' => $client->id, 'name' => $data['name']], ['color' => $data['color'] ?? 'slate']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Tag saved.']);
    }

    public function storeList(Request $request): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        $client = $this->tenantClient($request);
        abort_if($client === null, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('contact_lists', 'name')->where('client_id', $client->id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        ContactList::query()->create([...$data, 'client_id' => $client->id, 'created_by' => $request->user()->id]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Contact list created.']);
    }

    public function destroyList(Request $request, ContactList $list): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        abort_unless($this->tenantClient($request)?->is($list->client), 404);
        $list->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Contact list archived.']);
    }

    public function destroyTag(Request $request, Tag $tag): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        abort_unless($this->tenantClient($request)?->is($tag->client), 404);
        $tag->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Tag removed.']);
    }

    public function storeSegment(Request $request, ContactSegmentQuery $segments): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        $client = $this->tenantClient($request);
        abort_if($client === null, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'filters' => ['required', 'array'],
            'filters.search' => ['nullable', 'string', 'max:120'],
            'filters.dnd' => ['nullable', 'boolean'],
            'filters.location' => ['nullable', 'string', 'max:120'],
            'filters.company' => ['nullable', 'string', 'max:120'],
            'filters.contact_list_id' => ['nullable', 'integer', Rule::exists('contact_lists', 'id')->where('client_id', $client->id)],
            'filters.tag_id' => ['nullable', 'integer', Rule::exists('tags', 'id')->where('client_id', $client->id)],
            'filters.has_email' => ['nullable', 'boolean'],
        ]);
        $segment = ContactSegment::query()->create([...$data, 'client_id' => $client->id, 'created_by' => $request->user()->id]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Audience segment saved with '.$segments->count($segment).' matching contacts.']);
    }

    public function destroySegment(Request $request, ContactSegment $segment): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        abort_unless($this->tenantClient($request)?->is($segment->client), 404);
        $segment->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Audience segment removed.']);
    }

    /**
     * Rename a saved audience segment or change the filters it matches on.
     */
    public function updateSegment(Request $request, ContactSegment $segment, ContactSegmentQuery $segments): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        $client = $this->tenantClient($request);
        abort_unless($client?->is($segment->client), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'filters' => ['nullable', 'array'],
            'filters.search' => ['nullable', 'string', 'max:120'],
            'filters.dnd' => ['nullable', 'boolean'],
            'filters.location' => ['nullable', 'string', 'max:120'],
            'filters.company' => ['nullable', 'string', 'max:120'],
            'filters.contact_list_id' => ['nullable', 'integer', Rule::exists('contact_lists', 'id')->where('client_id', $client->id)],
            'filters.tag_id' => ['nullable', 'integer', Rule::exists('tags', 'id')->where('client_id', $client->id)],
            'filters.has_email' => ['nullable', 'boolean'],
        ]);

        $segment->update($data);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Audience segment updated. '.$segments->count($segment->refresh()).' contacts match.',
        ]);
    }

    /**
     * Merge a duplicate contact into the record staff chose to keep.
     */
    public function mergeDuplicates(Request $request, ContactDuplicates $duplicates): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        $client = $this->tenantClient($request);
        abort_if($client === null, 403);

        $data = $request->validate([
            'survivor_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('client_id', $client->id)],
            'duplicate_id' => ['required', 'integer', 'different:survivor_id', Rule::exists('contacts', 'id')->where('client_id', $client->id)],
        ]);

        $survivor = Contact::query()->where('client_id', $client->id)->whereKey($data['survivor_id'])->firstOrFail();
        $duplicate = Contact::query()->where('client_id', $client->id)->whereKey($data['duplicate_id'])->firstOrFail();

        $duplicates->merge($survivor, $duplicate, $request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$duplicate->full_name} merged into {$survivor->full_name}.",
        ]);
    }

    public function storeSuppression(Request $request, PhoneNumber $phones): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        $data = $request->validate([
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'channel' => ['required', Rule::in(['sms', 'voice', 'email', ContactSuppression::ALL_CHANNELS])],
            'identifier' => ['required', 'string', 'max:191'],
            'reason' => ['required', Rule::enum(ContactSuppressionReason::class)],
            'global' => ['nullable', 'boolean'],
        ]);
        $global = (bool) ($data['global'] ?? false);
        abort_if($global && (! $request->user()->isPlatformUser() || ! $request->user()->isSuperAdmin()), 403);
        $client = $global ? null : ($request->user()->client ?? $this->tenantClient($request));
        abort_if(! $global && $client === null, 403);
        $identifier = $data['channel'] === 'email'
            ? mb_strtolower(trim($data['identifier']))
            : $phones->normalize($data['identifier']);
        if ($data['channel'] !== 'email') {
            $request->validate(['identifier' => ['regex:/^\+?[0-9 .()\-]{8,24}$/']]);
        } else {
            $request->validate(['identifier' => ['email']]);
        }

        ContactSuppression::query()->create([
            'client_id' => $client?->id,
            'channel' => $data['channel'],
            'identifier' => $identifier,
            'reason' => $data['reason'],
            'source' => 'staff_registry',
            'is_active' => true,
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Suppression added. This address will be excluded from matching campaigns.']);
    }

    public function liftSuppression(Request $request, ContactSuppression $suppression): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        $client = $this->tenantClient($request);
        abort_unless($suppression->client_id === null
            ? ($request->user()->isPlatformUser() && $request->user()->isSuperAdmin())
            : $suppression->client_id === $client?->id, 404);
        $suppression->forceFill(['is_active' => false, 'lifted_at' => now()])->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'Suppression lifted.']);
    }
}
