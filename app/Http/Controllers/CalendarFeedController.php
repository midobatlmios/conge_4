<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\IcalFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calendar subscriptions (iCal): the settings page with the private links,
 * and the feeds themselves, read by Outlook / Google Calendar with the secret
 * token in the URL (they cannot sign in).
 */
class CalendarFeedController extends Controller
{
    public function settings(Request $request): Response
    {
        $user = $request->user();
        $token = $user->calendar_token;

        return Inertia::render('settings/calendar', [
            'links' => $token ? [
                'me' => route('calendar.feed', ['token' => $token, 'feed' => 'me']),
                'team' => $user->approvesRequests() ? route('calendar.feed', ['token' => $token, 'feed' => 'team']) : null,
            ] : null,
            'approves' => $user->approvesRequests(),
        ]);
    }

    /** Creates the links, or replaces them (the old ones stop working). */
    public function regenerate(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['calendar_token' => Str::random(48)])->save();

        return back()->with('success', 'Nouveaux liens d\'abonnement créés. Les anciens ne fonctionnent plus.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['calendar_token' => null])->save();

        return back()->with('success', 'Abonnement désactivé : les liens ne fonctionnent plus.');
    }

    /** The .ics file. Unknown token, account deactivated, or no team: not found. */
    public function feed(string $token, string $feed, IcalFeed $ical): HttpResponse
    {
        $user = User::where('calendar_token', $token)->whereNull('deactivated_at')->first();
        abort_unless($user && in_array($feed, ['me', 'team'], true), 404);
        abort_if($feed === 'team' && ! $user->approvesRequests(), 404);

        return response($feed === 'me' ? $ical->mine($user) : $ical->team($user), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.($feed === 'me' ? 'mes-conges' : 'mon-equipe').'.ics"',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }
}
