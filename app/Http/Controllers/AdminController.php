<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee account management. Admin and RH change accounts (RH only plain
 * employees, see User::canManageAccount); Paie only reads the list.
 */
class AdminController extends Controller
{
    public function users(Request $request): Response
    {
        return Inertia::render('Admin/Users', [
            // Active employees first; deactivated ones stay listed so they can be reactivated.
            'users' => User::with(['department:id,name', 'manager:id,name', 'contracts'])
                ->orderByRaw('deactivated_at is not null')
                ->orderBy('name')
                ->get()
                // Only the contract in force travels with each user (type, end date).
                ->each(function (User $user) {
                    $contract = $user->currentContract();
                    $user->setAttribute('contract', $contract ? EmploymentContractController::present($contract) : null);
                    $user->unsetRelation('contracts');
                }),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            // People who can be picked as someone's manager.
            'managers' => User::active()->orderBy('name')->get(['id', 'name']),
            'roles' => self::roleOptions($request->user()),
        ]);
    }

    /**
     * Roles the signed-in user may give, for the role pickers.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function roleOptions(User $actor): array
    {
        return array_map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()], $actor->assignableRoles());
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        // The password is hashed by the model's "hashed" cast.
        User::create([
            ...$request->safe()->only(['name', 'email', 'password', 'role']),
            ...$request->employeeRecord(),
        ]);

        return back()->with('success', 'Utilisateur créé avec succès');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill([
            ...$request->safe()->only(['name', 'email', 'role']),
            ...$request->employeeRecord(),
        ]);

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        $user->save();

        return back()->with('success', 'Utilisateur modifié avec succès');
    }

    /**
     * An employee who leaves is deactivated, never deleted: they can no longer
     * sign in, but their requests stay for payroll and audits.
     */
    public function deactivate(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->canManageAccount($user), 403);

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

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->canManageAccount($user), 403);

        $user->forceFill(['deactivated_at' => null])->save();

        return back()->with('success', "{$user->name} a été réactivé.");
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->update(['password' => $request->validated('password')]);

        return back()->with('success', 'Mot de passe réinitialisé avec succès');
    }
}
