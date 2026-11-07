<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Models\User;
use App\Notifications\DemandeStatusChanged;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class DemandesController extends Controller
{
    private const MAX_DAYS_PER_YEAR = 18;

    public function index(): Response
    {
        $user = Auth::user();
        $query = Demande::with('user')->latest();

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $demandes = $query->get();
        $remainingDaysByUser = [];

        $demandes->each(function (Demande $demande) use (&$remainingDaysByUser) {
            if (!array_key_exists($demande->user_id, $remainingDaysByUser)) {
                $remainingDaysByUser[$demande->user_id] = self::MAX_DAYS_PER_YEAR
                    - Demande::getTotalDaysForUser($demande->user_id, now()->year);
            }

            $demande->user->remaining_days = $remainingDaysByUser[$demande->user_id];
        });

        return Inertia::render('Demandes', [
            'demandes' => $demandes,
        ]);
    }

    public function destroy(int $id)
    {
        $demande = Demande::findOrFail($id);
        $user = Auth::user();

        abort_unless(
            $user->role === 'admin'
                || ($demande->user_id === $user->id && $demande->etat === 'en attente'),
            403
        );

        $demande->delete();

        return redirect()->back()->with('success', 'Demande supprimée avec succès');
    }

    public function update(Request $request, int $id)
    {
        $demande = Demande::findOrFail($id);
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->role === 'admin';

        abort_unless(
            $isAdmin || ($demande->user_id === $user->id && $demande->etat === 'en attente'),
            403
        );

        $validated = $this->validateLeaveData($request, $isAdmin);
        $status = $isAdmin ? $validated['etat'] : $demande->etat;

        if ($status === 'acceptée') {
            $this->ensureAnnualLimit(
                $demande->user_id,
                $validated['date_debut'],
                $validated['date_fin'],
                $demande->id
            );
        }

        $oldStatus = $demande->etat;
        $demande->update([
            'date_demande' => $validated['date_demande'],
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
            'type_conge' => $validated['type_conge'],
            'etat' => $status,
            'comment' => $validated['comment'] ?? null,
            'valide_par' => $isAdmin && $status !== 'en attente' ? $user->id : null,
        ]);

        if ($isAdmin && $oldStatus !== $status && in_array($status, ['acceptée', 'refusée'], true)) {
            $demande->user->notify(new DemandeStatusChanged($demande));
        }

        return response()->json([
            'message' => 'Demande mise à jour avec succès',
            'demande' => $demande->fresh(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateLeaveData($request);
        $user = Auth::user();

        Demande::create([
            'date_demande' => $validated['date_demande'],
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
            'type_conge' => $validated['type_conge'],
            'etat' => 'en attente',
            'user_id' => $user->id,
            'comment' => $validated['comment'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Demande ajoutée avec succès',
            ], 201);
        }

        return redirect()->back()->with('success', 'Demande ajoutée avec succès');
    }

    private function validateLeaveData(Request $request, bool $isAdmin = false): array
    {
        $rules = [
            'date_demande' => ['required', 'date'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'type_conge' => ['required', 'in:mariage,naissance,deces,sans_solde,recuperation'],
            'comment' => ['required_if:type_conge,recuperation', 'nullable', 'string', 'max:1000'],
        ];

        if ($isAdmin) {
            $rules['etat'] = ['required', 'in:en attente,acceptée,refusée'];
        }

        return Validator::make($request->all(), $rules)
            ->after(function ($validator) use ($request) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $days = Carbon::parse($request->date_debut)
                    ->diffInDays(Carbon::parse($request->date_fin)) + 1;
                $maximum = match ($request->type_conge) {
                    'mariage' => 4,
                    'naissance', 'deces' => 3,
                    default => null,
                };

                if ($maximum !== null && $days > $maximum) {
                    $validator->errors()->add(
                        'date_fin',
                        "Cette demande ne peut pas dépasser {$maximum} jours."
                    );
                }
            })
            ->validate();
    }

    private function ensureAnnualLimit(
        int $userId,
        string $startDate,
        string $endDate,
        ?int $excludeDemandeId = null
    ): void {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        for ($year = $start->year; $year <= $end->year; $year++) {
            $yearStart = $start->copy()->max(Carbon::create($year, 1, 1));
            $yearEnd = $end->copy()->min(Carbon::create($year, 12, 31));
            $requestedDays = (int) $yearStart->diffInDays($yearEnd) + 1;
            $usedDays = Demande::getTotalDaysForUser($userId, $year, $excludeDemandeId);

            if ($usedDays + $requestedDays > self::MAX_DAYS_PER_YEAR) {
                abort(response()->json([
                    'message' => "Impossible d'accepter cette demande. L'employé dépasserait la limite de "
                        . self::MAX_DAYS_PER_YEAR . " jours pour {$year}.",
                ], 422));
            }
        }
    }
}
