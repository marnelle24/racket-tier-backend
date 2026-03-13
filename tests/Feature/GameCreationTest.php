<?php

namespace Tests\Feature;

use App\Events\GameCreated;
use App\Events\GameInvited;
use App\Models\Facility;
use App\Models\FacilityPresence;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_game_with_invited_participants_and_dispatches_invite_events(): void
    {
        Event::fake([GameCreated::class, GameInvited::class]);

        $creator = User::factory()->create();
        $inviteeA = User::factory()->create();
        $inviteeB = User::factory()->create();
        $facility = Facility::create(['name' => 'Downtown Courts']);
        $token = $creator->createToken('auth-token')->plainTextToken;

        FacilityPresence::create([
            'user_id' => $creator->id,
            'facility_id' => $facility->id,
            'joined_at' => now(),
            'last_seen_at' => now(),
        ]);
        FacilityPresence::create([
            'user_id' => $inviteeA->id,
            'facility_id' => $facility->id,
            'joined_at' => now(),
            'last_seen_at' => now(),
        ]);
        FacilityPresence::create([
            'user_id' => $inviteeB->id,
            'facility_id' => $facility->id,
            'joined_at' => now(),
            'last_seen_at' => now(),
        ]);

        $response = $this->postJson('/api/games', [
            'facility_id' => $facility->id,
            'sport' => 'pickleball',
            // Include duplicates and creator id to verify dedupe/filter.
            'user_ids' => [$inviteeA->id, $creator->id, $inviteeB->id, $inviteeA->id],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Game created.');

        $gameId = (int) $response->json('data.game.id');
        $this->assertNotSame(0, $gameId);

        $this->assertDatabaseHas('games', [
            'id' => $gameId,
            'creator_id' => $creator->id,
            'facility_id' => $facility->id,
            'sport' => 'pickleball',
            'status' => Game::STATUS_AWAITING_CONFIRMATION,
        ]);

        $this->assertDatabaseHas('game_participants', [
            'game_id' => $gameId,
            'user_id' => $creator->id,
        ]);
        $this->assertDatabaseHas('game_participants', [
            'game_id' => $gameId,
            'user_id' => $inviteeA->id,
        ]);
        $this->assertDatabaseHas('game_participants', [
            'game_id' => $gameId,
            'user_id' => $inviteeB->id,
        ]);
        $this->assertDatabaseCount('game_participants', 3);

        Event::assertDispatched(GameCreated::class, 1);
        Event::assertDispatched(GameInvited::class, 2);
        Event::assertDispatched(GameInvited::class, function (GameInvited $event) use ($gameId, $inviteeA) {
            return $event->game->id === $gameId && $event->invitedUserId === $inviteeA->id;
        });
        Event::assertDispatched(GameInvited::class, function (GameInvited $event) use ($gameId, $inviteeB) {
            return $event->game->id === $gameId && $event->invitedUserId === $inviteeB->id;
        });
    }

    public function test_store_requires_user_ids(): void
    {
        $creator = User::factory()->create();
        $facility = Facility::create(['name' => 'Downtown Courts']);
        $token = $creator->createToken('auth-token')->plainTextToken;

        FacilityPresence::create([
            'user_id' => $creator->id,
            'facility_id' => $facility->id,
            'joined_at' => now(),
            'last_seen_at' => now(),
        ]);

        $response = $this->postJson('/api/games', [
            'facility_id' => $facility->id,
            'sport' => 'pickleball',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['errors' => ['user_ids']]]);
    }
}
