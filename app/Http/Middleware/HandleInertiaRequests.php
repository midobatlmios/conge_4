<?php

namespace App\Http\Middleware;

use App\Models\Holiday;
use App\Services\LeaveCalculator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                ] : null,
            ],
            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'leaveRules' => fn (): array => [
                'maxDaysPerYear' => config('conge.max_days_per_year'),
                'countedTypes' => config('conge.counted_types'),
                'fixedDeductions' => config('conge.fixed_deductions'),
                // The signed-in user's remaining annual days for the current year.
                'remainingDays' => $request->user()
                    ? app(LeaveCalculator::class)->remainingDays($request->user()->id, now()->year)
                    : null,
                'typeLimits' => config('conge.type_limits'),
                'workingDays' => config('conge.working_days'),
                // Only signed-in pages count leave days; skip the query for guests.
                'holidays' => $request->user() ? Holiday::keys() : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
