<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Rules\MoroccanRib;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * HR editing an employee's personal data. Access is checked by the role:admin,rh
 * route middleware. Every field is optional: the record is completed over time.
 */
class UpdateEmployeeProfileRequest extends FormRequest
{
    private const PHONE = 'regex:/^\+?[0-9][0-9 .\-]{7,19}$/';

    /** Nobody edits their own personal data (an RH changing their own RIB): another HR person does. */
    public function authorize(): bool
    {
        return ! $this->target()->is($this->user());
    }

    public function target(): User
    {
        return $this->route('user');
    }

    /** Identifiers are stored without spaces, dots or dashes (and the CIN in capitals). */
    protected function prepareForValidation(): void
    {
        $compact = fn (string $field) => $this->filled($field)
            ? preg_replace('/[\s.\-]/', '', (string) $this->input($field))
            : $this->input($field);

        $this->merge([
            'cin' => $this->filled('cin') ? strtoupper($compact('cin')) : $this->input('cin'),
            'cnss_number' => $compact('cnss_number'),
            'rib' => $compact('rib'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'gender' => ['nullable', Rule::enum(Gender::class)],
            // Minimum working age in Morocco is 15.
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before_or_equal:'.now()->subYears(15)->toDateString()],
            'birth_place' => ['nullable', 'string', 'max:100'],
            // CIN (1–2 letters + digits) or residence card of a foreign employee.
            'cin' => ['nullable', 'string', 'regex:/^[A-Z]{1,2}\d{1,7}$/'],
            'cnss_number' => ['nullable', 'regex:/^\d{9}$/'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'children_count' => ['nullable', 'integer', 'min:0', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'personal_phone' => ['nullable', 'string', self::PHONE],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'rib' => ['nullable', new MoroccanRib],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'emergency_contact_phone' => ['nullable', 'string', self::PHONE],
        ];
    }

    /** The CIN is encrypted, so uniqueness is checked on its keyed hash. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('cin') || $validator->errors()->has('cin')) {
                    return;
                }

                $owner = EmployeeProfile::where('cin_hash', EmployeeProfile::cinHash($this->input('cin')))
                    ->where('user_id', '!=', $this->target()->id)
                    ->with('user:id,name')
                    ->first();

                if ($owner) {
                    $validator->errors()->add('cin', "Cette CIN est déjà enregistrée pour {$owner->user->name}.");
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'gender' => 'genre',
            'birth_date' => 'date de naissance',
            'birth_place' => 'lieu de naissance',
            'cin' => 'CIN',
            'cnss_number' => 'N° CNSS',
            'marital_status' => 'situation familiale',
            'children_count' => "nombre d'enfants",
            'address' => 'adresse',
            'city' => 'ville',
            'personal_phone' => 'téléphone personnel',
            'personal_email' => 'e-mail personnel',
            'bank_name' => 'banque',
            'rib' => 'RIB',
            'emergency_contact_name' => "nom du contact d'urgence",
            'emergency_contact_relation' => 'lien de parenté',
            'emergency_contact_phone' => "téléphone du contact d'urgence",
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'birth_date.before_or_equal' => "L'employé doit avoir au moins 15 ans.",
            'cin.regex' => 'La CIN doit comporter 1 ou 2 lettres suivies de chiffres (ex. : AB123456).',
            'cnss_number.regex' => 'Le N° CNSS doit contenir 9 chiffres.',
            'personal_phone.regex' => 'Numéro de téléphone invalide.',
            'emergency_contact_phone.regex' => 'Numéro de téléphone invalide.',
        ];
    }
}
