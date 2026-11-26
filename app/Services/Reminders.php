<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Demande;
use App\Models\User;
use App\Notifications\HrDailySummary;
use App\Notifications\ManagerPendingReminder;
use App\Notifications\PrivacyNoticeReminder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * The morning reminders (config conge.reminders): managers about team requests
 * waiting for them, HR a summary of what to do, employees about the data
 * protection notice. Each person gets each kind at most once a day; repeats
 * are spaced out. Nothing is sent on weekends and public holidays.
 */
class Reminders
{
    public function __construct(
        private readonly HrTodo $todo,
        private readonly LeaveCalculator $calculator,
        private readonly DataRetention $retention,
    ) {}

    /**
     * @param  bool  $send  false: only report what would be sent
     * @return array{managers: int, hr: int, notice: int, skipped: ?string}
     */
    public function run(bool $send = true): array
    {
        $done = ['managers' => 0, 'hr' => 0, 'notice' => 0, 'skipped' => null];

        if (! $this->calculator->isWorkingDay(today())) {
            return [...$done, 'skipped' => 'Jour non ouvré : aucun rappel.'];
        }

        $config = config('conge.reminders');

        // Managers: requests waiting for them, then again every few working days.
        foreach ($this->todo->waitingForManagers($config['request_waiting_days']) as $requests) {
            $manager = $requests->first()->user->manager;
            $delegate = $manager->currentDelegate();
            // During a delegation the delegate is reminded; their own requests stay with the manager.
            $groups = $delegate
                ? [[$delegate, $requests->reject(fn ($d) => $d->user_id === $delegate->id)], [$manager, $requests->filter(fn ($d) => $d->user_id === $delegate->id)]]
                : [[$manager, $requests]];

            foreach ($groups as [$recipient, $items]) {
                if ($items->isNotEmpty() && $this->due($recipient, 'manager_pending', $config['manager_repeat_days'])) {
                    $done['managers'] += $this->deliver($send, $recipient, 'manager_pending', new ManagerPendingReminder($items->values()));
                }
            }
        }

        // HR: one summary each working day there is something to do.
        $sections = $this->hrSections($config['request_waiting_days']);
        foreach (User::active()->whereIn('role', [Role::Admin, Role::Hr])->get() as $hr) {
            $mine = $hr->isAdmin() ? [...$sections, ...$this->adminSections()] : $sections;
            if ($mine !== [] && $this->due($hr, 'hr_summary', 1)) {
                $done['hr'] += $this->deliver($send, $hr, 'hr_summary', new HrDailySummary($mine));
            }
        }

        // Employees who have not accepted the notice after a few days, once a week.
        $late = User::active()
            ->whereNull('privacy_acknowledged_at')
            ->where('created_at', '<=', now()->subDays($config['notice_days']))
            ->get();
        foreach ($late as $employee) {
            if ($this->due($employee, 'privacy_notice', 5)) {
                $done['notice'] += $this->deliver($send, $employee, 'privacy_notice', new PrivacyNoticeReminder);
            }
        }

        return $done;
    }

    /**
     * What HR has to act on, as titled lists of readable lines (empty sections left out).
     *
     * @return list<array{title: string, items: list<string>}>
     */
    public function hrSections(int $waitingDays): array
    {
        $employees = User::active()->with('contracts')->get();
        $date = fn ($value) => Carbon::parse($value)->format('d/m/Y');
        $leave = fn (Demande $d) => "{$d->user?->name} : {$d->type_conge->label()} du {$date($d->date_debut)} au {$date($d->date_fin)}";

        $sections = [
            ['title' => 'Demandes en attente de décision', 'items' => $this->todo->waitingForHr($waitingDays)
                ->map(fn (Demande $d) => $leave($d).' (depuis '.$this->todo->waitingDays($d).' j ouvrés)')->all()],
            ['title' => 'Annulations demandées', 'items' => $this->todo->cancellations()->map($leave)->all()],
            ['title' => 'Attestations demandées', 'items' => $this->todo->attestations()
                ->map(fn ($a) => "{$a->user?->name} : {$a->type->label()} (le {$a->created_at->format('d/m/Y')})")->all()],
            ['title' => 'Dossiers CNSS à déposer', 'items' => array_map(
                fn (array $c) => "{$c['name']} : arrêt du {$date($c['start'])} au {$date($c['end'])}, ".($c['overdue'] ? 'délai dépassé depuis le ' : 'avant le ').$date($c['deadline']),
                $this->todo->cnss($employees),
            )],
            ['title' => 'Contrats à suivre', 'items' => array_map(fn (array $a) => match ($a['kind']) {
                'ended' => "{$a['name']} : {$a['contract']} terminé le {$date($a['date'])}, sans nouveau contrat",
                'contract_end' => "{$a['name']} : {$a['contract']} se termine le {$date($a['date'])}",
                default => "{$a['name']} : fin de période d'essai le {$date($a['date'])}",
            }, $this->todo->contracts($employees))],
            ['title' => 'Documents à renouveler', 'items' => $this->todo->documents($employees)
                ->map(fn ($doc) => "{$doc->user->name} : ".($doc->title ?: $doc->category->label()).($doc->expires_on->isPast() ? ' expiré le ' : ' expire le ').$doc->expires_on->format('d/m/Y'))->all()],
        ];

        return array_values(array_filter($sections, fn (array $section) => $section['items'] !== []));
    }

    /** @return list<array{title: string, items: list<string>}> Only for the Administrateur. */
    private function adminSections(): array
    {
        $due = $this->retention->due();
        $count = $due['certificates']->count() + $due['personal']->count() + $due['anonymize']->count();

        return $count === 0 ? [] : [[
            'title' => 'Données à supprimer (durées de conservation)',
            'items' => ["{$count} élément(s) à traiter dans « Protection des données »"],
        ]];
    }

    /** Not sent today, and the last one is at least $everyWorkingDays working days old. */
    private function due(User $user, string $type, int $everyWorkingDays): bool
    {
        $last = DB::table('reminder_logs')->where('user_id', $user->id)->where('type', $type)->max('sent_on');

        if ($last === null) {
            return true;
        }
        if (Carbon::parse($last)->isToday()) {
            return false;
        }

        return $this->calculator->workingDays(Carbon::parse($last)->addDay(), today()) >= $everyWorkingDays;
    }

    /** @return int 1 when sent (or would be, in a dry run) */
    private function deliver(bool $send, User $user, string $type, Notification $notification): int
    {
        if (! $send) {
            return 1;
        }

        try {
            $user->notify($notification);
        } catch (TransportExceptionInterface $e) {
            // The in-app part is saved; a mail outage must not stop the other reminders.
            report($e);
        }

        DB::table('reminder_logs')->insertOrIgnore(['user_id' => $user->id, 'type' => $type, 'sent_on' => today()->toDateString()]);

        return 1;
    }
}
