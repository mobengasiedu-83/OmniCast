<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsActivity;
use App\Enums\ClientDocumentStatus;
use App\Enums\ClientDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ReviewClientDocumentRequest;
use App\Http\Requests\Clients\StoreClientDocumentRequest;
use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Uploads and compliance review of a tenant's documents.
 *
 * Files are stored on the private disk; the row keeps the metadata the review
 * screen needs without exposing a download URL to the browser.
 */
class ClientDocumentController extends Controller
{
    use RecordsActivity;

    /**
     * Store an uploaded document against the tenant.
     */
    public function store(StoreClientDocumentRequest $request, Client $client): RedirectResponse
    {
        $file = $request->file('document');

        if ($file === null) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Choose a file to upload.',
            ]);
        }

        $document = $client->documents()->create([
            'type' => $request->enum('type', ClientDocumentType::class),
            'title' => (string) $request->validated('title'),
            'path' => (string) $file->store('client-documents/'.$client->id, 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'status' => ClientDocumentStatus::Pending,
            'uploaded_by' => $request->user()?->getAuthIdentifier(),
            'expires_at' => $request->validated('expires_at'),
        ]);

        $this->recordActivity('uploaded client document', $document, [
            'client_id' => $client->id,
            'type' => $document->type->value,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$document->title} uploaded for review.",
        ]);
    }

    /**
     * Verify or reject an uploaded document.
     */
    public function review(ReviewClientDocumentRequest $request, Client $client, ClientDocument $document): RedirectResponse
    {
        $this->ensureBelongsTo($client, $document);

        $decision = (string) $request->validated('decision');
        $verified = $decision === 'verify';

        $document->forceFill([
            'status' => $verified ? ClientDocumentStatus::Verified : ClientDocumentStatus::Rejected,
            'reviewed_by' => $request->user()?->getAuthIdentifier(),
            'reviewed_at' => now(),
            'rejection_reason' => $verified ? null : (string) $request->validated('reason'),
        ])->save();

        $this->recordActivity(
            $verified ? 'verified client document' : 'rejected client document',
            $document,
            [
                'client_id' => $client->id,
                'type' => $document->type->value,
                'reason' => $verified ? null : $request->validated('reason'),
            ],
        );

        return back()->with('toast', [
            'type' => $verified ? 'success' : 'warning',
            'message' => $verified
                ? "{$document->title} verified."
                : "{$document->title} rejected and the tenant notified.",
        ]);
    }

    /**
     * Remove a document from the tenant and the private disk.
     */
    public function destroy(Request $request, Client $client, ClientDocument $document): RedirectResponse
    {
        Gate::authorize('update', $client);

        $this->ensureBelongsTo($client, $document);

        $title = $document->title;

        Storage::disk('local')->delete($document->path);

        $document->delete();

        $this->recordActivity('deleted client document', null, [
            'client_id' => $client->id,
            'title' => $title,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$title} removed.",
        ]);
    }

    /**
     * Guard against addressing another tenant's document through a guessed id.
     */
    protected function ensureBelongsTo(Client $client, ClientDocument $document): void
    {
        if ((int) $document->client_id !== (int) $client->id) {
            abort(404);
        }
    }
}
