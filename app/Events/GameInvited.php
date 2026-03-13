<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast immediately so the invited user sees the modal without running a queue worker.
 */
class GameInvited implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Game $game,
        public int $invitedUserId
    ) {
        $this->game->load(['facility', 'creator', 'participants.user']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->invitedUserId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'game' => $this->game->toArray(),
        ];
    }
}
