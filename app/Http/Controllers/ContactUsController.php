<?php

namespace App\Http\Controllers;

use App\Enums\SupportTicketType;
use App\Http\Requests\Support\ContactEnquiryRequest;
use App\Models\SupportCategory;
use App\Services\Support\SupportAttachmentStore;
use App\Services\Support\SupportDesk;
use App\Services\Support\SupportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public Contact Us page.
 *
 * A visitor writes once and leaves with a reference number. The enquiry lands in
 * the support register with the team its category names, so nothing depends on a
 * shared mailbox being read — and the acknowledgement email is the receipt.
 */
class ContactUsController extends Controller
{
    public function __construct(
        protected SupportDesk $desk,
        protected SupportLibrary $library,
        protected SupportAttachmentStore $attachments,
    ) {}

    /**
     * Show the contact page and the reasons a visitor can write.
     */
    public function show(): Response
    {
        return Inertia::render('contact', [
            'categories' => $this->library->categories(SupportTicketType::Enquiry),
            'contact' => $this->library->contact(),
            'articles' => $this->library->featured(3),
            'attachments' => $this->library->attachmentLimits(),
        ]);
    }

    /**
     * Record the enquiry and acknowledge it.
     */
    public function store(ContactEnquiryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $category = SupportCategory::query()
            ->where('slug', $validated['category'])
            ->firstOrFail();

        $ticket = $this->desk->open([
            'type' => SupportTicketType::Enquiry,
            'source' => 'public',
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'company' => $validated['company'] ?? null,
            'subject' => $validated['subject'],
            'description' => $validated['message'],
            'category' => $category,
            'consented' => true,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);

        $this->attachments->store(
            $ticket,
            $ticket->messages()->first(),
            $request->file('attachments', []) ?? [],
            null,
        );

        return to_route('contact.show')->with('toast', [
            'type' => 'success',
            'message' => "Thank you — your message reached the {$category->name} team. Quote {$ticket->reference} if you write to us again.",
        ]);
    }
}
