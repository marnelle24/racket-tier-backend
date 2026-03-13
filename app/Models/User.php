<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'age',
        'pronoun',
        'primary_sport',
        'nickname',
        'avatar_seed',
        'global_rating',
        'tier',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'age' => 'integer',
            'global_rating' => 'integer',
            'tier' => 'integer',
        ];
    }

    public function gamesCreated(): HasMany
    {
        return $this->hasMany(Game::class, 'creator_id');
    }

    public function gameParticipants(): HasMany
    {
        return $this->hasMany(GameParticipant::class);
    }

    public function playerStats(): HasMany
    {
        return $this->hasMany(PlayerStats::class);
    }

    public function facilityPresences(): HasMany
    {
        return $this->hasMany(FacilityPresence::class);
    }
}
