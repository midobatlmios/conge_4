<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\LeaveCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Company sites (head office, warehouse in another city…), managed by HR
 * (role:admin,rh routes). An employee belongs to a site; a holiday may be
 * local to one site.
 */
class SiteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Sites', [
            'sites' => Site::withCount(['users', 'holidays'])->orderBy('name')->get(['id', 'name', 'city']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $site = Site::create($this->validated($request));

        return back()->with('success', "« {$site->name} » a été ajouté.");
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $site->update($this->validated($request, $site));

        return back()->with('success', "« {$site->name} » a été modifié.");
    }

    /** Only a site without employees can be removed (its local holidays go with it). */
    public function destroy(Site $site): RedirectResponse
    {
        $count = $site->users()->count();
        if ($count > 0) {
            return back()->withErrors(['site' => "Impossible de supprimer « {$site->name} » : {$count} employé(s) y sont rattachés."]);
        }

        $site->delete();
        LeaveCalculator::flushUserSites();

        return back()->with('success', "« {$site->name} » a été supprimé.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Site $site = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('sites', 'name')->ignore($site?->id)],
            'city' => ['nullable', 'string', 'max:100'],
        ], ['name.unique' => 'Ce site existe déjà.'], ['name' => 'nom', 'city' => 'ville']);
    }
}
