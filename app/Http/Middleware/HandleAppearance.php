<?php

namespace App\Http\Middleware;

use App\Enums\ClientTheme;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');
        View::share('theme', ($request->user()?->client?->theme() ?? ClientTheme::default())->value);

        return $next($request);
    }
}
