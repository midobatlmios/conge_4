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
