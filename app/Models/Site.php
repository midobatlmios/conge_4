<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company site (head office, warehouse…). Its employees follow the national
 * holidays plus the site's own local holidays.
 *
 * @property string $name
 * @property string|null $city
 */
class Site extends Model
{
    protected $fillable = ['name', 'city'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }
}
