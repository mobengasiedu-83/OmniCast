<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Services\ContactDndService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a contact add their number to the DND list from a signed link, without
 * signing in. Uploaded contacts are assumed to have consented, so the only public
 * request a recipient can make is to be left alone.
 */
class ContactDndController extends Controller
{
    /**
     * Show the DND confirmation screen.
     */
    public function show(Request $request, Contact $contact): Response
    {
        return Inertia::render('contacts/dnd', [
            'contact' => [
                'first_name' => $contact->first_name,
                'phone' => $this->maskPhone($contact->phone),
                'email' => $this->maskEmail($contact->email),
                'organisation' => $contact->client?->name,
                'dnd' => (bool) $contact->dnd,
                'dnd_at' => $contact->dnd_at?->toIso8601String(),
            ],
            'dndRequestUrl' => $request->fullUrl(),
        ]);
    }

    /**
     * Add the number to the DND list and show the confirmation screen.
     */
    public function store(Contact $contact, ContactDndService $dnd): RedirectResponse
    {
        $dnd->apply($contact, 'dnd_link');

        activity()
            ->performedOn($contact)
            ->withProperties(['source' => 'dnd_link'])
            ->log('contact added to DND list');

        return redirect()
            ->to(URL::temporarySignedRoute('contacts.dnd.request', now()->addMinutes(30), ['contact' => $contact->id]))
            ->with('toast', [
                'type' => 'success',
                'message' => 'You will no longer receive voice, SMS or email messages from us.',
            ]);
    }

    /**
     * Hide most of a phone number so the page never exposes a full address.
     */
    protected function maskPhone(string $phone): string
    {
        $visible = mb_substr($phone, -4);

        return str_repeat('•', max(mb_strlen($phone) - 4, 0)).$visible;
    }

    /**
     * Hide most of an email address so the page never exposes a full address.
     */
    protected function maskEmail(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return null;
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).str_repeat('•', max(mb_strlen($local) - 1, 1)).'@'.$domain;
    }
}
