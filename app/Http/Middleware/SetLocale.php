<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Language is remembered in the session; ?lang=lo or ?lang=en switches it.
 */
class SetLocale
{
    public const SUPPORTED = ['en', 'lo'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('lang') && in_array($request->query('lang'), self::SUPPORTED, true)) {
            $request->session()->put('locale', $request->query('lang'));
        }

        // Customers scanning a table QR code get Lao first; staff get the app default.
        $default = $request->routeIs('customer.*') ? 'lo' : config('app.locale');

        app()->setLocale($request->session()->get('locale', $default));

        return $next($request);
    }
}
