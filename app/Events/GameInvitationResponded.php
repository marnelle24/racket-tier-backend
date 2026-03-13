<?php

namespace App\Events;

use App\Models\Game;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameInvitationResponded implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Game $game,
        public array $notifiedUserIds = [],
        public ?string $action = null,
        public ?User $declinedUser = null
    ) {
        $this->game->load(['facility', 'creator', 'participants.user']);
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('facility.'.$this->game->facility_id),
        ];

        $userIds = $this->notifiedUserIds;
        if (in_array($this->action, ['decline', 'leave'], true) && $this->game->creator_id) {
            $userIds[] = $this->game->creator_id;
        }
        foreach (array_values(array_unique($userIds)) as $userId) {
            $channels[] = new PrivateChannel('App.Models.User.'.$userId);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        $payload = ['game' => $this->game->toArray()];
        if ($this->action === 'decline' && $this->declinedUser) {
            $payload['action'] = 'decline';
            $payload['declined_user'] = [
                'id' => $this->declinedUser->id,
                'name' => $this->declinedUser->name,
            ];
        }
        if ($this->action === 'leave' && $this->declinedUser) {
            $payload['action'] = 'leave';
            $payload['left_user'] = [
                'id' => $this->declinedUser->id,
                'name' => $this->declinedUser->name,
            ];
        }

        return $payload;
    }
}
