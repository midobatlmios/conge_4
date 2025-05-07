<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Notifications\DemandeStatusChanged;

class DemandesController extends Controller
{
    const MAX_DAYS_PER_YEAR = 18;

    public function index(): Response
    {
        $demandes = Demande::with('user')->get();
        $currentYear = date('Y');
        
        // Add remaining days information to each user
        $demandes->each(function ($demande) use ($currentYear) {
            $totalDaysUsed = Demande::getTotalDaysForUser($demande->user_id, $currentYear);
            $demande->user->remaining_days = self::MAX_DAYS_PER_YEAR - $totalDaysUsed;
        });

        return Inertia::render('Demandes', [
            'demandes' => $demandes,
        ]);
    }

    public function destroy($id)
    {     
        $demande = Demande::findOrFail($id);
        $demande->delete();
        return redirect()->back()->with('success', 'Demande supprimée avec succès');
    }

    public function update(Request $request, $id)
    {     
        Log::info('Update demande request:', [
            'request_data' => $request->all(),
            'demande_id' => $id
        ]);

        $request->validate([
            'date_demande' => 'required|date',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date',
            'type_conge' => 'required|in:mariage,naissance,deces,sans_solde,recuperation',
            'etat' => 'required|in:en attente,acceptée,refusée',
            'comment' => 'nullable|string|max:1000',
        ]);

        $demande = Demande::findOrFail($id);
        $oldStatus = $demande->etat;
        
        Log::info('Current demande data:', [
            'demande' => $demande->toArray(),
            'old_status' => $oldStatus,
            'new_status' => $request->etat,
            'new_type_conge' => $request->type_conge
        ]);
        
        if ($request->etat === 'acceptée' && $demande->etat !== 'acceptée') {
            $totalDaysUsed = Demande::getTotalDaysForUser($demande->user_id, $demande->annee);
            $newDays = $demande->getNbrJoursAttribute();
            
            if (($totalDaysUsed + $newDays) > self::MAX_DAYS_PER_YEAR) {
                return response()->json([
                    'message' => 'Impossible d\'accepter cette demande. L\'employé dépasserait la limite de ' . self::MAX_DAYS_PER_YEAR . ' jours.'
                ], 422);
            }
        }

        $updateData = [
            'date_demande' => $request->date_demande,
            'date_debut' => $request->date_debut,
            'date_fin' => $request->date_fin,
            'type_conge' => $request->type_conge,
            'etat' => $request->etat,
            'comment' => $request->comment
        ];

        Log::info('Updating demande with data:', $updateData);

        $demande->update($updateData);

        Log::info('Demande updated successfully:', [
            'demande' => $demande->fresh()->toArray()
        ]);

        // Send notification if status changed to accepted or rejected
        if ($oldStatus !== $request->etat && ($request->etat === 'acceptée' || $request->etat === 'refusée')) {
            try {
                $demande->user->notify(new DemandeStatusChanged($demande));
                Log::info('Notification sent successfully', [
                    'demande_id' => $demande->id,
                    'user_id' => $demande->user_id,
                    'status' => $request->etat,
                    'type_conge' => $request->type_conge
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send notification', [
                    'demande_id' => $demande->id,
                    'user_id' => $demande->user_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'message' => 'Demande mise à jour avec succès',
            'demande' => $demande->fresh()
        ]);
    }
     
    public function store(Request $request)
    {    
        // Log the incoming request data
        Log::info('Store demande request:', [
            'request_data' => $request->all(),
            'user_id' => Auth::id(),
            'is_authenticated' => Auth::check()
        ]);

        $request->validate([
            'date_demande' => 'required|date',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date',
            'type_conge' => 'required|in:mariage,naissance,deces,sans_solde,recuperation',
            'etat' => 'required|in:en attente,acceptée,refusée',
            'comment' => 'nullable|string|max:1000',
        ]);
        
        if (!Auth::check()) {
            Log::error('User not authenticated when trying to create demande');
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }
        
        try {
            // Validate type-specific rules
            $nbr_jours = $request->nbr_jours;
            switch ($request->type_conge) {
                case 'mariage':
                    if ($nbr_jours > 4) {
                        return response()->json([
                            'message' => 'Vous ne pouvez pas renseigner plus de 4 jours pour ce type de congé'
                        ], 422);
                    }
                    break;
                case 'naissance':
                    $startDate = new \DateTime($request->date_debut);
                    $oneMonthLater = (clone $startDate)->modify('+1 month');
                    $endDate = new \DateTime($request->date_fin);
                    if ($endDate > $oneMonthLater) {
                        return response()->json([
                            'message' => 'Le congé de naissance doit être pris dans un délai d\'un mois'
                        ], 422);
                    }
                    break;
                case 'deces':
                    if ($nbr_jours > 3) {
                        return response()->json([
                            'message' => 'Vous ne pouvez pas renseigner plus de 3 jours pour ce type de congé'
                        ], 422);
                    }
                    break;
                case 'recuperation':
                    if (empty($request->comment)) {
                        return response()->json([
                            'message' => 'Veuillez renseigner les dates sujet de récupération'
                        ], 422);
                    }
                    break;
            }

            // Log the data before creation
            Log::info('Creating demande with data:', [
                'date_demande' => $request->date_demande,
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin,
                'type_conge' => $request->type_conge,
                'etat' => $request->etat,
                'nbr_jours' => $nbr_jours,
                'annee' => $request->annee,
                'user_id' => Auth::id(),
                'comment' => $request->comment
            ]);

            $demande = Demande::create([
                'date_demande' => $request->date_demande,
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin,
                'type_conge' => $request->type_conge,
                'etat' => $request->etat,
                'nbr_jours' => $nbr_jours,
                'annee' => $request->annee,
                'user_id' => Auth::id(),
                'comment' => $request->comment
            ]);
            
            // Log the created demande
            Log::info('Demande created successfully:', [
                'demande' => $demande->toArray(),
                'user_id' => Auth::id()
            ]);
            
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Demande ajoutée avec succès',
                    'demande' => $demande
                ], 200);
            }
            
            return redirect()->back()->with('success', 'Demande ajoutée avec succès');
        } catch (\Exception $e) {
            Log::error('Error creating demande:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Erreur lors de la création de la demande: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Erreur lors de la création de la demande');
        }
    }
}

