<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityJoinTest extends TestCase
{
    use RefreshDatabase;

    public function test_join_succeeds_with_valid_token(): void
    {
        $user = User::factory()->create();
        $facility = Facility::create(['name' => 'Downtown Courts']);
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->postJson('/api/facilities/join', [
            'token' => $facility->join_token,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Successfully joined facility.',
                'data' => [
                    'facility' => [
                        'id' => $facility->id,
                        'name' => 'Downtown Courts',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('facility_presences', [
            'user_id' => $user->id,
            'facility_id' => $facility->id,
        ]);
    }

    public function test_join_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->postJson('/api/facilities/join', [
            'token' => 'invalid-token-xyz',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertNotFound()
            ->assertJson(['success' => false, 'message' => 'Invalid facility token.']);

        $this->assertDatabaseEmpty('facility_presences');
    }

    public function test_join_requires_authentication(): void
    {
        $facility = Facility::create(['name' => 'Downtown Courts']);

        $response = $this->postJson('/api/facilities/join', [
            'token' => $facility->join_token,
        ]);

        $response->assertUnauthorized()
            ->assertJson(['success' => false, 'message' => 'Unauthenticated.']);
    }

    public function test_join_requires_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->postJson('/api/facilities/join', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['errors' => ['token']]]);
    }

    public function test_switch_facility_expires_presence_at_previous_facility(): void
    {
        $user = User::factory()->create();
        $facilityA = Facility::create(['name' => 'Facility A']);
        $facilityB = Facility::create(['name' => 'Facility B']);
        $token = $user->createToken('auth-token')->plainTextToken;

        // Join facility A
        $this->postJson('/api/facilities/join', [
            'token' => $facilityA->join_token,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $presenceA = $user->facilityPresences()->where('facility_id', $facilityA->id)->first();
        $this->assertTrue($presenceA->last_seen_at->isToday(), 'User should be active at facility A');

        // Switch to facility B
        $this->postJson('/api/facilities/join', [
            'token' => $facilityB->join_token,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        // User should no longer be active at facility A (last_seen_at before today)
        $presenceA->refresh();
        $this->assertFalse($presenceA->last_seen_at->isToday(), 'User should not be active at facility A after switching');

        // User should be active at facility B
        $presenceB = $user->facilityPresences()->where('facility_id', $facilityB->id)->first();
        $this->assertNotNull($presenceB);
        $this->assertTrue($presenceB->last_seen_at->isToday(), 'User should be active at facility B');
    }

    public function test_rejoin_updates_last_seen_preserves_joined_at(): void
    {
        $user = User::factory()->create();
        $facility = Facility::create(['name' => 'Downtown Courts']);
        $authToken = $user->createToken('auth-token')->plainTextToken;

        $firstJoin = $this->postJson('/api/facilities/join', [
            'token' => $facility->join_token,
        ], [
            'Authorization' => 'Bearer '.$authToken,
        ]);

        $firstJoin->assertOk();

        $presence = $user->facilityPresences()->where('facility_id', $facility->id)->first();
        $originalJoinedAt = $presence->joined_at;

        // Re-join (e.g. user scans QR again)
        $this->postJson('/api/facilities/join', [
            'token' => $facility->join_token,
        ], [
            'Authorization' => 'Bearer '.$authToken,
        ])->assertOk();

        $presence->refresh();
        $this->assertEquals($originalJoinedAt->toDateTimeString(), $presence->joined_at->toDateTimeString());
        $this->assertTrue($presence->last_seen_at->gte($originalJoinedAt));
    }
}
