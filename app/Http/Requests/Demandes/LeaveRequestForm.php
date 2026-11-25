<?php

namespace App\Http\Requests\Demandes;

use App\Enums\LeaveType;
use App\Models\Demande;
use App\Models\User;
use App\Services\LeaveCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation shared by creating and editing a leave request: dates, type,
 * working-day limits and overlap with the employee's other requests.
 */
abstract class LeaveRequestForm extends FormRequest
{
    /** Employee the request belongs to. */
    abstract public function ownerId(): int;

    /** Request being edited, so it does not conflict with itself. */
    abstract protected function excludedDemandeId(): ?int;

    protected function actor(): User
    {
        return $this->user();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Employees can't request leave in the past; admins may record it after the fact.
            // Sick leave can be declared up to 48 hours after it started.
            'date_debut' => $this->actor()->isHr()
                ? ['required', 'date']
                : ['required', 'date', 'after_or_equal:'.$this->earliestStart()],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'type_conge' => ['required', Rule::enum(LeaveType::class)],
            // Starts in the afternoon / ends at noon (ignored for whole-day leave types).
            'start_half' => ['sometimes', 'boolean'],
            'end_half' => ['sometimes', 'boolean'],
            'comment' => ['required_if:type_conge,'.LeaveType::Recovery->value, 'nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date_debut.after_or_equal' => $this->input('type_conge') === LeaveType::Sick->value
                ? 'Un arrêt maladie se déclare dans les 48 heures. Pour un arrêt plus ancien, adressez-vous au service RH.'
                : 'La date de début ne peut pas être dans le passé.',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->checkDays($validator);
                $this->checkOverlap($validator);
            },
        ];
    }

    /** Employees can't start leave in the past, except a sick leave declared within the allowed delay. */
    private function earliestStart(): string
    {
        return $this->input('type_conge') === LeaveType::Sick->value
            ? today()->subDays(config('conge.sick_leave_backdate_days'))->toDateString()
            : 'today';
    }

    public function leaveType(): LeaveType
    {
        return LeaveType::from($this->validated('type_conge'));
    }

    /**
     * Half-day flags to store: always false for leave types taken in whole days.
     *
     * @return array{start_half: bool, end_half: bool}
     */
    public function halfDays(): array
    {
        $allowed = app(LeaveCalculator::class)->allowsHalfDays(LeaveType::from($this->input('type_conge')));

        return [
            'start_half' => $allowed && $this->boolean('start_half'),
            'end_half' => $allowed && $this->boolean('end_half'),
        ];
    }

    private function checkDays(Validator $validator): void
    {
        $calculator = app(LeaveCalculator::class);
        ['start_half' => $startHalf, 'end_half' => $endHalf] = $this->halfDays();
        $start = $this->input('date_debut');
        $end = $this->input('date_fin');

        if ($calculator->workingDays($start, $end) === 0) {
            $validator->errors()->add('date_fin', 'La période choisie ne contient aucun jour ouvré.');

            return;
        }

        if ($startHalf && $endHalf && Carbon::parse($start)->isSameDay(Carbon::parse($end))) {
            $validator->errors()->add('date_fin', 'Pour une seule demi-journée, choisissez le matin ou l\'après-midi.');

            return;
        }

        if (($startHalf && ! $calculator->isWorkingDay($start)) || ($endHalf && ! $calculator->isWorkingDay($end))) {
            $validator->errors()->add('date_debut', 'Une demi-journée doit tomber sur un jour ouvré.');

            return;
        }

        $days = $calculator->leaveDays($start, $end, $startHalf, $endHalf);
        $limit = $calculator->typeLimit(LeaveType::from($this->input('type_conge')));

        if ($limit !== null && $days > $limit) {
            $validator->errors()->add('date_fin', "Cette demande ne peut pas dépasser {$limit} jours ouvrés.");
        }
    }

    private function checkOverlap(Validator $validator): void
    {
        ['start_half' => $startHalf, 'end_half' => $endHalf] = $this->halfDays();
        $overlap = Demande::firstClash(
            $this->ownerId(),
            $this->input('date_debut'),
            $this->input('date_fin'),
            $startHalf,
            $endHalf,
            $this->excludedDemandeId()
        );

        if ($overlap) {
            $from = Carbon::parse($overlap->date_debut)->format('d/m/Y');
            $to = Carbon::parse($overlap->date_fin)->format('d/m/Y');
            $validator->errors()->add('date_debut', "Cette période chevauche une autre demande (du {$from} au {$to}).");
        }
    }
}
