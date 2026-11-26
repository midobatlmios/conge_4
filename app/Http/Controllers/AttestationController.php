<?php

namespace App\Http\Controllers;

use App\Enums\AttestationStatus;
use App\Enums\AttestationType;
use App\Enums\Role;
use App\Models\Attestation;
use App\Models\PersonalDataAccess;
use App\Models\User;
use App\Notifications\AttestationHandled;
use App\Notifications\AttestationRequested;
use App\Services\AttestationIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Notifications\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Attestations de travail / de salaire. The employee asks (Paramètres → Mes
 * attestations); Admin or RH issues the PDF or refuses (never their own);
 * Paie reads the list. HR can also issue one directly from the employee page.
 */
class AttestationController extends Controller
{
    public function __construct(private readonly AttestationIssuer $issuer) {}

    /** The employee's own attestations and the request form. */
    public function mine(Request $request): Response
    {
        return Inertia::render('settings/attestations', [
            'attestations' => $request->user()->attestations()->latest()->get()->map->present(),
            'types' => AttestationType::options(),
        ]);
    }

    public function request(Request $request): RedirectResponse
    {
        $employee = $request->user();
        $validated = $this->validateRequest($request);
        $type = AttestationType::from($validated['type']);

        if ($employee->attestations()->where('type', $type)->where('status', AttestationStatus::Requested)->exists()) {
            return back()->withErrors(['type' => "Vous avez déjà une demande d'{$this->lower($type)} en cours."]);
        }

        $attestation = $employee->attestations()->create([
            'type' => $type,
            'purpose' => $validated['purpose'] ?? null,
            'status' => AttestationStatus::Requested,
            'requested_by' => $employee->id,
        ]);

        User::active()
            ->whereIn('role', [Role::Admin, Role::Hr])
            ->whereKeyNot($employee->id)
            ->get()
            ->each(fn (User $hr) => $this->send($hr, new AttestationRequested($attestation)));

        return back()->with('success', 'Demande envoyée au service RH.');
    }

    /** Every attestation, requests waiting first (Admin, RH, Paie). */
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Attestations', [
            'attestations' => Attestation::with(['user:id,name', 'handler:id,name'])
                ->orderByRaw('status = ? desc', [AttestationStatus::Requested->value])
                ->latest()
                ->limit(200)
                ->get()
                ->map(fn (Attestation $attestation) => [
                    ...$attestation->present(),
                    // What blocks issuing it, so HR knows before clicking.
                    'missing' => $attestation->status === AttestationStatus::Requested
                        ? $this->issuer->missing($attestation->user, $attestation->type)
                        : [],
                ]),
        ]);
    }

    /** HR issues an attestation directly (asked by phone, at the desk…). */
    public function storeForEmployee(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403);

        $validated = $this->validateRequest($request, withNet: true);

        $attestation = $user->attestations()->make([
            'type' => $validated['type'],
            'purpose' => $validated['purpose'] ?? null,
            'status' => AttestationStatus::Requested,
            'requested_by' => $request->user()->id,
        ]);

        // Saved only once issued: nothing is left half-done if data is missing.
        $this->issuer->issue($attestation, $request->user(), $this->net($validated));

        return back()->with('success', "{$attestation->type->label()} n° {$attestation->number} délivrée.");
    }

    public function issue(Request $request, Attestation $attestation): RedirectResponse
    {
        $this->ensureHandledByOther($request, $attestation);

        $validated = $request->validate(['net_salary' => ['nullable', 'numeric', 'min:0', 'max:10000000']], [], ['net_salary' => 'salaire net']);

        $this->issuer->issue($attestation, $request->user(), $this->net($validated));
        $this->send($attestation->user, new AttestationHandled($attestation));

        return back()->with('success', "{$attestation->type->label()} n° {$attestation->number} délivrée.");
    }

    public function refuse(Request $request, Attestation $attestation): RedirectResponse
    {
        $this->ensureHandledByOther($request, $attestation);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'motif']);

        $attestation->update([
            'status' => AttestationStatus::Refused,
            'refusal_reason' => $validated['reason'],
            'handled_by' => $request->user()->id,
        ]);
        $this->send($attestation->user, new AttestationHandled($attestation));

        return back()->with('success', 'Demande refusée.');
    }

    /** The issued PDF: its employee and HR (incl. Paie). */
    public function pdf(Request $request, Attestation $attestation): HttpResponse
    {
        $viewer = $request->user();
        abort_unless($viewer->canViewHr() || $attestation->user_id === $viewer->id, 403);
        abort_unless($attestation->status === AttestationStatus::Issued, 404);
        PersonalDataAccess::record($viewer, $attestation->user, 'attestation_pdf', $attestation->number);

        return $this->issuer->pdf($attestation)->stream($this->issuer->fileName($attestation));
    }

    /** @return array<string, mixed> */
    private function validateRequest(Request $request, bool $withNet = false): array
    {
        return $request->validate([
            'type' => ['required', Rule::enum(AttestationType::class)],
            'purpose' => ['nullable', 'string', 'max:150'],
            ...($withNet ? ['net_salary' => ['nullable', 'numeric', 'min:0', 'max:10000000']] : []),
        ], [], ['type' => "type d'attestation", 'purpose' => 'motif', 'net_salary' => 'salaire net']);
    }

    private function net(array $validated): ?float
    {
        return isset($validated['net_salary']) ? (float) $validated['net_salary'] : null;
    }

    /** A request waiting for HR, handled by someone other than the employee. */
    private function ensureHandledByOther(Request $request, Attestation $attestation): void
    {
        abort_if($attestation->user_id === $request->user()->id, 403);

        if ($attestation->status !== AttestationStatus::Requested) {
            abort(422, 'Cette demande a déjà été traitée.');
        }
    }

    private function lower(AttestationType $type): string
    {
        return lcfirst($type->label());
    }

    private function send(User $to, Notification $notification): void
    {
        try {
            $to->notify($notification);
        } catch (TransportExceptionInterface $e) {
            // Saved and notified in the app; a mail outage must not turn it into an error.
            report($e);
        }
    }
}
