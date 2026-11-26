<?php

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Attestation;
use App\Models\Demande;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\LeaveCalculator;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('uploads');
    $this->seed(DemoSeeder::class);
});

test('the demo company has staff, managers, contracts, folders and a year of leave', function () {
    expect(User::count())->toBe(27)
        ->and(User::whereNotNull('deactivated_at')->count())->toBe(1)
        ->and(User::where('email', 'admin@'.DemoSeeder::DOMAIN)->sole()->isAdmin())->toBeTrue()
        ->and(User::where('email', 'employe@'.DemoSeeder::DOMAIN)->sole()->manager->email)->toBe('manager@'.DemoSeeder::DOMAIN)
        ->and(Demande::count())->toBeGreaterThan(80)
        ->and(Demande::where('type_conge', LeaveType::Sick)->count())->toBeGreaterThan(5)
        ->and(EmployeeDocument::count())->toBeGreaterThan(50)
        ->and(Attestation::count())->toBe(6)
        ->and(App\Models\CareerChange::count())->toBe(6)
        // A month and more of clocking, with corrections waiting for the sales manager.
        ->and(App\Models\AttendanceRecord::count())->toBeGreaterThan(400)
        ->and(App\Models\AttendanceCorrection::pending()->count())->toBe(2)
        // Last year closed, this year in progress with two recommendations for HR.
        ->and(App\Models\ReviewCampaign::count())->toBe(2)
        ->and(App\Models\Review::where('recommendation_status', 'pending')->count())->toBe(2)
        // Three offers on the careers page, candidates at every stage.
        ->and(App\Models\JobOffer::open()->count())->toBe(3)
        ->and(App\Models\Candidate::distinct()->count('stage'))->toBe(6);

    // Sara was promoted: her salary before the promotion is still known (payroll cost).
    $sara = User::with(['contracts', 'careerChanges'])->where('email', 'employe@'.DemoSeeder::DOMAIN)->sole();
    expect($sara->salaryOn(now()->subYear()))->toBe(10000.0)
        ->and($sara->salaryOn(today()))->toBe(12500.0);

    User::all()->each(fn (User $user) => expect($user->contracts()->exists())->toBeTrue()
        ->and($user->profile)->not->toBeNull());
});

test('demo leave follows the app rules: no overlap, annual leave within what is earned', function () {
    $calculator = app(LeaveCalculator::class);

    foreach (User::all() as $user) {
        $active = $user->demandes()->whereNotIn('etat', LeaveStatus::inactive())->orderBy('date_debut')->get();

        $active->each(function (Demande $leave) use ($user) {
            expect(Demande::overlapping($user->id, $leave->date_debut, $leave->date_fin, $leave->id)->exists())
                ->toBeFalse("{$user->name}: overlapping leave on {$leave->date_debut}");
        });

        if ($user->isActive()) {
            expect($calculator->balance($user)['remainingDays'] ?? 0)->toBeGreaterThanOrEqual(0);
        }
    }
});

test('the showcase cases are there for HR', function () {
    $rh = User::where('email', 'rh@'.DemoSeeder::DOMAIN)->sole();

    $this->actingAs($rh)->get('/admin/overview')->assertInertia(fn ($page) => $page
        ->where('cnssPending', fn ($rows) => count($rows) >= 1)
        ->where('contractAlerts', fn ($rows) => count($rows) >= 2)
        ->where('expiringDocuments', fn ($rows) => count($rows) >= 3)
        ->where('counts.pending', fn ($n) => $n >= 2)
        ->where('counts.cancellations', 1));

    $this->actingAs($rh)->get('/admin/attestations')->assertInertia(fn ($page) => $page->has('attestations', 6));
});

test('demo:reset refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('demo:reset', ['--force' => true])->assertFailed();
    expect(User::count())->toBe(27);
});
