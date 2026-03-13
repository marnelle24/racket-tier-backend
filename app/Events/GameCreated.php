<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a new game is created at a facility so all users in the room
 * see it in Active Games in real-time.
 */
class GameCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Game $game
    ) {
        $this->game->load(['facility', 'creator', 'participants.user']);
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
