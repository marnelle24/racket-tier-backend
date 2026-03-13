<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\FacilityPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacilityJoinController extends Controller
{
    /**
     * Join a facility via QR token.
     * Validates the facility token and records user presence.
     */
    public function join(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $facility = Facility::where('join_token', $validated['token'])->first();

        if (! $facility) {
            return $this->apiError('Invalid facility token.', null, 404);
        }

        $now = now();

        // Check out of all other facilities so the user is only active at one at a time
        FacilityPresence::where('user_id', $request->user()->id)
            ->where('facility_id', '!=', $facility->id)
            ->update(['last_seen_at' => $now->copy()->startOfDay()->subSecond()]);

        $presence = FacilityPresence::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'facility_id' => $facility->id,
            ],
            [
                'joined_at' => $now,
                'last_seen_at' => $now,
            ]
        );

        if (! $presence->wasRecentlyCreated) {
            $presence->update(['last_seen_at' => $now]);
        }

        return $this->apiSuccess('Successfully joined facility.', [
            'facility' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'country' => $facility->country ?? 'Philippines',
                'address' => $facility->address,
            ],
        ]);
    }
}
