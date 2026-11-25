<?php

namespace App\Http\Controllers;

use App\Services\LeaveCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, LeaveCalculator $calculator): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'auth' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_manager' => $user->isManager(),
                    'can' => $user->permissions(),
                    'remaining_days' => $calculator->remainingDays($user->id, now()->year),
                ],
            ],
            'demandes' => $user->demandes()->with('documents')->latest()->get(),
        ]);
    }
}
