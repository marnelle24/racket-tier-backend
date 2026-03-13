<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameParticipant extends Model
{
    protected $fillable = ['game_id', 'user_id', 'result', 'result_confirmed_at', 'confirmed_at', 'invitation_responded_at'];

    protected function casts(): array
    {
        return [
            'result_confirmed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'invitation_responded_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
