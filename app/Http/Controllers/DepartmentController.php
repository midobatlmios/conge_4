<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Departments, managed by HR. Every route is behind the role:admin,rh middleware. */
class DepartmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Departments', [
            'departments' => Department::withCount('users')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        $department = Department::create($request->validated());

        return back()->with('success', "« {$department->name} » a été ajouté.");
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return back()->with('success', "« {$department->name} » a été modifié.");
    }

    /** Only an empty department can be removed, so no employee silently loses theirs. */
    public function destroy(Department $department): RedirectResponse
    {
        $count = $department->users()->count();

        if ($count > 0) {
            return back()->withErrors([
                'department' => "Impossible de supprimer « {$department->name} » : {$count} employé(s) y sont rattachés.",
            ]);
        }

        $department->delete();

        return back()->with('success', "« {$department->name} » a été supprimé.");
    }
}
