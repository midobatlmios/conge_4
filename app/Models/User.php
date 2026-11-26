<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property Role $role
 * @property string|null $matricule
 * @property int|null $department_id
 * @property string|null $job_title
 * @property \Illuminate\Support\Carbon|null $hire_date
 * @property int|null $manager_id
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'matricule',
        'department_id',
        'job_title',
        'hire_date',
        'manager_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => Role::class,
        'deactivated_at' => 'datetime',
        // Read and accepted the data protection notice (loi 09-08).
        'privacy_acknowledged_at' => 'datetime',
        // Former employee whose identity was erased at the end of the retention period.
        'anonymized_at' => 'datetime',
        'hire_date' => 'date:Y-m-d',
    ];

    public function demandes(): HasMany
    {
        return $this->hasMany(Demande::class);
    }

    /** Personal and administrative data (HR only). Never loaded into shared props. */
    public function profile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    /** The employee's document folder (contract, CIN copy, diplomas…). */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /** Employment contracts, newest first (history: CDD, renewal, CDI…). */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class)->orderByDesc('start_date');
    }

    /**
     * The contract in force: the latest one that has started, or the next one
     * if none has started yet. Uses the loaded contracts when there are.
     */
    public function currentContract(): ?EmploymentContract
    {
        $contracts = $this->contracts;

        return $contracts->first(fn (EmploymentContract $contract) => $contract->start_date->lte(today()))
            ?? $contracts->last();
    }

    /** Clock-in / clock-out records, one per day. */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /** Corrections of clocking the employee asked for. */
    public function attendanceCorrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    /** Career history (promotions, raises, transfers), newest first. */
    public function careerChanges(): HasMany
    {
        return $this->hasMany(CareerChange::class)->orderByDesc('effective_date')->orderByDesc('id');
    }

    /**
     * Monthly gross salary on a given day: that of the contract in force that
     * day, as it was before any later raise on that contract. Null without a
     * contract that day or a salary on it. Uses the loaded relations when there are.
     */
    public function salaryOn(\Carbon\CarbonInterface $day): ?float
    {
        $contract = $this->contracts->first(fn (EmploymentContract $contract) => $contract->start_date->lte($day)
            && ($contract->end_date === null || $contract->end_date->gte($day)));

        if (! $contract) {
            return null;
        }

        // The first raise after that day knows what the salary was until then.
        $later = $this->careerChanges
            ->filter(fn (CareerChange $change) => $change->contract_id === $contract->id && $change->changesSalary() && $change->effective_date->gt($day))
            ->sortBy(fn (CareerChange $change) => [$change->effective_date->toDateString(), $change->id])
            ->first();
        $salary = $later ? $later->previous_gross_salary : $contract->gross_salary;

        return $salary === null ? null : (float) $salary;
    }

    /** Attestations de travail / de salaire asked for or issued. */
    public function attestations(): HasMany
    {
        return $this->hasMany(Attestation::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** The employee's line manager (any active employee). */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** Employees who report to this user. */
    public function reports(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    /** Has at least one active employee reporting to them: approves their requests first. */
    public function isManager(): bool
    {
        return $this->reports()->whereNull('deactivated_at')->exists();
    }

    /** Delegations this manager gave (their approvals handed to someone else). */
    public function delegationsGiven(): HasMany
    {
        return $this->hasMany(ApprovalDelegation::class, 'manager_id')->orderBy('starts_on');
    }

    /** The person approving for this manager today, if they delegated (and that person is active). */
    public function currentDelegate(): ?User
    {
        $delegate = $this->delegationsGiven()->current()->with('delegate')->first()?->delegate;

        return $delegate?->isActive() ? $delegate : null;
    }

    /** @return list<int> Managers who delegated their approvals to this user today. */
    public function delegatingManagerIds(): array
    {
        return ApprovalDelegation::current()->where('delegate_id', $this->id)->pluck('manager_id')->all();
    }

    /** Approves someone's requests today: their own team, or a team delegated to them. */
    public function approvesRequests(): bool
    {
        return $this->isManager() || $this->delegatingManagerIds() !== [];
    }

    /**
     * Who should hear about a request waiting for this manager: the delegate during
     * a delegation (unless the request is the delegate's own), otherwise the manager.
     */
    public function approvalRecipient(?User $requester = null): User
    {
        $delegate = $this->currentDelegate();

        return $delegate && ! $delegate->is($requester) ? $delegate : $this;
    }

    /** The active manager who approves this employee's requests first, if any. */
    public function approvingManager(): ?User
    {
        $manager = $this->manager;

        return $manager && $manager->isActive() ? $manager : null;
    }

    /** Super-administrator: HR rights plus giving and removing roles. */
    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Admin or RH: changes HR data and decides on leave. */
    public function isHr(): bool
    {
        return (bool) $this->role?->managesHr();
    }

    /** Admin, RH or Paie: reads every request, employee file and the payroll export. */
    public function canViewHr(): bool
    {
        return (bool) $this->role?->viewsHr();
    }

    /**
     * Edit, reset the password of, or deactivate this account. RH only handles
     * plain employees, so it cannot take over an Admin, RH or Paie account.
     */
    public function canManageAccount(User $target): bool
    {
        return $this->isAdmin() || ($this->isHr() && $target->role === Role::User);
    }

    /** @return list<Role> Roles this user may give to an account. */
    public function assignableRoles(): array
    {
        return match (true) {
            $this->isAdmin() => Role::cases(),
            $this->isHr() => [Role::User],
            default => [],
        };
    }

    /** What the signed-in user may do, for the menus and buttons (shared with every page). */
    public function permissions(): array
    {
        return [
            'view_hr' => $this->canViewHr(),
            'manage_hr' => $this->isHr(),
            'manage_roles' => $this->isAdmin(),
        ];
    }

    /** A deactivated account (employee who left) cannot sign in; its history is kept. */
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /** @param Builder<User> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    /** Whether this user is the only active administrator left. */
    public function isLastAdmin(): bool
    {
        return $this->isAdmin()
            && $this->isActive()
            && static::active()->where('role', Role::Admin)->count() <= 1;
    }
}
