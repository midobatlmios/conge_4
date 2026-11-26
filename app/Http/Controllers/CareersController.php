<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\JobOffer;
use App\Notifications\AppNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public careers page (no sign-in): published offers and the application
 * form. Applying needs consent to the processing of the data (loi 09-08); a
 * hidden field and a rate limit (routes) keep robots out.
 */
class CareersController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Careers/Index', [
            'offers' => JobOffer::open()->with('department:id,name')->latest('published_at')->get()->map(fn (JobOffer $o) => $this->card($o)),
        ]);
    }

    public function show(string $slug): Response
    {
        $offer = JobOffer::where('slug', $slug)->with('department:id,name')->firstOrFail();
        abort_unless($offer->status !== 'draft', 404);

        return Inertia::render('Careers/Show', [
            'offer' => [...$this->card($offer), 'description' => $offer->description, 'requirements' => $offer->requirements, 'open' => $offer->isOpen()],
            'cvMaxKb' => config('conge.cv_max_kb', 5120),
            'privacyContact' => CompanySetting::current()->privacy_contact,
        ]);
    }

    public function apply(Request $request, string $slug): RedirectResponse
    {
        $offer = JobOffer::where('slug', $slug)->firstOrFail();
        abort_unless($offer->isOpen(), 410, 'Cette offre n’accepte plus de candidatures.');

        // Robots fill every field: a filled hidden field ends here, as if it worked.
        if (filled($request->input('website'))) {
            return back()->with('success', 'Candidature envoyée.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:3000'],
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:'.config('conge.cv_max_kb', 5120)],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Merci d’accepter le traitement de vos données pour envoyer votre candidature.'], [
            'name' => 'nom', 'email' => 'e-mail', 'phone' => 'téléphone', 'message' => 'message', 'cv' => 'CV',
        ]);

        if ($offer->candidates()->where('email', $validated['email'])->exists()) {
            return back()->withErrors(['email' => 'Une candidature avec cette adresse existe déjà pour cette offre.']);
        }

        $candidate = $offer->candidates()->create([
            ...collect($validated)->only(['name', 'email', 'phone', 'message'])->all(),
            ...RecruitmentController::storeCv($request->file('cv'), $offer),
            'source' => 'careers_page',
            'stage' => 'new',
            'stage_changed_at' => now(),
            'consent_at' => now(),
        ]);

        $recipients = AppNotice::hr()->merge(array_filter([$offer->hiringManager]))->unique('id');
        \Illuminate\Support\Facades\Notification::send($recipients, new AppNotice('Nouvelle candidature de :name pour « :offer ».', ['name' => $candidate->name, 'offer' => $offer->title], "/recrutement/candidats/{$candidate->id}"));

        return back()->with('success', 'Merci ! Votre candidature a bien été envoyée. Nous revenons vers vous rapidement.');
    }

    /** @return array<string, mixed> */
    private function card(JobOffer $offer): array
    {
        return [
            'title' => $offer->title,
            'slug' => $offer->slug,
            'department' => $offer->department?->name,
            'location' => $offer->location,
            'contract' => $offer->contract_type->label(),
            'published_at' => $offer->published_at?->toDateString(),
            'closes_on' => $offer->closes_on?->toDateString(),
        ];
    }
}
