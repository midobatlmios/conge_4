<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The org chart, built from the "Responsable" links between active employees.
 * Everyone sees it (names, job titles, departments only); HR can open a file.
 */
class OrgChartController extends Controller
{
    public function index(): Response
    {
        $people = User::active()
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'job_title', 'department_id', 'manager_id']);

        $ids = $people->modelKeys();

        return Inertia::render('OrgChart', [
            'people' => $people->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'job_title' => $user->job_title,
                'department' => $user->department?->name,
                // A manager who left (or no manager) makes this person a top of the chart.
                'manager_id' => in_array($user->manager_id, $ids, true) ? $user->manager_id : null,
            ])->values(),
        ]);
    }
}
