<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\HolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public holidays, managed by HR. Every route is behind the role:admin middleware.
 * Changes apply at once to day counts and remaining balances, which are
 * always computed from the current list.
 */
class HolidayController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Holidays', [
            'holidays' => Holiday::orderByDesc('recurring')
                ->orderBy('date')
                ->get(['id', 'name', 'date', 'recurring']),
        ]);
    }

    public function store(HolidayRequest $request): RedirectResponse
    {
        $holiday = Holiday::create($request->holidayData());

        return back()->with('success', "« {$holiday->name} » a été ajouté.");
    }

    public function update(HolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($request->holidayData());

        return back()->with('success', "« {$holiday->name} » a été modifié.");
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('success', "« {$holiday->name} » a été supprimé.");
    }
}
