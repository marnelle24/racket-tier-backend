<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameAborted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, int>  $notifiedUserIds
     */
    public function __construct(
        public int $gameId,
        public int $facilityId,
        public array $notifiedUserIds = []
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('facility.'.$this->facilityId),
        ];

        foreach (array_values(array_unique($this->notifiedUserIds)) as $userId) {
            $channels[] = new PrivateChannel('App.Models.User.'.$userId);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->gameId,
            'facility_id' => $this->facilityId,
        ];
    }
}
