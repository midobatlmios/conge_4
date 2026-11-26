<?php

namespace Database\Seeders;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\CompanySetting;
use App\Models\User;
use App\Services\AttendanceCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo clocking for the Atlas Distribution employees: tracking starts on the
 * first day of last month, then everyone clocks in and out on their working
 * days (leave left out), with a few late arrivals, evenings of overtime,
 * Saturday mornings in the warehouse, forgotten departures, an unjustified
 * absence now and then, and corrections waiting for the manager.
 *
 * Part of `demo:reset`; it also runs alone on an existing demo database
 * (`php artisan db:seed --class=DemoAttendanceSeeder`): it only adds rows and
 * does nothing when clocking already exists.
 */
class DemoAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        if (AttendanceRecord::exists()) {
            $this->command?->warn('Des pointages existent déjà : rien n’est ajouté.');

            return;
        }

        mt_srand(4242);
        $since = today()->subMonthNoOverflow()->startOfMonth();
        $company = CompanySetting::query()->first() ?? new CompanySetting;
        $company->forceFill(['attendance_started_on' => $since->toDateString()])->save();

        $calculator = app(AttendanceCalculator::class);
        $calculator->refresh();
        $s = $calculator->schedule();

        $people = User::active()->with('department:id,name')->where('email', 'like', '%@'.DemoSeeder::DOMAIN)->get();
        $days = $calculator->days($people, $since, today());
        $nowMinutes = now()->hour * 60 + now()->minute;
        $time = fn (int $minutes) => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);

        foreach ($people as $person) {
            $warehouse = $person->department?->name === 'Logistique';

            foreach ($days[$person->id] ?? [] as $day) {
                $date = Carbon::parse($day['date']);
                $record = fn (int $in, ?int $out) => AttendanceRecord::create([
                    'user_id' => $person->id, 'date' => $day['date'], 'clock_in' => $time($in), 'clock_out' => $out === null ? null : $time(min($out, 23 * 60 + 59)),
                ]);

                // Saturday mornings in the warehouse: rest-day overtime (+50 %).
                if ($day['status'] === 'rest') {
                    if ($warehouse && $date->isSaturday() && $date->lt(today()) && mt_rand(1, 100) <= 30) {
                        $record(8 * 60 + mt_rand(-5, 10), 12 * 60 + 30 + mt_rand(0, 30));
                    }

                    continue;
                }

                // Only days expected at work (leave, future and untracked days are skipped).
                if (! in_array($day['status'], ['absent', 'not_yet'], true)) {
                    continue;
                }

                [$from, $to] = match ($day['leave_part']) {
                    'morning_off' => [$s['lunch_end'], $s['end']],
                    'afternoon_off' => [$s['start'], $s['lunch_start']],
                    default => [$s['start'], $s['end']],
                };
                $in = $from + (mt_rand(1, 100) <= 8 ? mt_rand(12, 40) : mt_rand(-15, 6));

                if ($day['status'] === 'not_yet') {
                    // Today: those already arrived at this hour.
                    if ($in <= $nowMinutes) {
                        $record($in, null);
                    }

                    continue;
                }

                $roll = mt_rand(1, 100);
                match (true) {
                    $roll <= 2 => null,                                                    // unjustified absence
                    $roll <= 4 => $record($in, null),                                      // forgot to clock out
                    $roll <= 14 => $record($in, $to + mt_rand(45, $warehouse ? 240 : 150)), // overtime evening
                    default => $record($in, $to + mt_rand(-5, 20)),
                };
            }
        }

        $this->corrections();
    }

    /** Corrections to show: two waiting for the sales manager, one already approved. */
    private function corrections(): void
    {
        $sara = User::where('email', 'employe@'.DemoSeeder::DOMAIN)->first();
        $amine = User::where('email', 'amine.kettani@'.DemoSeeder::DOMAIN)->first();
        $ghita = User::where('email', 'ghita.squalli@'.DemoSeeder::DOMAIN)->first();
        $manager = User::where('email', 'manager@'.DemoSeeder::DOMAIN)->first();

        // Sara forgot to clock out on her latest day at work.
        if ($sara && $last = AttendanceRecord::where('user_id', $sara->id)->whereDate('date', '<', today())->orderByDesc('date')->first()) {
            $last->update(['clock_out' => null]);
            AttendanceCorrection::create(['user_id' => $sara->id, 'date' => $last->date->toDateString(), 'clock_in' => $last->clock_in, 'clock_out' => '18:10', 'reason' => 'Oubli de pointage au départ.']);
        }

        // Amine went straight to a client: clocked late, asks for the start of the day.
        if ($amine && $day = AttendanceRecord::where('user_id', $amine->id)->whereDate('date', '<', today())->orderByDesc('clock_in')->first()) {
            AttendanceCorrection::create(['user_id' => $amine->id, 'date' => $day->date->toDateString(), 'clock_in' => '08:30', 'clock_out' => $day->clock_out, 'reason' => 'Rendez-vous chez un client à Rabat le matin, arrivé ensuite au bureau.']);
        }

        // Already decided: Ghita's correction approved by the manager.
        if ($ghita && $manager && $day = AttendanceRecord::where('user_id', $ghita->id)->whereDate('date', '<', today()->subWeek())->orderByDesc('date')->first()) {
            $day->update(['clock_in' => '08:25', 'clock_out' => '17:55', 'source' => 'correction']);
            AttendanceCorrection::create([
                'user_id' => $ghita->id, 'date' => $day->date->toDateString(), 'clock_in' => '08:25', 'clock_out' => '17:55',
                'reason' => 'Badge du téléphone oublié, pointage fait par erreur à midi.', 'status' => 'approved',
                'decided_by' => $manager->id, 'decided_at' => $day->date->copy()->addDay()->setTime(9, 15),
            ]);
        }
    }
}
