<?php

namespace App\Http\Middleware;

use App\Models\CompanySetting;
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
            // Interface language (fr, ar, en); server messages and PDFs stay in French for now.
            'locale' => \App\Http\Controllers\LocaleController::current($request),
            // One-off messages after an action, shown as toasts (components/app-toaster.tsx).
            'flash' => fn (): array => [
                // Fixed sentences are translated (lang/ar.json, lang/en.json); others stay as written.
                'success' => ($message = $request->session()->get('success')) ? __($message) : null,
                'error' => ($message = $request->session()->get('error')) ? __($message) : null,
            ],
            // The company's name, accent color and logo (or initials), on every page incl. sign-in.
            'brand' => fn (): array => CompanySetting::current()->brand(),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    // Approves their team's requests first (two-step approval).
                    // Approves requests: their team, or one delegated to them today.
                    'is_manager' => $request->user()->approvesRequests(),
                    'can' => $request->user()->permissions(),
                ] : null,
            ],
            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            // Sites for the employee forms (HR only; empty list: the field is hidden).
            'sites' => fn (): array => $request->user()?->isHr() ? \App\Models\Site::orderBy('name')->get(['id', 'name'])->all() : [],
            'leaveRules' => fn (): array => [
                'maxDaysPerYear' => config('conge.max_days_per_year'),
                'countedTypes' => config('conge.counted_types'),
                'fixedDeductions' => config('conge.fixed_deductions'),
                'seniorityBonusDays' => config('conge.seniority_bonus_days'),
                'seniorityYears' => config('conge.seniority_years'),
                // The signed-in user's balance for the current year (days earned so far, used, left).
                'balance' => $request->user() ? app(LeaveCalculator::class)->balance($request->user()) : null,
                'typeLimits' => config('conge.type_limits'),
                'halfDayTypes' => config('conge.half_day_types'),
                // Supporting documents: types that need one before HR accepts, accepted files, size limit.
                'documentTypes' => config('conge.document_types'),
                // Sick leave can be declared up to this many days after it started.
                'sickLeaveBackdateDays' => config('conge.sick_leave_backdate_days'),
                'documentMimes' => config('conge.document_mimes'),
                'documentMaxKb' => config('conge.document_max_kb'),
                'workingDays' => config('conge.working_days'),
                // Only signed-in pages count leave days; skip the query for guests.
                // The signed-in person's site: national holidays plus its local ones.
                'holidays' => $request->user() ? Holiday::keys($request->user()->site_id) : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
