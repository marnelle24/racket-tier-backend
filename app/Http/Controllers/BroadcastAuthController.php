<?php

namespace App\Http\Controllers;

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Wraps Laravel's broadcast auth to return the failing channel name on 403 for debugging.
 */
class BroadcastAuthController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            return app(BroadcastController::class)->authenticate($request);
        } catch (AccessDeniedHttpException $e) {
            $channel = $request->input('channel_name', '');

            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to access this channel.',
                'data' => ['channel' => $channel],
            ], 403);
        }
    }
}
