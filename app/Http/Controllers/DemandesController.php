<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Models\User;
use App\Notifications\DemandeStatusChanged;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DemandesController extends Controller
{
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
                $remainingDaysByUser[$demande->user_id] = Demande::maxDaysPerYear()
                    - Demande::getTotalDaysForUser($demande->user_id, now()->year);
            }

            $demande->user->remaining_days = $remainingDaysByUser[$demande->user_id];
        });

        return Inertia::render('Demandes', [
            'demandes' => $demandes,
            // Employees an admin can file a request for.
            'users' => $user->role === 'admin' ? User::orderBy('name')->get(['id', 'name']) : [],
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

        $validated = $this->validateLeaveData($request, $isAdmin, $demande->user_id, $demande->id);
        $status = $isAdmin ? $validated['etat'] : $demande->etat;

        if ($status === 'acceptée' && Demande::countsTowardsAllowance($validated['type_conge'])) {
            $this->ensureAnnualLimit(
                $demande->user_id,
                $validated['type_conge'],
                $validated['date_debut'],
                $validated['date_fin'],
                $demande->id
            );
        }

        $oldStatus = $demande->etat;
        // date_demande is the submission date: it is set once on creation and never edited.
        $demande->update([
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
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->role === 'admin';

        // Admins may file a request on behalf of an employee; employees only for themselves.
        $request->validate($isAdmin ? ['user_id' => ['nullable', 'integer', 'exists:users,id']] : []);
        $ownerId = $isAdmin && $request->filled('user_id') ? (int) $request->input('user_id') : $user->id;

        $validated = $this->validateLeaveData($request, $isAdmin, $ownerId, null, requireStatus: false);

        Demande::create([
            'date_demande' => now()->toDateString(),
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
            'type_conge' => $validated['type_conge'],
            'etat' => 'en attente',
            'user_id' => $ownerId,
            'comment' => $validated['comment'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Demande ajoutée avec succès',
            ], 201);
        }

        return redirect()->back()->with('success', 'Demande ajoutée avec succès');
    }

    private function validateLeaveData(
        Request $request,
        bool $isAdmin,
        int $ownerId,
        ?int $excludeDemandeId,
        bool $requireStatus = true
    ): array {
        $rules = [
            // Employees can't request leave in the past; admins may record it after the fact.
            'date_debut' => $isAdmin ? ['required', 'date'] : ['required', 'date', 'after_or_equal:today'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'type_conge' => ['required', Rule::in(Demande::TYPES)],
            'comment' => ['required_if:type_conge,recuperation', 'nullable', 'string', 'max:1000'],
        ];

        if ($isAdmin && $requireStatus) {
            $rules['etat'] = ['required', 'in:en attente,acceptée,refusée'];
        }

        $messages = [
            'date_debut.after_or_equal' => 'La date de début ne peut pas être dans le passé.',
        ];

        return Validator::make($request->all(), $rules, $messages)
            ->after(function ($validator) use ($request, $ownerId, $excludeDemandeId) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $days = Demande::countWorkingDays($request->date_debut, $request->date_fin);

                if ($days === 0) {
                    $validator->errors()->add(
                        'date_fin',
                        'La période choisie ne contient aucun jour ouvré.'
                    );
                    return;
                }

                $maximum = config('conge.type_limits')[$request->type_conge] ?? null;

                if ($maximum !== null && $days > $maximum) {
                    $validator->errors()->add(
                        'date_fin',
                        "Cette demande ne peut pas dépasser {$maximum} jours ouvrés."
                    );
                }

                $overlap = Demande::overlapping(
                    $ownerId,
                    $request->date_debut,
                    $request->date_fin,
                    $excludeDemandeId
                )->first();

                if ($overlap) {
                    $from = Carbon::parse($overlap->date_debut)->format('d/m/Y');
                    $to = Carbon::parse($overlap->date_fin)->format('d/m/Y');
                    $validator->errors()->add(
                        'date_debut',
                        "Cette période chevauche une autre demande (du {$from} au {$to})."
                    );
                }
            })
            ->validate();
    }

    private function ensureAnnualLimit(
        int $userId,
        string $type,
        string $startDate,
        string $endDate,
        ?int $excludeDemandeId = null
    ): void {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $maxDays = Demande::maxDaysPerYear();

        for ($year = $start->year; $year <= $end->year; $year++) {
            $requestedDays = Demande::allowanceDays($type, $startDate, $endDate, $year);
            $usedDays = Demande::getTotalDaysForUser($userId, $year, $excludeDemandeId);

            if ($usedDays + $requestedDays > $maxDays) {
                abort(response()->json([
                    'message' => "Impossible d'accepter cette demande. L'employé dépasserait la limite de "
                        . "{$maxDays} jours de congé annuel pour {$year}.",
                ], 422));
            }
        }
    }
}
