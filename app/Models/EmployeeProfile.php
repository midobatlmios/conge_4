<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personal and administrative data of an employee: HR edits it, the employee
 * can only read their own (see EmployeeProfileController).
 *
 * Sensitive fields are encrypted with APP_KEY: changing the key without listing
 * the old one in APP_PREVIOUS_KEYS makes them unreadable.
 *
 * @property int $user_id
 * @property Gender|null $gender
 * @property \Illuminate\Support\Carbon|null $birth_date
 * @property string|null $cin
 * @property string|null $cnss_number
 * @property MaritalStatus|null $marital_status
 * @property string|null $rib
 */
class EmployeeProfile extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeProfileFactory> */
    use HasFactory;

    /** Fields HR fills in (all optional: the record is completed over time). */
    public const FIELDS = [
        'gender',
        'birth_date',
        'birth_place',
        'cin',
        'cnss_number',
        'marital_status',
        'children_count',
        'address',
        'city',
        'personal_phone',
        'personal_email',
        'bank_name',
        'rib',
        'emergency_contact_name',
        'emergency_contact_relation',
        'emergency_contact_phone',
    ];

    /** Needed for payroll, CNSS declarations and attestations: flagged when missing. */
    public const ESSENTIAL = [
        'cin' => 'CIN',
        'cnss_number' => 'N° CNSS',
        'birth_date' => 'date de naissance',
        'address' => 'adresse',
        'rib' => 'RIB',
    ];

    protected $fillable = [...self::FIELDS, 'updated_by'];

    /** The lookup hash never leaves the server. */
    protected $hidden = ['cin_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'gender' => Gender::class,
        'marital_status' => MaritalStatus::class,
        'birth_date' => 'date:Y-m-d',
        'children_count' => 'integer',
        'cin' => 'encrypted',
        'cnss_number' => 'encrypted',
        'address' => 'encrypted',
        'personal_phone' => 'encrypted',
        'personal_email' => 'encrypted',
        'rib' => 'encrypted',
        'emergency_contact_phone' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmployeeProfile $profile) {
            if ($profile->isDirty('cin')) {
                $profile->cin_hash = static::cinHash($profile->cin);
            }
        });
    }

    /**
     * Keyed hash of a CIN: the CIN itself is encrypted (so it cannot be
     * searched), this lets us refuse the same CIN on two employees.
     */
    public static function cinHash(?string $cin): ?string
    {
        return $cin === null || $cin === '' ? null : hash_hmac('sha256', strtoupper($cin), (string) config('app.key'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return list<string> Labels of the essential fields still empty. */
    public function missingEssentials(): array
    {
        return array_values(array_filter(
            self::ESSENTIAL,
            fn (string $label, string $field) => blank($this->{$field}),
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
