<?php

namespace App\Http\Controllers\Support;

use App\Enums\SupportTicketType;
use App\Http\Controllers\Controller;
use App\Services\Support\SupportLibrary;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How a signed-in client reaches a person.
 *
 * The page is deliberately a directory rather than a second form: a client with a
 * problem they want tracked opens a ticket, and one who wants to talk is shown the
 * confirmed telephone, WhatsApp and email of the team that answers.
 */
class ContactSupportController extends Controller
{
    public function __construct(protected SupportLibrary $library) {}

    /**
     * Show the contact directory.
     */
    public function __invoke(): Response
    {
        return Inertia::render('support/contact', [
            'categories' => $this->library->categories(SupportTicketType::Ticket),
            'contact' => $this->library->contact(),
            'articles' => $this->library->featured(4),
        ]);
    }
}
