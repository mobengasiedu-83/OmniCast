<?php

namespace App\Http\Controllers;

use App\Enums\ClientService;
use App\Http\Requests\Clients\RegisterClientRequest;
use App\Models\ClientRegistration;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public client onboarding form.
 *
 * Anyone may apply; the application lands in the review queue and no tenant is
 * created until an approver signs it off.
 */
class ClientRegistrationController extends Controller
{
    /**
     * Show the onboarding application form.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register-client', [
            'services' => array_map(
                fn (ClientService $service): array => [
                    'value' => $service->value,
                    'label' => $service->label(),
                ],
                ClientService::cases(),
            ),
            'defaultTimezone' => (string) config('app.timezone'),
        ]);
    }

    /**
     * Open an application for the onboarding team to review.
     */
    public function store(RegisterClientRequest $request): RedirectResponse
    {
        $registration = ClientRegistration::query()->create($request->registrationAttributes());

        activity()
            ->performedOn($registration)
            ->withProperties([
                'company_name' => $registration->company_name,
                'email' => $registration->email,
                'services' => $registration->services,
            ])
            ->log('submitted client registration');

        return to_route('home')->with('toast', [
            'type' => 'success',
            'message' => 'Thanks — your application is with our onboarding team.',
        ]);
    }
}
