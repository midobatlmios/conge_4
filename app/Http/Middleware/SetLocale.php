<?php

namespace App\Http\Middleware;

use App\Http\Controllers\LocaleController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server messages (validation errors, sign-in, success / error notices) in the
 * interface language of the account or the session. PDFs and exports stay in
 * French: their templates do not use translations.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(LocaleController::current($request));

        return $next($request);
    }
}
