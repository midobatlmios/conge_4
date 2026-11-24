<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Employee account management. Every route is behind the role:admin middleware. */
class AdminController extends Controller
{
    public function users(): Response
    {
        return Inertia::render('Admin/Users', [
            // Active employees first; deactivated ones stay listed so they can be reactivated.
            'users' => User::orderByRaw('deactivated_at is not null')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        // The password is hashed by the model's "hashed" cast.
        User::create($request->validated());

        return redirect()->back()->with('success', 'Utilisateur créé avec succès');
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse|RedirectResponse
    {
        $user->fill($request->safe()->only(['name', 'email', 'role']));

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        $user->save();

        if ($request->wantsJson()) {
            return response()->json($user);
        }

        return redirect()->back()->with('success', 'Utilisateur modifié avec succès');
    }

    /**
     * An employee who leaves is deactivated, never deleted: they can no longer
     * sign in, but their requests stay for payroll and audits.
     */
    public function deactivate(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }

        if ($user->isLastAdmin()) {
            return back()->withErrors(['user' => 'Impossible de désactiver le dernier administrateur actif.']);
        }

        if ($user->isActive()) {
            $user->forceFill(['deactivated_at' => now()])->save();
        }

        return back()->with('success', "{$user->name} a été désactivé.");
    }

    public function reactivate(User $user): RedirectResponse
    {
        $user->forceFill(['deactivated_at' => null])->save();

        return back()->with('success', "{$user->name} a été réactivé.");
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $user->update(['password' => $request->validated('password')]);

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès']);
    }
}
