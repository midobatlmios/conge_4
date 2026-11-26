<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The interface language: French (default), Arabic (right to left) or English.
 * Saved on the account when signed in, in the session on the sign-in pages.
 * PDFs (attestations, payslips, reports) always stay in French.
 */
class LocaleController extends Controller
{
    public const LOCALES = ['fr', 'ar', 'en'];

    public function update(Request $request): RedirectResponse
    {
        $locale = $request->validate(['locale' => ['required', Rule::in(self::LOCALES)]])['locale'];

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }

    /** The language of this request: the account's, else the session's, else French. */
    public static function current(Request $request): string
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale');

        return in_array($locale, self::LOCALES, true) ? $locale : 'fr';
    }
}
