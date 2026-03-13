<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    public const STATUS_ONGOING = 'ongoing';
    public const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';
    public const STATUS_AWAITING_RESULT_CONFIRMATION = 'awaiting_result_confirmation';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'facility_id',
        'sport',
        'creator_id',
        'status',
        'start_time',
        'end_time',
        'score',
        'match_type',
        'stats_applied_at',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'stats_applied_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function winners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'game_winners')->withTimestamps(false);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(GameParticipant::class);
    }
}
