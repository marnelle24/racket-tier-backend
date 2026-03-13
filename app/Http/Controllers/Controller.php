<?php

namespace App\Http\Controllers;

use App\Models\FacilityPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Ensure the current user has a presence record for the facility.
     * Returns a 404 JSON response if not (so facility appears "not found" to unauthorized users).
     */
    protected function requireFacilityPresence(Request $request, int $facilityId): ?JsonResponse
    {
        $hasPresence = FacilityPresence::where('user_id', $request->user()->id)
            ->where('facility_id', $facilityId)
            ->exists();

        if (! $hasPresence) {
            return $this->apiError('Not found.', null, 404);
        }

        return null;
    }
    /**
     * Return a standardized success response.
     */
    protected function apiSuccess(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a standardized error response.
     */
    protected function apiError(string $message, mixed $data = null, int $status = 400): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }
}
