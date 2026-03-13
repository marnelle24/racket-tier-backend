<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Game;
use App\Models\GameParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameMetadataTimestampsTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_sets_start_time_when_creator_starts_game(): void
    {
        $creator = User::factory()->create();
        $invitee = User::factory()->create();
        $facility = Facility::create(['name' => 'Downtown Courts']);

        $game = Game::create([
            'facility_id' => $facility->id,
            'sport' => 'pickleball',
            'creator_id' => $creator->id,
            'status' => Game::STATUS_AWAITING_CONFIRMATION,
        ]);

        GameParticipant::create([
            'game_id' => $game->id,
            'user_id' => $creator->id,
            'invitation_responded_at' => now(),
        ]);
        GameParticipant::create([
            'game_id' => $game->id,
            'user_id' => $invitee->id,
            'invitation_responded_at' => now(),
        ]);

        Sanctum::actingAs($creator);

        $response = $this->postJson("/api/games/{$game->id}/start", []);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Game started.');

        $game->refresh();
        $this->assertSame(Game::STATUS_ONGOING, $game->status);
        $this->assertNotNull($game->start_time);
    }

    public function test_submit_result_sets_end_time_when_creator_finishes_game(): void
    {
        $creator = User::factory()->create();
        $invitee = User::factory()->create();
        $facility = Facility::create(['name' => 'Downtown Courts']);

        $game = Game::create([
            'facility_id' => $facility->id,
            'sport' => 'pickleball',
            'creator_id' => $creator->id,
            'status' => Game::STATUS_ONGOING,
            'start_time' => now()->subMinutes(20),
        ]);

        GameParticipant::create([
            'game_id' => $game->id,
            'user_id' => $creator->id,
            'invitation_responded_at' => now()->subMinutes(25),
        ]);
        GameParticipant::create([
            'game_id' => $game->id,
            'user_id' => $invitee->id,
            'invitation_responded_at' => now()->subMinutes(25),
        ]);

        Sanctum::actingAs($creator);

        $response = $this->postJson("/api/games/{$game->id}/result", [
            'results' => [
                ['user_id' => $creator->id, 'result' => 'win'],
                ['user_id' => $invitee->id, 'result' => 'loss'],
            ],
            'score' => [21, 15],
            'match_type' => '1st Set',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Result submitted. Waiting for participants to confirm.');

        $game->refresh();
        $this->assertSame(Game::STATUS_AWAITING_RESULT_CONFIRMATION, $game->status);
        $this->assertNotNull($game->end_time);
        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'score' => '[21,15]',
            'match_type' => '1st Set',
        ]);
    }
}
