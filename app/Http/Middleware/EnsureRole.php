<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:kitchen,admin'). Admin is always allowed.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $allowed = array_map(fn (string $r) => UserRole::from($r), $roles);
        $allowed[] = UserRole::Admin;

        abort_unless($user->hasRole(...$allowed), 403);

        return $next($request);
    }
}
