<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Demande;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    const MAX_DAYS_PER_YEAR = 18;

    public function index(): Response
    {
        $user = Auth::user();
        
        // Log the user ID and authentication status
        Log::info('Dashboard access:', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'is_authenticated' => Auth::check(),
            'auth_user' => Auth::user()
        ]);

        // Get all demandes for the user with detailed logging
        $demandes = Demande::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Log the SQL query and results
        Log::info('Demandes query:', [
            'sql' => Demande::where('user_id', $user->id)->toSql(),
            'bindings' => [$user->id],
            'count' => $demandes->count(),
            'demandes' => $demandes->toArray()
        ]);

        // Calculate total days used for the current year
        $currentYear = date('Y');
        $totalDaysUsed = Demande::getTotalDaysForUser($user->id, $currentYear);
        $remainingDays = self::MAX_DAYS_PER_YEAR - $totalDaysUsed;

        // Log the calculation details
        Log::info('Days calculation:', [
            'max_days_per_year' => self::MAX_DAYS_PER_YEAR,
            'current_year' => $currentYear,
            'total_days_used' => $totalDaysUsed,
            'remaining_days' => $remainingDays
        ]);

        // Log the final data being sent to the view
        Log::info('Final data being sent to view:', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'remaining_days' => $remainingDays,
            ],
            'demandes_count' => $demandes->count(),
            'demandes' => $demandes->toArray()
        ]);

        return Inertia::render('Dashboard', [
            'auth' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'remaining_days' => $remainingDays,
                ],
            ],
            'demandes' => $demandes->map(function ($demande) {
                return [
                    'id' => $demande->id,
                    'date_demande' => $demande->date_demande,
                    'date_debut' => $demande->date_debut,
                    'date_fin' => $demande->date_fin,
                    'nbr_jours' => $demande->nbr_jours,
                    'etat' => $demande->etat,
                    'comment' => $demande->comment,
                ];
            })->toArray(),
        ]);
    }
} 