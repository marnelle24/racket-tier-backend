<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Facility extends Model
{
    protected $fillable = ['name', 'join_token', 'country', 'address'];

    protected static function booted(): void
    {
        static::creating(function (Facility $facility) {
            if (empty($facility->join_token)) {
                $facility->join_token = Str::random(32);
            }
        });
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function playerStats(): HasMany
    {
        return $this->hasMany(PlayerStats::class);
    }

    public function presences(): HasMany
    {
        return $this->hasMany(FacilityPresence::class);
    }
}
