<?php

use App\Enums\Role;
use App\Models\Candidate;
use App\Models\CandidateInterview;
use App\Models\EmployeeDocument;
use App\Models\JobOffer;
use App\Models\User;
use App\Notifications\EmployeeInvitation;
use App\Services\DataRetention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('uploads'));

function openOffer(array $attributes = []): JobOffer
{
    return JobOffer::create(['title' => 'Comptable', 'contract_type' => 'cdi', 'description' => 'Tenue de la comptabilité.', 'status' => 'published', 'published_at' => now(), ...$attributes]);
}

function application(array $overrides = []): array
{
    return ['name' => 'Yasmine Alami', 'email' => 'yasmine@example.test', 'cv' => UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf'), 'consent' => '1', ...$overrides];
}

test('the careers page lists open offers only; anyone can apply with a CV and consent', function () {
    $offer = openOffer();
    openOffer(['title' => 'Brouillon', 'status' => 'draft']);
    openOffer(['title' => 'Expirée', 'closes_on' => today()->subDay()]);

    $this->get('/carrieres')->assertInertia(fn (Assert $page) => $page->has('offers', 1)->where('offers.0.title', 'Comptable'));

    $this->post("/carrieres/{$offer->slug}", application(['consent' => null]))->assertSessionHasErrors('consent');
    $this->post("/carrieres/{$offer->slug}", application())->assertSessionHas('success');
    $this->post("/carrieres/{$offer->slug}", application())->assertSessionHasErrors('email');

    $candidate = Candidate::sole();
    expect($candidate->stage)->toBe('new')->and($candidate->source)->toBe('careers_page')->and($candidate->consent_at)->not->toBeNull();
    Storage::disk('uploads')->assertExists($candidate->cv_path);
});

test('robots filling the hidden field are ignored; drafts and closed offers take no application', function () {
    $offer = openOffer();
    $this->post("/carrieres/{$offer->slug}", application(['website' => 'http://spam.test']))->assertSessionHas('success');
    expect(Candidate::count())->toBe(0);

    $this->get('/carrieres/'.openOffer(['status' => 'draft'])->slug)->assertNotFound();
    $this->post('/carrieres/'.openOffer(['status' => 'closed'])->slug, application())->assertStatus(410);
});

test('the hiring manager sees their offer and evaluates; only HR moves candidates', function () {
    $manager = User::factory()->create(['role' => Role::User]);
    $offer = openOffer(['hiring_manager_id' => $manager->id]);
    $candidate = $offer->candidates()->create(['name' => 'A', 'email' => 'a@example.test']);
    $interview = CandidateInterview::create(['candidate_id' => $candidate->id, 'scheduled_at' => now()]);
    $other = User::factory()->create(['role' => Role::User]);

    $this->actingAs($manager)->get("/recrutement/offres/{$offer->id}")->assertOk();
    $this->actingAs($other)->get("/recrutement/offres/{$offer->id}")->assertForbidden();
    $this->actingAs($other)->get('/recrutement')->assertForbidden();

    $this->actingAs($manager)->put("/recrutement/entretiens/{$interview->id}", ['rating' => 'depasse', 'comment' => 'Très bon profil'])->assertSessionHasNoErrors();
    expect($interview->fresh()->rating->value)->toBe('depasse');

    $this->actingAs($manager)->put("/recrutement/candidats/{$candidate->id}/etape", ['stage' => 'offer'])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => Role::Hr]))->put("/recrutement/candidats/{$candidate->id}/etape", ['stage' => 'offer'])->assertSessionHas('success');
    expect($candidate->fresh()->stage)->toBe('offer');
});

test('hiring creates the account, record, contract and CV document, and sends the invitation', function () {
    Notification::fake();
    $rh = User::factory()->create(['role' => Role::Hr]);
    $manager = User::factory()->create(['role' => Role::User]);
    $offer = openOffer(['hiring_manager_id' => $manager->id]);
    $this->post("/carrieres/{$offer->slug}", application());
    $candidate = Candidate::sole();

    $this->actingAs($rh)->post("/recrutement/candidats/{$candidate->id}/embaucher", [
        'name' => 'Yasmine Alami', 'email' => 'yasmine.alami@atlas.test', 'job_title' => 'Comptable', 'manager_id' => $manager->id,
        'hire_date' => '2027-01-04', 'contract_type' => 'cdi', 'category' => 'employe', 'gross_salary' => 9000, 'trial_end' => '2027-02-18',
        'invite' => true, 'close_offer' => true,
    ])->assertSessionHasNoErrors();

    $employee = User::where('email', 'yasmine.alami@atlas.test')->sole();
    expect($employee->manager_id)->toBe($manager->id)
        ->and($employee->hire_date->toDateString())->toBe('2027-01-04')
        ->and((float) $employee->contracts()->sole()->gross_salary)->toBe(9000.0)
        ->and(EmployeeDocument::where('user_id', $employee->id)->sole()->category->value)->toBe('cv')
        ->and($candidate->fresh()->stage)->toBe('hired')
        ->and($candidate->fresh()->hired_user_id)->toBe($employee->id)
        ->and($offer->fresh()->status)->toBe('closed');
    Notification::assertSentTo($employee, EmployeeInvitation::class);
});

test('applications not hired are deleted with their CV after 2 years', function () {
    $offer = openOffer();
    $this->post("/carrieres/{$offer->slug}", application());
    $old = Candidate::sole();
    $this->travel(25)->months();

    expect(app(DataRetention::class)->due()['candidates'])->toHaveCount(1);
    app(DataRetention::class)->apply(null);

    expect(Candidate::count())->toBe(0);
    Storage::disk('uploads')->assertMissing($old->cv_path);
});
