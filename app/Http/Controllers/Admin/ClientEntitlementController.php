<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\UpdateClientEntitlementsRequest;
use App\Models\Client;
use App\Models\ClientEntitlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the service entitlements and volume limits of a tenant.
 */
class ClientEntitlementController extends Controller
{
    use RecordsActivity;

    /**
     * Upsert the entitlement grid in one request.
     */
    public function update(UpdateClientEntitlementsRequest $request, Client $client): RedirectResponse
    {
        $rows = $request->entitlementRows();

        DB::transaction(function () use ($client, $rows): void {
            foreach ($rows as $service => $attributes) {
                ClientEntitlement::query()->updateOrCreate(
                    ['client_id' => $client->id, 'service' => $service],
                    $attributes,
                );
            }
        });

        $this->recordActivity('updated client entitlements', $client, [
            'services' => array_keys($rows),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Entitlements for {$client->name} updated.",
        ]);
    }
}
