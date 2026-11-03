<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ClientDocumentStatus;
use App\Enums\ClientDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientDocumentRequest;
use App\Models\ClientDocument;
use App\Models\ClientResourceRequest;
use App\Services\PlatformConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->client_id, 403);
        $client = $request->user()->client;
        $client->load(['documents' => fn ($query) => $query->latest(), 'entitlements']);
        $registration = $client->registrations()->latest()->first();

        return Inertia::render('settings/organisation', [
            'settings' => ['timezone' => $client->timezone, ...($client->settings ?? [])],
            'organisation' => [
                'name' => $client->name,
                'account_type' => $client->accountType()->value,
                'account_type_label' => $client->accountType()->label(),
                'email' => $client->email,
                'phone' => $client->phone,
                'status' => $client->status->value,
                'status_label' => $client->status->label(),
                'status_reason' => $client->status_reason,
                'registration_status' => $registration?->status->label(),
                'missing_documents' => array_map(fn (ClientDocumentType $type): string => $type->label(), $client->missingRequiredDocuments()),
            ],
            'documents' => $client->documents->map(fn (ClientDocument $document): array => [
                'id' => $document->id,
                'type' => $document->type->value,
                'title' => $document->title,
                'status' => $document->status->value,
                'status_label' => $document->status->label(),
                'rejection_reason' => $document->rejection_reason,
                'created_at' => $document->created_at?->toIso8601String(),
            ])->all(),
            'documentTypes' => array_map(fn (ClientDocumentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'is_required' => in_array($type, $client->accountType()->identificationTypes(), true),
            ], $client->accountType()->identificationTypes()),
            'entitlements' => $client->entitlements->map(fn ($entitlement): array => [
                'service' => $entitlement->service->label(),
                'is_enabled' => $entitlement->is_enabled,
                'quota' => $entitlement->quotaLabel(),
            ])->values()->all(),
            'timezones' => timezone_identifiers_list(),
            'locales' => config('app.supported_locales', ['en']),
        ]);
    }

    public function storeDocument(StoreClientDocumentRequest $request, PlatformConfiguration $configuration): RedirectResponse
    {
        $client = $request->user()->client;
        abort_unless($client !== null, 403);
        $file = $request->file('document');
        abort_unless($file !== null, 422);
        $disk = (string) $configuration->resolve('storage', 'client_documents_disk', 'local', $client);
        $diskConfiguration = config('filesystems.disks.'.$disk);
        abort_unless(is_array($diskConfiguration) && ($diskConfiguration['visibility'] ?? 'private') !== 'public', 422);

        $client->documents()->create([
            'type' => $request->enum('type', ClientDocumentType::class),
            'title' => (string) $request->validated('title'),
            'path' => (string) $file->store('client-documents/'.$client->id, $disk),
            'storage_disk' => $disk,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'status' => ClientDocumentStatus::Pending,
            'uploaded_by' => $request->user()->id,
            'expires_at' => $request->validated('expires_at'),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Document submitted for review.']);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->client_id, 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', 'string', Rule::in(config('app.supported_locales', ['en']))],
            'branding' => ['nullable', 'array'],
            'branding.name' => ['nullable', 'string', 'max:120'],
            'branding.primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'notification_preferences' => ['nullable', 'array'],
            'notification_preferences.email' => ['sometimes', 'boolean'],
            'notification_preferences.sms' => ['sometimes', 'boolean'],
        ]);
        $client = $request->user()->client;
        $client->name = $validated['name'];
        $client->email = $validated['email'] ?? null;
        $client->phone = $validated['phone'] ?? null;
        $client->timezone = $validated['timezone'];
        $client->settings = [
            'locale' => $validated['locale'],
            'branding' => $validated['branding'] ?? [],
            'notification_preferences' => $validated['notification_preferences'] ?? [],
        ];
        $client->save();
        activity()->causedBy($request->user())->performedOn($client)
            ->withProperties(['settings' => array_keys($validated)])->log('updated client settings');

        return back()->with('toast', ['type' => 'success', 'message' => 'Organisation settings saved.']);
    }

    public function requests(Request $request): Response
    {
        abort_unless($request->user()->client_id, 403);

        return Inertia::render('settings/resource-requests', [
            'requests' => ClientResourceRequest::query()->where('client_id', $request->user()->client_id)->latest()->paginate(15),
        ]);
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        abort_unless($request->user()->client_id, 403);
        $validated = $request->validate([
            'resource_type' => ['required', Rule::in(['caller_id', 'sender_id', 'sending_domain', 'other'])],
            'name' => ['required', 'string', 'max:120'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);
        ClientResourceRequest::query()->create([
            ...$validated,
            'client_id' => $request->user()->client_id,
            'requested_by' => $request->user()->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Resource request submitted.']);
    }
}
