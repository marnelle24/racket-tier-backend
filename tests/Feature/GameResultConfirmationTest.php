<?php

namespace Tests\Feature;

use App\Events\GameResultConfirmed;
use App\Models\Facility;
use App\Models\Game;
use App\Models\GameParticipant;
use App\Models\PlayerStats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameResultConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_completes_only_after_all_participants_confirm_and_broadcasts_each_confirmation(): void
    {
        Event::fake([GameResultConfirmed::class]);

        $creator = User::factory()->create();
        $inviteeA = User::factory()->create();
        $facility = Facility::create(['name' => 'Downtown Courts']);

        $game = Game::create([
            'facility_id' => $facility->id,
            'sport' => 'pickleball',
            'creator_id' => $creator->id,
            'status' => Game::STATUS_AWAITING_RESULT_CONFIRMATION,
        ]);

        GameParticipant::create([
            'game_id' => $game->id,
            'user_id' => $creator->id,
            'invitation_responded_at' => now(),
            'result' => 'win',
            'result_confirmed_at' => now(),
        ]);
        GameParticipant::create([
            'game_id' => $game->id,
            'user_id' => $inviteeA->id,
            'result' => 'loss',
            'result_confirmed_at' => now(),
        ]);

        Sanctum::actingAs($inviteeA);
        $first = $this->postJson("/api/games/{$game->id}/confirm", []);
        $first->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.all_confirmed', false);
        $game->refresh();
        $this->assertSame(Game::STATUS_AWAITING_RESULT_CONFIRMATION, $game->status);

        Sanctum::actingAs($creator);
        $second = $this->postJson("/api/games/{$game->id}/confirm", []);
        $second->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.all_confirmed', true);

        $game->refresh();
        $this->assertSame(Game::STATUS_COMPLETED, $game->status);
        $this->assertNotNull($game->stats_applied_at);

        Event::assertDispatched(GameResultConfirmed::class, 2);
    }

    public function test_upset_win_gives_more_rating_than_expected_win_and_preserves_facility_points(): void
    {
        $facility = Facility::create(['name' => 'Downtown Courts']);

        $underdog = User::factory()->create(['global_rating' => 0, 'tier' => 0]);
        $strongOpponent = User::factory()->create(['global_rating' => 2000, 'tier' => 20]);
        $favorite = User::factory()->create(['global_rating' => 1200, 'tier' => 12]);
        $weakOpponent = User::factory()->create(['global_rating' => 200, 'tier' => 2]);

        $this->completeAwaitingResultGame($facility, [
            $underdog->id => 'win',
            $strongOpponent->id => 'loss',
        ]);

        $underdog->refresh();
        $underdogDelta = $underdog->global_rating;

        $this->completeAwaitingResultGame($facility, [
            $favorite->id => 'win',
            $weakOpponent->id => 'loss',
        ]);

        $favorite->refresh();
        $favoriteDelta = $favorite->global_rating - 1200;

        $this->assertGreaterThan($favoriteDelta, $underdogDelta);

        $this->assertDatabaseHas('player_stats', [
            'user_id' => $underdog->id,
            'facility_id' => $facility->id,
            'wins' => 1,
            'losses' => 0,
            'points' => 4,
        ]);
        $this->assertDatabaseHas('player_stats', [
            'user_id' => $strongOpponent->id,
            'facility_id' => $facility->id,
            'wins' => 0,
            'losses' => 1,
            'points' => 1,
        ]);
    }

    public function test_rating_floor_and_tier_clamp_boundaries_are_enforced(): void
    {
        $facility = Facility::create(['name' => 'Downtown Courts']);

        $newPlayer = User::factory()->create(['global_rating' => 0, 'tier' => 0]);
        $veryStrong = User::factory()->create(['global_rating' => 2500, 'tier' => 25]);
        $elite = User::factory()->create(['global_rating' => 10000, 'tier' => 100]);
        $beginner = User::factory()->create(['global_rating' => 0, 'tier' => 0]);

        $this->completeAwaitingResultGame($facility, [
            $newPlayer->id => 'loss',
            $veryStrong->id => 'win',
        ]);

        $newPlayer->refresh();
        $this->assertSame(0, $newPlayer->global_rating);
        $this->assertSame(0, $newPlayer->tier);

        $this->completeAwaitingResultGame($facility, [
            $elite->id => 'win',
            $beginner->id => 'loss',
        ]);

        $elite->refresh();
        $this->assertGreaterThanOrEqual(10000, $elite->global_rating);
        $this->assertSame(100, $elite->tier);
    }

    private function completeAwaitingResultGame(Facility $facility, array $resultsByUserId): void
    {
        $game = Game::create([
            'facility_id' => $facility->id,
            'sport' => 'pickleball',
            'creator_id' => array_key_first($resultsByUserId),
            'status' => Game::STATUS_AWAITING_RESULT_CONFIRMATION,
        ]);

        foreach ($resultsByUserId as $userId => $result) {
            GameParticipant::create([
                'game_id' => $game->id,
                'user_id' => $userId,
                'result' => $result,
                'result_confirmed_at' => now(),
            ]);
        }

        foreach (array_keys($resultsByUserId) as $userId) {
            Sanctum::actingAs(User::findOrFail($userId));
            $this->postJson("/api/games/{$game->id}/confirm", [])->assertOk();
        }

        $game->refresh();
        $this->assertSame(Game::STATUS_COMPLETED, $game->status);
        $this->assertNotNull($game->stats_applied_at);
        $this->assertCount(count($resultsByUserId), PlayerStats::where('facility_id', $facility->id)->whereIn('user_id', array_keys($resultsByUserId))->get());
    }
}
