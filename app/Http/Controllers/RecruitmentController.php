<?php

namespace App\Http\Controllers;

use App\Enums\ContractType;
use App\Enums\DocumentCategory;
use App\Enums\ReviewRating;
use App\Enums\Role;
use App\Enums\StaffCategory;
use App\Models\Candidate;
use App\Models\CandidateInterview;
use App\Models\Department;
use App\Models\EmployeeDocument;
use App\Models\EmploymentContract;
use App\Models\JobOffer;
use App\Models\User;
use App\Notifications\AppNotice;
use App\Notifications\EmployeeInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Recruitment for HR (Admin, RH: everything) and hiring managers (the offers
 * they are named on: see candidates, open CVs, evaluate interviews). Only HR
 * moves candidates, schedules interviews and hires.
 */
class RecruitmentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isHr() || JobOffer::where('hiring_manager_id', $user->id)->exists(), 403);

        $offers = JobOffer::with(['department:id,name', 'hiringManager:id,name'])
            ->withCount(['candidates', 'candidates as active_count' => fn ($q) => $q->whereNotIn('stage', ['hired', 'rejected']), 'candidates as new_count' => fn ($q) => $q->where('stage', 'new')])
            ->when(! $user->isHr(), fn ($q) => $q->where('hiring_manager_id', $user->id))
            ->orderByRaw("case status when 'published' then 0 when 'draft' then 1 else 2 end")
            ->latest()
            ->get();

        $candidates = Candidate::whereIn('job_offer_id', $offers->modelKeys())->get(['id', 'stage', 'created_at', 'hired_at', 'source']);
        $hired = $candidates->where('stage', 'hired')->filter(fn ($c) => $c->hired_at);

        return Inertia::render('Recruitment/Index', [
            'offers' => $offers->map(fn (JobOffer $o) => [
                'id' => $o->id,
                'title' => $o->title,
                'department' => $o->department?->name,
                'contract' => $o->contract_type->label(),
                'status' => $o->status,
                'status_label' => JobOffer::STATUS_LABELS[$o->status],
                'open' => $o->isOpen(),
                'closes_on' => $o->closes_on?->toDateString(),
                'hiring_manager' => $o->hiringManager?->name,
                'candidates' => $o->candidates_count,
                'active' => $o->active_count,
                'new' => $o->new_count,
                'slug' => $o->slug,
            ]),
            'stats' => [
                'open_offers' => $offers->filter->isOpen()->count(),
                'applications_month' => $candidates->filter(fn ($c) => $c->created_at->gte(today()->startOfMonth()))->count(),
                'in_progress' => $candidates->whereNotIn('stage', ['hired', 'rejected'])->count(),
                'hired_year' => $hired->filter(fn ($c) => $c->hired_at->year === now()->year)->count(),
                // Days from application to hiring, on average.
                'days_to_hire' => $hired->isEmpty() ? null : (int) round($hired->avg(fn ($c) => $c->created_at->diffInDays($c->hired_at))),
                'from_careers_page' => $candidates->count() ? (int) round($candidates->where('source', 'careers_page')->count() / $candidates->count() * 100) : null,
            ],
            'canManage' => $user->isHr(),
        ]);
    }

    public function createOffer(Request $request): Response
    {
        abort_unless($request->user()->isHr(), 403);

        return Inertia::render('Recruitment/OfferForm', ['offer' => null, ...$this->offerOptions()]);
    }

    public function editOffer(Request $request, JobOffer $offer): Response
    {
        abort_unless($request->user()->isHr(), 403);

        return Inertia::render('Recruitment/OfferForm', [
            'offer' => [...$offer->only(['id', 'title', 'department_id', 'location', 'description', 'requirements', 'hiring_manager_id', 'status']), 'contract_type' => $offer->contract_type->value, 'closes_on' => $offer->closes_on?->toDateString()],
            ...$this->offerOptions(),
        ]);
    }

    public function storeOffer(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isHr(), 403);
        $offer = JobOffer::create([...$this->validateOffer($request), 'status' => 'draft', 'created_by' => $request->user()->id]);

        return redirect("/recrutement/offres/{$offer->id}")->with('success', 'Offre enregistrée en brouillon : publiez-la pour recevoir des candidatures.');
    }

    public function updateOffer(Request $request, JobOffer $offer): RedirectResponse
    {
        abort_unless($request->user()->isHr(), 403);
        $offer->update($this->validateOffer($request));

        return redirect("/recrutement/offres/{$offer->id}")->with('success', 'Offre modifiée.');
    }

    /** Publish on the careers page, or close (no more applications). */
    public function setStatus(Request $request, JobOffer $offer): RedirectResponse
    {
        abort_unless($request->user()->isHr(), 403);
        $status = $request->validate(['status' => ['required', Rule::in(['published', 'closed'])]])['status'];
        $offer->update(['status' => $status, 'published_at' => $status === 'published' ? ($offer->published_at ?? now()) : $offer->published_at]);

        return back()->with('success', $status === 'published' ? 'Offre publiée sur la page carrières.' : 'Offre clôturée.');
    }

    /** The offer's candidates as columns by stage. */
    public function showOffer(Request $request, JobOffer $offer): Response
    {
        abort_unless($offer->canBeSeenBy($request->user()), 403);
        $offer->load(['department:id,name', 'hiringManager:id,name']);

        return Inertia::render('Recruitment/Offer', [
            'offer' => [
                'id' => $offer->id,
                'title' => $offer->title,
                'slug' => $offer->slug,
                'department' => $offer->department?->name,
                'location' => $offer->location,
                'contract' => $offer->contract_type->label(),
                'status' => $offer->status,
                'status_label' => JobOffer::STATUS_LABELS[$offer->status],
                'open' => $offer->isOpen(),
                'closes_on' => $offer->closes_on?->toDateString(),
                'hiring_manager' => $offer->hiringManager?->name,
                'description' => $offer->description,
                'requirements' => $offer->requirements,
            ],
            'candidates' => $offer->candidates()->with('interviews')->latest()->get()->map(fn (Candidate $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'stage' => $c->stage,
                'source' => $c->source,
                'applied_on' => $c->created_at->toDateString(),
                'has_cv' => $c->cv_path !== null,
                'interviews' => $c->interviews->count(),
                // Best evaluation so far.
                'rating' => $c->interviews->pluck('rating')->filter()->map->label()->last(),
            ]),
            'stages' => collect(Candidate::STAGES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'canManage' => $request->user()->isHr(),
        ]);
    }

    /** HR adds a candidate received elsewhere (e-mail, LinkedIn, cooptation…). */
    public function storeCandidate(Request $request, JobOffer $offer): RedirectResponse
    {
        abort_unless($request->user()->isHr(), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:3000'],
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:'.config('conge.cv_max_kb', 5120)],
        ], [], ['name' => 'nom', 'phone' => 'téléphone', 'message' => 'notes', 'cv' => 'CV']);

        $candidate = $offer->candidates()->create([
            ...collect($validated)->except('cv')->all(),
            ...self::storeCv($request->file('cv'), $offer),
            'source' => 'manual',
            'stage_changed_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return redirect("/recrutement/candidats/{$candidate->id}")->with('success', 'Candidat ajouté.');
    }

    public function showCandidate(Request $request, Candidate $candidate): Response
    {
        $user = $request->user();
        $candidate->load(['offer.department:id,name', 'offer.hiringManager:id,name', 'interviews.interviewer:id,name', 'hiredUser:id,name']);
        abort_unless($candidate->offer->canBeSeenBy($user), 403);
        $offer = $candidate->offer;

        return Inertia::render('Recruitment/Candidate', [
            'candidate' => [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'message' => $candidate->message,
                'has_cv' => $candidate->cv_path !== null,
                'cv_name' => $candidate->cv_original_name,
                'source' => $candidate->source,
                'stage' => $candidate->stage,
                'stage_label' => Candidate::STAGES[$candidate->stage],
                'rejection_reason' => $candidate->rejection_reason,
                'applied_on' => $candidate->created_at->toDateString(),
                'hired_user' => $candidate->hiredUser ? ['id' => $candidate->hiredUser->id, 'name' => $candidate->hiredUser->name] : null,
            ],
            'offer' => [
                'id' => $offer->id,
                'title' => $offer->title,
                'department_id' => $offer->department_id,
                'department' => $offer->department?->name,
                'contract_type' => $offer->contract_type->value,
                'hiring_manager_id' => $offer->hiring_manager_id,
                'hiring_manager' => $offer->hiringManager?->name,
            ],
            'interviews' => $candidate->interviews->map(fn (CandidateInterview $i) => [
                'id' => $i->id,
                'scheduled_at' => $i->scheduled_at->format('Y-m-d\TH:i'),
                'interviewer' => $i->interviewer?->name,
                'mode' => $i->mode,
                'rating' => $i->rating?->value,
                'rating_label' => $i->rating?->label(),
                'comment' => $i->comment,
                // The interviewer, the hiring manager and HR evaluate.
                'can_evaluate' => $user->isHr() || $i->interviewer_id === $user->id || $offer->hiring_manager_id === $user->id,
            ]),
            'stages' => collect(Candidate::STAGES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'ratings' => ReviewRating::options(),
            'canManage' => $user->isHr(),
            // For scheduling (interviewers) and the hiring form.
            'people' => $user->isHr() ? User::active()->orderBy('name')->get(['id', 'name']) : [],
            'departments' => $user->isHr() ? Department::orderBy('name')->get(['id', 'name']) : [],
            'contractOptions' => EmploymentContractController::options(),
        ]);
    }

    public function cv(Request $request, Candidate $candidate): StreamedResponse
    {
        abort_unless($candidate->offer->canBeSeenBy($request->user()) && $candidate->cv_path, 403);

        return Storage::disk(Candidate::DISK)->response($candidate->cv_path, $candidate->cv_original_name ?? 'cv.pdf', ['Content-Type' => $candidate->cv_mime_type ?? 'application/pdf'], 'inline');
    }

    public function moveStage(Request $request, Candidate $candidate): RedirectResponse
    {
        abort_unless($request->user()->isHr() && $candidate->isActive(), 403);
        $validated = $request->validate([
            // Hiring goes through the hiring form.
            'stage' => ['required', Rule::in(['new', 'screening', 'interview', 'offer', 'rejected'])],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $candidate->update([
            'stage' => $validated['stage'],
            'stage_changed_at' => now(),
            'rejection_reason' => $validated['stage'] === 'rejected' ? ($validated['rejection_reason'] ?? null) : null,
        ]);

        return back()->with('success', 'Candidature passée en « '.Candidate::STAGES[$validated['stage']].' ».');
    }

    public function storeInterview(Request $request, Candidate $candidate): RedirectResponse
    {
        abort_unless($request->user()->isHr() && $candidate->isActive(), 403);
        $validated = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'interviewer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deactivated_at')],
            'mode' => ['nullable', 'string', 'max:100'],
        ], [], ['scheduled_at' => 'date et heure', 'interviewer_id' => 'interviewer', 'mode' => 'lieu ou visio']);

        $interview = $candidate->interviews()->create([...$validated, 'created_by' => $request->user()->id]);
        if ($interview->interviewer && ! $interview->interviewer->is($request->user())) {
            $interview->interviewer->notify(new AppNotice('Entretien de recrutement avec :name le :date (« :offer »).', [
                'name' => $candidate->name, 'date' => $interview->scheduled_at->format('d/m/Y H:i'), 'offer' => $candidate->offer->title,
            ], "/recrutement/candidats/{$candidate->id}"));
        }
        if (in_array($candidate->stage, ['new', 'screening'], true)) {
            $candidate->update(['stage' => 'interview', 'stage_changed_at' => now()]);
        }

        return back()->with('success', 'Entretien planifié.');
    }

    public function evaluateInterview(Request $request, CandidateInterview $interview): RedirectResponse
    {
        $user = $request->user();
        $offer = $interview->candidate->offer;
        abort_unless($user->isHr() || $interview->interviewer_id === $user->id || $offer->hiring_manager_id === $user->id, 403);
        $validated = $request->validate([
            'rating' => ['required', Rule::enum(ReviewRating::class)],
            'comment' => ['nullable', 'string', 'max:3000'],
        ], [], ['rating' => 'évaluation', 'comment' => 'commentaire']);

        $interview->update($validated);

        return back()->with('success', 'Évaluation enregistrée.');
    }

    /**
     * Hire: creates the employee account and record, the contract, copies the
     * CV into the document folder, and sends the invitation e-mail.
     */
    public function hire(Request $request, Candidate $candidate): RedirectResponse
    {
        abort_unless($request->user()->isHr() && $candidate->isActive(), 403);
        $type = ContractType::tryFrom((string) $request->input('contract_type'));
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'matricule' => ['nullable', 'string', 'max:50', 'unique:users,matricule'],
            'job_title' => ['required', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deactivated_at')],
            'hire_date' => ['required', 'date_format:Y-m-d'],
            'contract_type' => ['required', Rule::enum(ContractType::class)],
            'category' => ['nullable', Rule::enum(StaffCategory::class)],
            'gross_salary' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'end_date' => [$type?->requiresEndDate() ? 'required' : 'nullable', 'date_format:Y-m-d', 'after:hire_date'],
            'trial_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:hire_date'],
            'close_offer' => ['boolean'],
            'invite' => ['boolean'],
        ], ['email.unique' => 'Un compte existe déjà avec cette adresse e-mail.'], [
            'name' => 'nom', 'job_title' => 'poste', 'hire_date' => "date d'embauche", 'contract_type' => 'type de contrat',
            'gross_salary' => 'salaire', 'end_date' => 'date de fin', 'trial_end' => "fin de période d'essai",
        ]);

        $employee = DB::transaction(function () use ($request, $candidate, $validated) {
            $employee = new User;
            $employee->forceFill([
                ...collect($validated)->only(['name', 'email', 'matricule', 'job_title', 'department_id', 'manager_id', 'hire_date'])->all(),
                'role' => Role::User,
                // A random password: the employee chooses theirs from the invitation.
                'password' => Str::random(40),
            ])->save();

            EmploymentContract::create([
                'user_id' => $employee->id,
                'type' => $validated['contract_type'],
                'category' => $validated['category'] ?? null,
                'job_title' => $validated['job_title'],
                'gross_salary' => filled($validated['gross_salary'] ?? null) ? (string) $validated['gross_salary'] : null,
                'start_date' => $validated['hire_date'],
                'end_date' => $validated['end_date'] ?? null,
                'trial_end' => $validated['trial_end'] ?? null,
                'notes' => "Recrutement : {$candidate->offer->title}",
                'created_by' => $request->user()->id,
            ]);

            // The CV goes to the employee's document folder.
            if ($candidate->cv_path && Storage::disk(Candidate::DISK)->exists($candidate->cv_path)) {
                $path = "employee-documents/{$employee->id}/".Str::uuid().'.'.pathinfo($candidate->cv_path, PATHINFO_EXTENSION);
                Storage::disk(EmployeeDocument::DISK)->copy($candidate->cv_path, $path);
                EmployeeDocument::create([
                    'user_id' => $employee->id, 'uploaded_by' => $request->user()->id, 'category' => DocumentCategory::Resume,
                    'title' => 'CV de candidature', 'original_name' => $candidate->cv_original_name ?? 'cv', 'path' => $path,
                    'mime_type' => $candidate->cv_mime_type ?? 'application/pdf', 'size' => Storage::disk(EmployeeDocument::DISK)->size($path),
                ]);
            }

            $candidate->update(['stage' => 'hired', 'stage_changed_at' => now(), 'hired_user_id' => $employee->id, 'hired_at' => now()]);
            if ($validated['close_offer'] ?? false) {
                $candidate->offer->update(['status' => 'closed']);
            }

            return $employee;
        });

        $message = "{$employee->name} est embauché(e) : compte, dossier et contrat créés.";
        if ($validated['invite'] ?? false) {
            try {
                $employee->notify(new EmployeeInvitation(Password::broker('invitations')->createToken($employee)));
                $employee->forceFill(['invited_at' => now()])->save();
                $message .= ' Invitation envoyée.';
            } catch (TransportExceptionInterface $e) {
                report($e);

                return redirect("/admin/users/{$employee->id}")->withErrors(['invite' => "{$message} L'invitation n'a pas pu être envoyée : vérifiez l'envoi d'e-mails."]);
            }
        }

        return redirect("/admin/users/{$employee->id}")->with('success', $message);
    }

    /**
     * Stores an uploaded CV on the private disk under a random name.
     *
     * @return array<string, string|null>
     */
    public static function storeCv(?\Illuminate\Http\UploadedFile $file, JobOffer $offer): array
    {
        if (! $file) {
            return [];
        }

        return [
            'cv_path' => $file->storeAs("candidates/{$offer->id}", Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), Candidate::DISK),
            'cv_original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'cv_mime_type' => $file->getMimeType(),
        ];
    }

    /** @return array<string, mixed> */
    private function validateOffer(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'location' => ['nullable', 'string', 'max:100'],
            'contract_type' => ['required', Rule::enum(ContractType::class)],
            'description' => ['required', 'string', 'max:10000'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'hiring_manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deactivated_at')],
            'closes_on' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['title' => 'intitulé', 'contract_type' => 'type de contrat', 'description' => 'description', 'requirements' => 'profil recherché', 'hiring_manager_id' => 'responsable du recrutement', 'closes_on' => 'date limite']);
    }

    /** @return array<string, mixed> */
    private function offerOptions(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'people' => User::active()->orderBy('name')->get(['id', 'name']),
            'contractTypes' => ContractType::options(),
        ];
    }
}
