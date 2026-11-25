<?php

namespace App\Enums;

/** What a document in an employee's folder is. */
enum DocumentCategory: string
{
    case Contract = 'contrat';
    case Amendment = 'avenant';
    case IdCard = 'cin';
    case ResidencePermit = 'carte_sejour';
    case Diploma = 'diplome';
    case Resume = 'cv';
    case BankDetails = 'rib';
    case MedicalAptitude = 'aptitude_medicale';
    case Other = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Contrat de travail',
            self::Amendment => 'Avenant',
            self::IdCard => 'Copie CIN',
            self::ResidencePermit => 'Carte de séjour',
            self::Diploma => 'Diplôme',
            self::Resume => 'CV',
            self::BankDetails => 'Attestation RIB',
            self::MedicalAptitude => "Certificat d'aptitude médicale",
            self::Other => 'Autre',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $category) => ['value' => $category->value, 'label' => $category->label()], self::cases());
    }
}
