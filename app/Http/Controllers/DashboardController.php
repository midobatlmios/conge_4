<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $demandes = Demande::where('user_id', $user->id)
            ->latest()
            ->get();

        $remainingDays = Demande::maxDaysPerYear()
            - Demande::getTotalDaysForUser($user->id, now()->year);

        return Inertia::render('Dashboard', [
            'auth' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'remaining_days' => $remainingDays,
                ],
            ],
            'demandes' => $demandes,
        ]);
    }
}
