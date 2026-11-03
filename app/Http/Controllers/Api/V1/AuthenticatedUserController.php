<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\Request;

/**
 * Returns the user behind a Sanctum token.
 */
class AuthenticatedUserController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): AuthenticatedUserResource
    {
        return new AuthenticatedUserResource($request->user());
    }
}
