<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to the given roles, e.g. "role:admin,rh".
 * Other users get the 403 "access denied" page.
 */
class RoleMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role?->value, $roles, true)) {
            return response()->view('errors.unauthorized', [], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
