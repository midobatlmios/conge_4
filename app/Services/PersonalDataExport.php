<?php

namespace App\Services;

use App\Models\Attestation;
use App\Models\Demande;
use App\Models\DemandeDocument;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Models\EmploymentContract;
use App\Models\PersonalDataAccess;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Everything the app holds about one person (right of access, loi 09-08
 * art. 7), as a ZIP: a readable summary (resume.html), the same data as JSON
 * (donnees.json) and their files (documents/).
 */
class PersonalDataExport
{
    /** @return string Path of a temporary .zip file */
    public function zip(User $user): string
    {
        $data = $this->data($user);
        $path = tempnam(sys_get_temp_dir(), 'export');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('donnees.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('resume.html', view('privacy.export', ['data' => $data])->render());

        foreach ($user->documents()->get() as $document) {
            $this->addFile($zip, EmployeeDocument::DISK, $document->path, "documents/dossier/{$document->id}-{$document->original_name}");
        }

        $certificates = DemandeDocument::whereIn('demande_id', $user->demandes()->pluck('id'))->get();
        foreach ($certificates as $certificate) {
            $this->addFile($zip, DemandeDocument::DISK, $certificate->path, "documents/justificatifs/{$certificate->id}-{$certificate->original_name}");
        }

        $zip->close();

        return $path;
    }

    public static function fileName(User $user): string
    {
        return 'mes-donnees-'.Str::slug($user->name).'-'.now()->format('Y-m-d').'.zip';
    }

    /** @return array<string, mixed> */
    public function data(User $user): array
    {
        $user->loadMissing(['department:id,name', 'manager:id,name', 'profile']);
        $date = fn ($value) => $value ? Carbon::parse($value)->toDateString() : null;

        return [
            'exporte_le' => now()->toIso8601String(),
            'compte' => [
                'nom' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->label(),
                'matricule' => $user->matricule,
                'poste' => $user->job_title,
                'departement' => $user->department?->name,
                'responsable' => $user->manager?->name,
                'date_embauche' => $date($user->hire_date),
                'cree_le' => $date($user->created_at),
                'desactive_le' => $date($user->deactivated_at),
                'notice_acceptee_le' => $date($user->privacy_acknowledged_at),
            ],
            'donnees_personnelles' => $user->profile ? collect(EmployeeProfile::FIELDS)->mapWithKeys(fn (string $field) => [
                $field => match (true) {
                    $user->profile->{$field} instanceof \BackedEnum => $user->profile->{$field}->label(),
                    $user->profile->{$field} instanceof Carbon => $user->profile->{$field}->toDateString(),
                    default => $user->profile->{$field},
                },
            ])->all() : null,
            'contrats' => $user->contracts->map(fn (EmploymentContract $c) => [
                'type' => $c->type->label(),
                'categorie' => $c->category?->label(),
                'poste' => $c->job_title,
                'salaire_brut_mensuel' => $c->gross_salary === null ? null : (float) $c->gross_salary,
                'debut' => $date($c->start_date),
                'fin' => $date($c->end_date),
                'fin_periode_essai' => $date($c->trial_end),
            ])->all(),
            'demandes_de_conge' => $user->demandes()->orderBy('date_debut')->get()->map(fn (Demande $d) => [
                'type' => $d->type_conge->label(),
                'du' => $date($d->date_debut),
                'au' => $date($d->date_fin),
                'jours' => $d->nbr_jours,
                'etat' => $d->etat->value,
                'commentaire' => $d->comment,
                'motif_responsable' => $d->manager_comment,
            ])->all(),
            'attestations' => $user->attestations()->latest()->get()->map(fn (Attestation $a) => [
                'type' => $a->type->label(),
                'numero' => $a->number,
                'statut' => $a->status->label(),
                'motif' => $a->purpose,
                'demandee_le' => $date($a->created_at),
                'delivree_le' => $date($a->issued_at),
            ])->all(),
            'documents' => $user->documents()->get()->map(fn (EmployeeDocument $doc) => [
                'type' => $doc->category->label(),
                'intitule' => $doc->title,
                'fichier' => $doc->original_name,
                'expire_le' => $date($doc->expires_on),
            ])->all(),
            // Who in HR looked at or changed this person's data.
            'consultations' => PersonalDataAccess::with('actor:id,name')
                ->where('subject_id', $user->id)
                ->latest('created_at')
                ->limit(500)
                ->get()
                ->map(fn (PersonalDataAccess $access) => [
                    'le' => $access->created_at->toIso8601String(),
                    'par' => $access->actor?->name,
                    'action' => PersonalDataAccess::ACTIONS[$access->action] ?? $access->action,
                    'detail' => $access->detail,
                ])->all(),
        ];
    }

    private function addFile(ZipArchive $zip, string $disk, string $path, string $name): void
    {
        if (Storage::disk($disk)->exists($path)) {
            $zip->addFromString($name, Storage::disk($disk)->get($path));
        }
    }
}
