<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function users(): Response
    {
        return Inertia::render('Admin/Users', [
            'users' => User::all()
        ]);
    }

    public function getUsers()
    {
        return response()->json(User::all());
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => 'required|in:admin,user',
        ];

        if ($request->filled('password')) {
            $rules['password'] = 'required|min:8|confirmed';
            $rules['password_confirmation'] = 'required';
        }

        $validated = $request->validate($rules);

        // Never leave the app without an administrator.
        if ($user->role === 'admin' && $validated['role'] !== 'admin') {
            if ($user->is($request->user())) {
                throw ValidationException::withMessages([
                    'role' => "Vous ne pouvez pas retirer votre propre rôle d'administrateur.",
                ]);
            }
            if (User::where('role', 'admin')->count() <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'Impossible de retirer le dernier administrateur.',
                ]);
            }
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        if ($request->filled('password')) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if ($request->wantsJson()) {
            return response()->json($user);
        }

        return redirect()->back()->with('success', 'Utilisateur modifié avec succès');
    }

    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'password' => 'required|min:8|confirmed',
            'password_confirmation' => 'required'
        ]);

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:user,admin'
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role']
        ]);

        return redirect()->back()->with('success', 'Utilisateur créé avec succès');
    }
} 