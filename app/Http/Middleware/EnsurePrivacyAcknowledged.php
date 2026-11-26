<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until a signed-in employee has accepted the data protection notice
 * (loi 09-08, art. 5), every page leads to it. Signing out still works.
 */
class EnsurePrivacyAcknowledged
{
    /** Routes reachable before accepting (the notification bell keeps working on the notice page). */
    private const ALLOWED = ['privacy.*', 'logout', 'verification.*', 'notifications.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->privacy_acknowledged_at !== null || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Veuillez d\'abord prendre connaissance de la notice de protection des données.'], 403);
        }

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('privacy.notice');
    }
}
