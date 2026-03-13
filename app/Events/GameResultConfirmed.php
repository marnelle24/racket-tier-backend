<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameResultConfirmed implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Game $game
    ) {
        $this->game->load(['facility', 'creator', 'winners', 'participants.user']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('facility.'.$this->game->facility_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'game' => $this->game->toArray(),
        ];
    }
}
