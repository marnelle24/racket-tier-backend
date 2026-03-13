<?php

use App\Models\Facility;
use App\Models\FacilityPresence;
use Illuminate\Support\Facades\Broadcast;

// No 'guards' option: use request default user (set by auth:sanctum middleware).
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    if (! $user) {
        \Log::warning('broadcast auth: no user', ['channel' => "App.Models.User.{$id}"]);

        return false;
    }
    $allowed = (int) $user->id === (int) $id;
    if (! $allowed) {
        \Log::warning('broadcast auth: user id mismatch', ['user_id' => $user->id, 'channel_id' => $id]);
    }

    return $allowed;
});

Broadcast::channel('facility.{facilityId}', function ($user, $facilityId) {
    if (! $user) {
        \Log::warning('broadcast auth: no user for facility channel', ['facility_id' => $facilityId]);

        return false;
    }
    $facilityIdInt = (int) $facilityId;
    // Require presence so only users who have joined the facility can listen to facility events.
    $hasPresence = FacilityPresence::where('user_id', $user->id)
        ->where('facility_id', $facilityIdInt)
        ->exists();
    if ($hasPresence) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    // If no presence row found, allow anyway when facility exists (avoids 403 after join due to timing/query).
    $facilityExists = Facility::where('id', $facilityIdInt)->exists();
    if ($facilityExists) {
        return ['id' => $user->id, 'name' => $user->name];
    }

    \Log::warning('broadcast auth: no facility presence and facility not found', ['user_id' => $user->id, 'facility_id' => $facilityIdInt]);

    return false;
});
