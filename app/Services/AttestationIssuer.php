<?php

namespace App\Services;

use App\Enums\AttestationStatus;
use App\Enums\AttestationType;
use App\Models\Attestation;
use App\Models\CompanySetting;
use App\Models\User;
use App\Support\FrenchNumber;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues attestations: checks that everything they print is known, gives them
 * a yearly number (ATT-2026-0001), freezes what they print, and renders the PDF.
 */
class AttestationIssuer
{
    /**
     * What is still missing before this attestation can be issued.
     *
     * @return list<string>
     */
    public function missing(User $employee, AttestationType $type): array
    {
        $missing = array_map(fn (string $label) => "entreprise : {$label}", CompanySetting::current()->missingRequired());
        $profile = $employee->profile;
        $contract = $employee->currentContract();

        if (blank($profile?->cin)) {
            $missing[] = 'CIN de l\'employé';
        }

        if (! $employee->hire_date && ! $contract) {
            $missing[] = "date d'embauche de l'employé";
        }

        if ($type === AttestationType::Salary && blank($contract?->gross_salary)) {
            $missing[] = 'salaire brut du contrat en cours';
        }

        return $missing;
    }

    /** @throws ValidationException when something the attestation prints is missing */
    public function issue(Attestation $attestation, User $hr, ?float $netSalary = null): Attestation
    {
        $employee = $attestation->user;
        $missing = $this->missing($employee, $attestation->type);

        if ($missing) {
            throw ValidationException::withMessages(['attestation' => 'À compléter avant de délivrer : '.implode(', ', $missing).'.']);
        }

        return DB::transaction(function () use ($attestation, $hr, $netSalary) {
            $issuedAt = now();

            $attestation->fill([
                'status' => AttestationStatus::Issued,
                'number' => $this->nextNumber($issuedAt->year),
                'issued_at' => $issuedAt,
                'handled_by' => $hr->id,
                'refusal_reason' => null,
            ]);
            $attestation->snapshot = $this->snapshot($attestation, $netSalary);
            $attestation->save();

            return $attestation;
        });
    }

    public function pdf(Attestation $attestation): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.attestation', [
            'a' => $attestation->snapshot,
            'logo' => CompanySetting::current()->logoDataUri(),
        ])
            ->setPaper('a4')
            // Embed only the characters used: ~50 KB instead of ~850 KB.
            ->setOption('isFontSubsettingEnabled', true);
    }

    public function fileName(Attestation $attestation): string
    {
        return "{$attestation->number}-".str($attestation->user->name)->slug().'.pdf';
    }

    private function nextNumber(int $year): string
    {
        $prefix = "ATT-{$year}-";
        $last = Attestation::where('number', 'like', "{$prefix}%")->lockForUpdate()->max('number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Everything the PDF prints, frozen at issue time.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Attestation $attestation, ?float $netSalary): array
    {
        $employee = $attestation->user;
        $profile = $employee->profile;
        $contract = $employee->currentContract();
        $company = CompanySetting::current();

        $start = $employee->hire_date ?? $employee->contracts->last()?->start_date;
        // An employee who left: until their last contract's end, or the day they were deactivated.
        $left = $employee->isActive() ? null : ($contract?->end_date ?? Carbon::parse($employee->deactivated_at));

        $gross = $attestation->type === AttestationType::Salary ? (float) $contract->gross_salary : null;

        return [
            'type' => $attestation->type->value,
            'title' => $attestation->type->label(),
            'number' => $attestation->number,
            'issued_on' => $attestation->issued_at->toDateString(),
            'purpose' => $attestation->purpose,
            'company' => $company->only(CompanySetting::FIELDS),
            'employee' => [
                'civility' => $profile?->gender?->title(),
                'name' => $employee->name,
                'cin' => $profile?->cin,
                'cnss_number' => $profile?->cnss_number,
                'matricule' => $employee->matricule,
                'job_title' => $contract?->job_title ?: $employee->job_title,
                'contract' => $contract?->type->label(),
                'category' => $contract?->category?->label(),
                'start' => $start?->toDateString(),
                'left' => $left?->toDateString(),
            ],
            'salary' => $gross === null ? null : [
                'gross' => $gross,
                'gross_words' => FrenchNumber::dirhams($gross),
                'net' => $netSalary,
                'net_words' => $netSalary === null ? null : FrenchNumber::dirhams($netSalary),
            ],
        ];
    }
}
