<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Models\CompanySetting;
use App\Models\Demande;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Leave as iCalendar (RFC 5545) for calendar subscriptions:
 * - "me": the person's own leave, with its type; waiting ones as tentative;
 * - "team": accepted leave of the people they approve (their team, and a team
 *   delegated to them today), shown as "Name – absent(e)" without the type.
 * From a year ago to two years ahead.
 */
class IcalFeed
{
    public function __construct(private readonly LeaveCalculator $calculator) {}

    public function mine(User $user): string
    {
        $leaves = $this->leaves(collect([$user->id]), [...LeaveStatus::booked(), ...LeaveStatus::awaiting()]);

        return $this->calendar('Mes congés', $leaves->map(function (Demande $leave) {
            $waiting = in_array($leave->etat, LeaveStatus::awaiting(), true);

            return $this->event(
                $leave,
                $leave->type_conge->label().$this->halfDays($leave).($waiting ? ' (en attente)' : ''),
                $waiting,
            );
        }));
    }

    public function team(User $user): string
    {
        $ids = User::active()
            // Grouped, so "active" applies to both.
            ->where(fn ($query) => $query->where('manager_id', $user->id)->orWhereIn('manager_id', $user->delegatingManagerIds()))
            ->whereKeyNot($user->id)
            ->pluck('id');

        $leaves = $this->leaves($ids, LeaveStatus::booked());

        return $this->calendar('Absences de mon équipe', $leaves->map(fn (Demande $leave) => $this->event(
            $leave,
            // Names only: the type (sick leave…) never leaves the app.
            "{$leave->user->name} – absent(e)".$this->halfDays($leave),
            false,
        )));
    }

    /** @return Collection<int, Demande> */
    private function leaves(Collection $userIds, array $statuses): Collection
    {
        return Demande::with('user:id,name')
            ->whereIn('user_id', $userIds)
            ->whereIn('etat', $statuses)
            ->whereDate('date_fin', '>=', today()->subYear())
            ->whereDate('date_debut', '<=', today()->addYears(2))
            ->orderBy('date_debut')
            ->get();
    }

    private function halfDays(Demande $leave): string
    {
        return match (true) {
            $leave->start_half && $leave->end_half => ' (début l\'après-midi, fin à midi)',
            (bool) $leave->start_half => ' (à partir de l\'après-midi)',
            (bool) $leave->end_half => ' (jusqu\'à midi)',
            default => '',
        };
    }

    /** An all-day event; DTEND is the day after the last day (exclusive). */
    private function event(Demande $leave, string $summary, bool $tentative): string
    {
        $start = Carbon::parse($leave->date_debut);
        $end = Carbon::parse($leave->date_fin)->addDay();

        return implode("\r\n", array_map([$this, 'fold'], [
            'BEGIN:VEVENT',
            'UID:conge-'.$leave->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$leave->updated_at->utc()->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$start->format('Ymd'),
            'DTEND;VALUE=DATE:'.$end->format('Ymd'),
            'SUMMARY:'.$this->escape($summary),
            'STATUS:'.($tentative ? 'TENTATIVE' : 'CONFIRMED'),
            // Free time for others' calendars: someone on leave is simply away.
            'TRANSP:TRANSPARENT',
            'END:VEVENT',
        ]));
    }

    /** @param Collection<int, string> $events */
    private function calendar(string $name, Collection $events): string
    {
        $company = CompanySetting::current()->name ?? config('app.name');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$this->escape($company).'//Conges//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            $this->fold('X-WR-CALNAME:'.$this->escape("{$name} – {$company}")),
            'X-WR-TIMEZONE:'.config('conge.reminders.timezone', 'Africa/Casablanca'),
            // Ask calendar apps to refresh a few times a day.
            'REFRESH-INTERVAL;VALUE=DURATION:PT4H',
            'X-PUBLISHED-TTL:PT4H',
        ];

        return implode("\r\n", [...$lines, ...$events->all(), 'END:VCALENDAR'])."\r\n";
    }

    /** Text values escape backslashes, commas, semicolons and line breaks. */
    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $text);
    }

    /** Lines longer than 75 octets continue on the next line after a space (multi-byte safe). */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $parts = [];
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($current.$char) > ($parts === [] ? 75 : 74)) {
                $parts[] = $current;
                $current = '';
            }
            $current .= $char;
        }
        $parts[] = $current;

        return implode("\r\n ", $parts);
    }
}
