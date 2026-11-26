<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\PersonalDataAccess;
use App\Models\User;
use App\Services\DataRetention;
use App\Services\PersonalDataExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Personal data protection (loi 09-08 / CNDP): the information notice every
 * employee accepts once, the export of one's data, and for the
 * Administrateur the register, the access log and the retention.
 */
class PrivacyController extends Controller
{
    /** The information notice (art. 5), and the export of one's own data. */
    public function notice(Request $request): Response
    {
        return Inertia::render('Privacy', [
            'notice' => $this->noticeFacts(),
            'acknowledgedAt' => $request->user()->privacy_acknowledged_at?->toIso8601String(),
        ]);
    }

    public function acknowledge(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['privacy_acknowledged_at' => now()])->save();

        return redirect()->intended(route('dashboard'))->with('success', 'Merci, votre prise de connaissance est enregistrée.');
    }

    /** Right of access: the employee downloads everything the app holds about them. */
    public function exportOwn(Request $request, PersonalDataExport $export): BinaryFileResponse
    {
        return $this->download($request->user(), $export);
    }

    /** HR exports an employee's data when they ask for it (role:admin,rh). */
    public function exportFor(Request $request, User $user, PersonalDataExport $export): BinaryFileResponse
    {
        PersonalDataAccess::record($request->user(), $user, 'export');

        return $this->download($user, $export);
    }

    /** The processing register and the data for the CNDP declaration (role:admin). */
    public function register(DataRetention $retention): Response
    {
        $employees = User::whereNull('anonymized_at')->whereNull('deactivated_at');
        $due = $retention->due();

        return Inertia::render('Admin/Privacy', [
            'notice' => $this->noticeFacts(),
            'acknowledged' => (clone $employees)->whereNotNull('privacy_acknowledged_at')->count(),
            'employees' => $employees->count(),
            'notAcknowledged' => User::active()->whereNull('privacy_acknowledged_at')->orderBy('name')->pluck('name'),
            'due' => [
                'certificates' => $due['certificates']->map(fn ($certificate) => [
                    'name' => $certificate->demande->user?->name,
                    'leave' => $certificate->demande->date_debut,
                ])->values(),
                'personal' => $due['personal']->map(fn (User $user) => ['name' => $user->name, 'left' => $user->deactivated_at->toDateString()])->values(),
                'anonymize' => $due['anonymize']->map(fn (User $user) => ['name' => $user->name, 'left' => $user->deactivated_at->toDateString()])->values(),
            ],
        ]);
    }

    /** Who viewed or changed whose personal data (role:admin; read-only). */
    public function log(Request $request): Response
    {
        $action = $request->string('action')->toString();

        $entries = PersonalDataAccess::with(['actor:id,name', 'subject:id,name'])
            ->when($request->integer('person'), fn ($q, $id) => $q->where(fn ($q) => $q->where('subject_id', $id)->orWhere('actor_id', $id)))
            ->when(array_key_exists($action, PersonalDataAccess::ACTIONS), fn ($q) => $q->where('action', $action))
            ->latest('created_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (PersonalDataAccess $access) => [
                'id' => $access->id,
                'at' => $access->created_at->toIso8601String(),
                'actor' => $access->actor?->name ?? 'Système',
                'subject' => $access->subject?->name,
                'subject_id' => $access->subject_id,
                'action' => PersonalDataAccess::ACTIONS[$access->action] ?? $access->action,
                'detail' => $access->detail,
                'ip' => $access->ip,
            ]);

        return Inertia::render('Admin/PrivacyLog', [
            'entries' => $entries,
            'actions' => PersonalDataAccess::ACTIONS,
            'people' => User::orderBy('name')->get(['id', 'name']),
            'filters' => ['person' => $request->integer('person') ?: null, 'action' => $action ?: null],
        ]);
    }

    /** Applies the retention periods now (role:admin). */
    public function applyRetention(Request $request, DataRetention $retention): RedirectResponse
    {
        $done = $retention->apply($request->user());
        $total = array_sum($done);

        return back()->with('success', $total === 0
            ? 'Rien à supprimer pour le moment.'
            : "Conservation appliquée : {$done['certificates']} certificat(s) médical(aux), {$done['personal']} dossier(s) d'anciens employés, {$done['anonymize']} anonymisation(s).");
    }

    /**
     * What the notice and the register say: the company, the contact, the
     * CNDP declaration and the retention periods.
     *
     * @return array<string, mixed>
     */
    private function noticeFacts(): array
    {
        $company = CompanySetting::current();

        return [
            'company' => $company->name,
            'address' => trim(($company->address ?? '').', '.($company->city ?? ''), ', '),
            'contact' => $company->privacy_contact ?: $company->email,
            'cndpNumber' => $company->cndp_number,
            'retention' => config('conge.retention'),
        ];
    }

    private function download(User $user, PersonalDataExport $export): BinaryFileResponse
    {
        return response()->download($export->zip($user), PersonalDataExport::fileName($user))->deleteFileAfterSend();
    }
}
