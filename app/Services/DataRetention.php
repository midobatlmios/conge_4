<?php

namespace App\Services;

use App\Enums\LeaveType;
use App\Models\Demande;
use App\Models\DemandeDocument;
use App\Models\DemandeEvent;
use App\Models\PersonalDataAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Retention of personal data (loi 09-08), durations in config/conge.php:
 * 1. medical certificates of sick leave, some years after the leave;
 * 2. personal data, document folder and attestations of a former employee,
 *    some years after departure;
 * 3. then the former employee's identity (anonymization). Leave history stays
 *    for payroll and statistics, without a name.
 * due() lists what is due; apply() does it and logs each step.
 */
class DataRetention
{
    /** @return array{certificates: Collection, personal: Collection, anonymize: Collection} */
    public function due(): array
    {
        $years = config('conge.retention');

        return [
            'certificates' => DemandeDocument::query()
                ->whereHas('demande', fn (Builder $q) => $q
                    ->where('type_conge', LeaveType::Sick)
                    ->whereDate('date_fin', '<', today()->subYears($years['medical_certificates_years'])))
                ->with('demande.user:id,name')
                ->get(),
            'personal' => User::query()
                ->whereNotNull('deactivated_at')
                ->where('deactivated_at', '<', now()->subYears($years['personal_data_years']))
                ->where(fn (Builder $q) => $q->has('profile')->orHas('documents')->orHas('attestations'))
                ->get(['id', 'name', 'deactivated_at']),
            'anonymize' => User::query()
                ->whereNotNull('deactivated_at')
                ->whereNull('anonymized_at')
                ->where('deactivated_at', '<', now()->subYears($years['anonymize_after_years']))
                ->get(['id', 'name', 'deactivated_at']),
        ];
    }

    /** @return array{certificates: int, personal: int, anonymize: int} How many of each were handled. */
    public function apply(?User $by): array
    {
        $due = $this->due();

        foreach ($due['certificates'] as $certificate) {
            $owner = $certificate->demande->user;
            $certificate->delete(); // the file goes with it
            PersonalDataAccess::record($by, $owner, 'retention', 'Certificat médical supprimé (fin de la durée de conservation)');
        }

        foreach ($due['personal'] as $person) {
            $this->erasePersonalData($person);
            PersonalDataAccess::record($by, $person, 'retention', 'Données personnelles, dossier et attestations supprimés');
        }

        foreach ($due['anonymize'] as $person) {
            $this->anonymize($person);
            PersonalDataAccess::record($by, $person, 'retention', 'Ancien employé anonymisé');
        }

        return array_map(fn (Collection $items) => $items->count(), $due);
    }

    private function erasePersonalData(User $person): void
    {
        $person->profile?->delete();
        // One by one so each file is deleted with its record.
        $person->documents()->get()->each->delete();
        $person->attestations()->delete();
    }

    /** Keeps the account and its leave history for statistics, without anything that identifies the person. */
    private function anonymize(User $person): void
    {
        DB::transaction(function () use ($person) {
            $this->erasePersonalData($person);

            $leaveIds = $person->demandes()->pluck('id');
            DemandeDocument::whereIn('demande_id', $leaveIds)->get()->each->delete();
            // Query updates: the audit trail must not copy the erased comments again.
            Demande::whereIn('id', $leaveIds)->update(['comment' => null, 'manager_comment' => null]);
            DemandeEvent::where('employee_id', $person->id)->update(['changes' => null]);
            $person->contracts()->update(['notes' => null, 'job_title' => null, 'gross_salary' => null]);
            $person->careerChanges()->update(['notes' => null, 'previous_job_title' => null, 'job_title' => null, 'previous_gross_salary' => null, 'gross_salary' => null]);
            $person->notifications()->delete();

            $person->forceFill([
                'name' => "Ancien employé n° {$person->id}",
                'email' => "ancien-{$person->id}@anonymise.invalid",
                'matricule' => null,
                'job_title' => null,
                'manager_id' => null,
                'password' => Str::random(40),
                'remember_token' => null,
                'anonymized_at' => now(),
            ])->save();
        });
    }
}
