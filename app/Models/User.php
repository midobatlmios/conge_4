<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property Role $role
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
    ];

    public function demandes(): HasMany
    {
        return $this->hasMany(Demande::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
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
