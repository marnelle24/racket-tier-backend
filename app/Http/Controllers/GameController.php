<?php

namespace App\Http\Controllers;

use App\Events\GameCreated;
use App\Events\GameAborted;
use App\Events\GameInvitationResponded;
use App\Events\GameInvited;
use App\Events\GameResultConfirmed;
use App\Events\GameResultSubmitted;
use App\Events\GameStarted;
use App\Models\FacilityPresence;
use App\Models\Game;
use App\Models\GameParticipant;
use App\Models\PlayerStats;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    /**
     * List games at a facility.
     * By default returns active games. Pass status=completed for recent completed games.
     * Active games: returns games where the current user is a participant, OR games with
     * status awaiting_confirmation/ongoing (visible to all room members).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
        ]);
        $facilityId = $validated['facility_id'];

        $notFound = $this->requireFacilityPresence($request, $facilityId);
        if ($notFound !== null) {
            return $notFound;
        }

        $status = $request->query('status');
        $userId = $request->user()->id;

        $participantFilter = fn ($q) => $q->where('user_id', $userId);

        if ($status === 'completed') {
            $games = Game::where('facility_id', $facilityId)
                ->where('status', Game::STATUS_COMPLETED)
                ->whereHas('participants', $participantFilter)
                ->with(['creator:id,name', 'winners:id,name', 'participants.user:id,name,avatar_seed'])
                ->orderBy('stats_applied_at', 'desc')
                ->limit(20)
                ->get();
        } else {
            $games = Game::where('facility_id', $facilityId)
                ->whereNotIn('status', [Game::STATUS_COMPLETED, Game::STATUS_CANCELLED])
                ->where(function ($q) use ($userId, $participantFilter) {
                    $q->whereHas('participants', $participantFilter)
                        ->orWhereIn('status', [
                            Game::STATUS_AWAITING_CONFIRMATION,
                            Game::STATUS_ONGOING,
                        ]);
                })
                // Exclude ongoing games older than 2 hours (stale/forgotten games)
                ->where(function ($q) {
                    $q->where('status', '!=', Game::STATUS_ONGOING)
                        ->orWhere(function ($q2) {
                            $q2->where('status', Game::STATUS_ONGOING)
                                ->whereNotNull('start_time')
                                ->where('start_time', '>=', now()->subHours(2));
                        });
                })
                ->with(['creator:id,name', 'participants.user:id,name,avatar_seed'])
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return $this->apiSuccess('Games retrieved.', $games);
    }

    /**
     * Create a new game at a facility.
     * User must have an active presence at the facility (joined via QR).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'sport' => ['required', 'string', 'max:64'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $userId = $request->user()->id;
        $facilityId = $validated['facility_id'];

        $hasPresence = FacilityPresence::where('user_id', $userId)
            ->where('facility_id', $facilityId)
            ->exists();

        if (! $hasPresence) {
            return $this->apiError('You must be at the facility to create a game. Scan the facility QR code first.', null, 403);
        }

        $hasActiveGame = Game::whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->whereNotIn('status', [Game::STATUS_COMPLETED, Game::STATUS_CANCELLED])
            ->exists();

        if ($hasActiveGame) {
            return $this->apiError('You already have an active game. Complete or cancel it before creating a new one.', null, 403);
        }

        $lockKey = 'game_create:'.$userId.':'.$facilityId;
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            return $this->apiError('A game is being created. Please wait a moment.', null, 409);
        }

        try {
            $recentDuplicate = Game::where('creator_id', $userId)
                ->where('facility_id', $facilityId)
                ->where('sport', $validated['sport'])
                ->where('status', Game::STATUS_AWAITING_CONFIRMATION)
                ->where('created_at', '>=', now()->subSeconds(15))
                ->exists();

            if ($recentDuplicate) {
                return $this->apiError('A game was just created. Please use that game or wait a moment before creating another.', null, 409);
            }

            $invitedUserIds = array_values(array_unique(array_filter(
                $validated['user_ids'],
                fn ($id) => (int) $id !== (int) $userId
            )));

            $game = DB::transaction(function () use ($userId, $facilityId, $validated, $invitedUserIds) {
                $game = Game::create([
                    'facility_id' => $facilityId,
                    'sport' => $validated['sport'],
                    'creator_id' => $userId,
                    'status' => Game::STATUS_AWAITING_CONFIRMATION,
                ]);

                GameParticipant::create([
                    'game_id' => $game->id,
                    'user_id' => $userId,
                    'invitation_responded_at' => now(),
                ]);

                foreach ($invitedUserIds as $invitedUserId) {
                    GameParticipant::create([
                        'game_id' => $game->id,
                        'user_id' => $invitedUserId,
                    ]);
                }

                return $game->load(['facility', 'creator', 'participants.user']);
            });

            GameCreated::dispatch($game);
            foreach ($invitedUserIds as $invitedUserId) {
                $isActiveInRoom = FacilityPresence::where('user_id', $invitedUserId)
                    ->where('facility_id', $facilityId)
                    ->exists();
                if ($isActiveInRoom) {
                    GameInvited::dispatch($game, $invitedUserId);
                }
            }

            return $this->apiSuccess('Game created.', ['game' => $game], 201);
        } finally {
            $lock->release();
        }
    }

    /**
     * Start an awaiting_confirmation game (set status to ongoing).
     * Only the creator can start. Non-responding invitees are removed. At least one
     * other player must have accepted for the game to start.
     */
    public function start(Request $request, Game $game): JsonResponse
    {
        if ($game->creator_id !== $request->user()->id) {
            return $this->apiError('Only the game creator can start the game.', null, 403);
        }

        if ($game->status !== Game::STATUS_AWAITING_CONFIRMATION) {
            return $this->apiError('Only games awaiting confirmation can be started.', null, 403);
        }

        // Remove participants who have not responded (treat as declined) so creator can proceed
        GameParticipant::where('game_id', $game->id)
            ->whereNull('invitation_responded_at')
            ->delete();

        $participantCount = $game->participants()->count();

        if ($participantCount < 2) {
            return $this->apiError('At least one invited player must accept before starting.', null, 403);
        }

        $game->update([
            'status' => Game::STATUS_ONGOING,
            'start_time' => now(),
        ]);

        $game = $game->fresh()->load(['facility', 'creator', 'participants.user']);
        GameStarted::dispatch($game);

        return $this->apiSuccess('Game started.', ['game' => $game]);
    }

    /**
     * Invite users to a game.
     * Only the game creator can invite. Game must be awaiting confirmation.
     */
    public function invite(Request $request, Game $game): JsonResponse
    {
        if ($game->creator_id !== $request->user()->id) {
            return $this->apiError('Only the game creator can invite participants.', null, 403);
        }

        if ($game->status !== Game::STATUS_AWAITING_CONFIRMATION) {
            return $this->apiError('Invitations are only allowed for games awaiting confirmation.', null, 403);
        }

        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $existingUserIds = $game->participants()->pluck('user_id')->all();
        $newUserIds = array_values(array_diff($validated['user_ids'], $existingUserIds));

        $invited = [];
        foreach ($newUserIds as $userId) {
            $invited[] = GameParticipant::create([
                'game_id' => $game->id,
                'user_id' => $userId,
            ]);
        }

        $game->load(['facility', 'creator', 'participants.user']);

        foreach ($newUserIds as $invitedUserId) {
            $isActiveInRoom = FacilityPresence::where('user_id', $invitedUserId)
                ->where('facility_id', $game->facility_id)
                ->exists();
            if ($isActiveInRoom) {
                GameInvited::dispatch($game, $invitedUserId);
            }
        }

        return $this->apiSuccess(
            count($invited) > 0
                ? 'Invitations sent.'
                : 'No new invitations sent (all users were already participants).',
            ['game' => $game, 'invited_count' => count($invited)]
        );
    }

    /**
     * Edit invite list for an awaiting_confirmation game.
     * Creator can add users and remove only pending invites.
     */
    public function updateInvites(Request $request, Game $game): JsonResponse
    {
        if ($game->creator_id !== $request->user()->id) {
            return $this->apiError('Only the game creator can edit invited participants.', null, 403);
        }

        if ($game->status !== Game::STATUS_AWAITING_CONFIRMATION) {
            return $this->apiError('Invited players can only be edited while awaiting confirmation.', null, 403);
        }

        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $targetUserIds = array_values(array_unique(array_filter(
            $validated['user_ids'],
            fn ($id) => (int) $id !== (int) $game->creator_id
        )));

        [$addedUserIds, $removedUserIds] = DB::transaction(function () use ($game, $targetUserIds) {
            $participants = $game->participants()->get();
            $existingByUserId = $participants->keyBy('user_id');
            $existingUserIds = $existingByUserId->keys()->all();

            $addedUserIds = array_values(array_diff($targetUserIds, $existingUserIds));
            foreach ($addedUserIds as $userId) {
                GameParticipant::create([
                    'game_id' => $game->id,
                    'user_id' => $userId,
                ]);
            }

            $removableUserIds = $participants
                ->filter(fn (GameParticipant $p) => $p->invitation_responded_at === null)
                ->pluck('user_id')
                ->all();
            $removedUserIds = array_values(array_diff($removableUserIds, $targetUserIds));
            if (count($removedUserIds) > 0) {
                GameParticipant::where('game_id', $game->id)
                    ->whereIn('user_id', $removedUserIds)
                    ->whereNull('invitation_responded_at')
                    ->delete();
            }

            return [$addedUserIds, $removedUserIds];
        });

        $game = $game->fresh()->load(['facility', 'creator', 'participants.user']);
        foreach ($addedUserIds as $invitedUserId) {
            GameInvited::dispatch($game, $invitedUserId);
        }
        GameInvitationResponded::dispatch($game, $removedUserIds);

        return $this->apiSuccess('Invited players updated.', [
            'game' => $game,
            'added_count' => count($addedUserIds),
            'removed_count' => count($removedUserIds),
        ]);
    }

    /**
     * Respond to a game invitation (accept or decline).
     * User must be invited (have a participant record and not be the creator).
     * If declined, the participant record is removed.
     * If accepted, the record is kept.
     */
    public function respond(Request $request, Game $game): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:accept,decline'],
        ]);

        $userId = $request->user()->id;

        if ($game->creator_id === $userId) {
            return $this->apiError('Game creator does not need to respond to invitations.', null, 403);
        }

        $participant = GameParticipant::where('game_id', $game->id)
            ->where('user_id', $userId)
            ->first();

        if (! $participant) {
            return $this->apiError('You have not been invited to this game.', null, 404);
        }

        if ($participant->invitation_responded_at !== null) {
            return $this->apiError('You have already responded to this invitation.', null, 403);
        }

        if ($validated['action'] === 'decline') {
            $declinedUser = $request->user();
            $participant->delete();

            $game = $game->fresh()->load(['facility', 'creator', 'participants.user']);
            GameInvitationResponded::dispatch($game, [], 'decline', $declinedUser);

            return $this->apiSuccess('Invitation declined. You have been removed from the game.');
        }

        $participant->update(['invitation_responded_at' => now()]);

        $game = $game->fresh()->load(['facility', 'creator', 'participants.user']);
        GameInvitationResponded::dispatch($game);

        return $this->apiSuccess('Invitation accepted. You are now a participant.', ['game' => $game]);
    }

    /**
     * Submit game result.
     * Only the game creator can submit. Must provide results for all participants.
     * Updates result on each game_participant and sets game status to awaiting_result_confirmation.
     * Game becomes completed only after participants (including creator) confirm; then PlayerStats are updated.
     */
    public function submitResult(Request $request, Game $game): JsonResponse
    {
        if ($game->creator_id !== $request->user()->id) {
            return $this->apiError('Only the game creator can submit the result.', null, 403);
        }

        if ($game->status === Game::STATUS_COMPLETED) {
            return $this->apiError('This game is already completed.', null, 403);
        }

        if ($game->status === Game::STATUS_CANCELLED) {
            return $this->apiError('Cannot submit result for a cancelled game.', null, 403);
        }

        if ($game->status === Game::STATUS_AWAITING_RESULT_CONFIRMATION) {
            return $this->apiError('Result was already submitted. Waiting for participants to confirm.', null, 403);
        }

        if ($game->status !== Game::STATUS_ONGOING) {
            return $this->apiError('Result can only be submitted for an ongoing game. Start the game first.', null, 403);
        }

        $participants = $game->participants()->get();
        $participantUserIds = $participants->pluck('user_id')->sort()->values()->all();

        if (count($participantUserIds) === 0) {
            return $this->apiError('Cannot submit result: game has no participants.', null, 422);
        }

        $validated = $request->validate([
            'results' => ['required', 'array', 'size:'.count($participantUserIds)],
            'results.*.user_id' => ['required', 'integer', 'in:'.implode(',', $participantUserIds)],
            'results.*.result' => ['required', 'string', 'in:win,loss,draw'],
            'score' => ['nullable', 'array', 'size:2'],
            'score.*' => ['integer', 'min:0'],
            'match_type' => ['nullable', 'string', 'max:64'],
        ]);

        $submittedUserIds = collect($validated['results'])->pluck('user_id')->sort()->values()->all();
        if ($submittedUserIds !== $participantUserIds) {
            return $this->apiError('You must provide exactly one result per participant.', null, 422);
        }

        $resultsByUserId = collect($validated['results'])->keyBy('user_id');

        DB::transaction(function () use ($game, $participants, $resultsByUserId, $validated) {
            $winnerUserIds = [];
            foreach ($participants as $participant) {
                $resultRow = $resultsByUserId->get($participant->user_id);
                $result = $resultRow['result'] ?? null;
                if ($result === null) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        response()->json(['success' => false, 'message' => 'Missing result for participant. Please try again.'], 422)
                    );
                }
                $participant->update([
                    'result' => $result,
                    'result_confirmed_at' => now(),
                ]);
                if ($result === 'win') {
                    $winnerUserIds[] = $participant->user_id;
                }
            }

            $game->winners()->sync($winnerUserIds);
            $game->update([
                'status' => Game::STATUS_AWAITING_RESULT_CONFIRMATION,
                'end_time' => now(),
                'score' => isset($validated['score']) ? json_encode(array_values($validated['score'])) : null,
                'match_type' => isset($validated['match_type']) ? trim((string) $validated['match_type']) : null,
            ]);
        });

        $game = $game->fresh()->load(['facility', 'creator', 'winners', 'participants.user']);
        GameResultSubmitted::dispatch($game);

        return $this->apiSuccess('Result submitted. Waiting for participants to confirm.', ['game' => $game]);
    }

    /**
     * Confirm game result.
     * Only participants (including creator) can confirm. Prevents double confirmation.
     * Allowed when game status is awaiting_result_confirmation (result was submitted by creator).
     * When majority have confirmed: game status is set to completed and PlayerStats are updated.
     */
    public function confirm(Request $request, Game $game): JsonResponse
    {
        $userId = $request->user()->id;

        $participant = GameParticipant::where('game_id', $game->id)
            ->where('user_id', $userId)
            ->first();

        if (! $participant) {
            return $this->apiError('Only participants can confirm the result.', null, 403);
        }

        if ($participant->confirmed_at !== null) {
            return $this->apiError('You have already confirmed this result.', null, 403);
        }

        if ($game->status !== Game::STATUS_AWAITING_RESULT_CONFIRMATION) {
            return $this->apiError('Result must be submitted by the creator before participants can confirm.', null, 403);
        }

        $allConfirmed = false;

        DB::transaction(function () use ($game, $userId, &$allConfirmed) {
            $participant = GameParticipant::where('game_id', $game->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($participant->confirmed_at !== null) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json(['success' => false, 'message' => 'You have already confirmed this result.'], 403)
                );
            }
            $participant->update(['confirmed_at' => now()]);

            $participants = $game->participants()->get();
            $total = $participants->count();
            $confirmedCount = $participants->whereNotNull('confirmed_at')->count();
            $allConfirmed = $confirmedCount === $total;
            $statsNotYetApplied = $game->stats_applied_at === null;

            if ($allConfirmed && $statsNotYetApplied) {
                $facilityId = $game->facility_id;
                $pointsWin = 4;
                $pointsDraw = 2;
                $pointsLoss = 1;

                $participantUserIds = $participants->pluck('user_id')->all();
                $usersById = User::query()
                    ->whereIn('id', $participantUserIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $existingStats = PlayerStats::where('facility_id', $facilityId)
                    ->whereIn('user_id', $participantUserIds)
                    ->get()
                    ->keyBy('user_id');

                foreach ($participants as $p) {
                    $player = $usersById->get($p->user_id);
                    if ($player) {
                        $opponents = $participants->where('user_id', '!=', $p->user_id);
                        $opponentRatingAvg = (float) $opponents->avg(fn (GameParticipant $opponent) => (int) ($usersById->get($opponent->user_id)?->global_rating ?? 0));
                        $opponentTierAvg = (float) $opponents->avg(fn (GameParticipant $opponent) => (int) ($usersById->get($opponent->user_id)?->tier ?? 0));

                        $resultScore = $this->resultScore($p->result);
                        $expectedScore = $this->expectedScore((int) $player->global_rating, $opponentRatingAvg);
                        $tierGap = $opponentTierAvg - (int) $player->tier;
                        $tierFactor = 1 + max(-0.2, min(0.2, $tierGap * 0.01));

                        $delta = (int) round(24 * ($resultScore - $expectedScore) * $tierFactor);
                        $delta = max(-30, min(30, $delta));

                        $newRating = max(0, (int) $player->global_rating + $delta);
                        $player->update([
                            'global_rating' => $newRating,
                            'tier' => $this->tierFromRating($newRating),
                        ]);
                    }

                    $stats = $existingStats->get($p->user_id);
                    if (! $stats) {
                        $stats = PlayerStats::create([
                            'user_id' => $p->user_id,
                            'facility_id' => $facilityId,
                            'wins' => 0,
                            'losses' => 0,
                            'points' => 0,
                        ]);
                        $existingStats->put($p->user_id, $stats);
                    }

                    if ($p->result === 'win') {
                        $stats->increment('wins');
                        $stats->increment('points', $pointsWin);
                    } elseif ($p->result === 'loss') {
                        $stats->increment('losses');
                        $stats->increment('points', $pointsLoss);
                    } else {
                        $stats->increment('points', $pointsDraw);
                    }
                }

                $game->update([
                    'stats_applied_at' => now(),
                    'status' => Game::STATUS_COMPLETED,
                ]);
            }
        });

        $game = $game->fresh()->load(['facility', 'creator', 'winners', 'participants.user']);
        GameResultConfirmed::dispatch($game);

        return $this->apiSuccess('Result confirmed.', [
            'game' => $game,
            'majority_reached' => $allConfirmed,
            'all_confirmed' => $allConfirmed,
        ]);
    }

    /**
     * Leave a game: remove the current user from participants.
     * Only non-creator participants can leave. Allowed for awaiting_confirmation and ongoing games.
     * If participants drop below 2 after leaving, the game is auto-aborted.
     */
    public function leave(Request $request, Game $game): JsonResponse
    {
        $userId = $request->user()->id;

        if ($game->creator_id === $userId) {
            return $this->apiError('The game creator cannot leave. Abort the game instead.', null, 403);
        }

        if (in_array($game->status, [Game::STATUS_COMPLETED, Game::STATUS_CANCELLED], true)) {
            return $this->apiError('Cannot leave a game that is already completed or cancelled.', null, 403);
        }

        if (! in_array($game->status, [Game::STATUS_AWAITING_CONFIRMATION, Game::STATUS_ONGOING], true)) {
            return $this->apiError('You can only leave games that are awaiting confirmation or ongoing.', null, 403);
        }

        $participant = GameParticipant::where('game_id', $game->id)
            ->where('user_id', $userId)
            ->first();

        if (! $participant) {
            return $this->apiError('You are not a participant in this game.', null, 404);
        }

        $leftUser = $request->user();
        $participant->delete();

        $remainingCount = $game->participants()->count();

        if ($remainingCount < 2) {
            $facilityId = (int) $game->facility_id;
            $gameId = (int) $game->id;
            $notifiedUserIds = $game->participants()->pluck('user_id')->all();
            $notifiedUserIds[] = (int) $game->creator_id;
            $notifiedUserIds[] = $userId;

            DB::transaction(function () use ($game) {
                $game->participants()->delete();
                $game->delete();
            });

            GameAborted::dispatch($gameId, $facilityId, $notifiedUserIds);

            return $this->apiSuccess('You left the game. The game was aborted because there were not enough participants.');
        }

        $game = $game->fresh()->load(['facility', 'creator', 'participants.user']);
        $creatorId = (int) $game->creator_id;
        GameInvitationResponded::dispatch($game, [$creatorId, $userId], 'leave', $leftUser);

        return $this->apiSuccess('You have left the game.', ['game' => $game]);
    }

    /**
     * Abort a game: delete the game and all its participants (invited or otherwise).
     * Only the game creator can abort. Allowed for awaiting_confirmation and ongoing games.
     */
    public function abort(Request $request, Game $game): JsonResponse
    {
        if ($game->creator_id !== $request->user()->id) {
            return $this->apiError('Only the game creator can abort the game.', null, 403);
        }

        if (in_array($game->status, [Game::STATUS_COMPLETED, Game::STATUS_CANCELLED], true)) {
            return $this->apiError('Cannot abort a game that is already completed or cancelled.', null, 403);
        }

        $facilityId = (int) $game->facility_id;
        $gameId = (int) $game->id;
        $notifiedUserIds = $game->participants()->pluck('user_id')->all();
        $notifiedUserIds[] = (int) $game->creator_id;

        DB::transaction(function () use ($game) {
            $game->participants()->delete();
            $game->delete();
        });

        GameAborted::dispatch($gameId, $facilityId, $notifiedUserIds);

        return $this->apiSuccess('Game aborted. Game and all participants have been removed.');
    }

    private function resultScore(?string $result): float
    {
        return match ($result) {
            'win' => 1.0,
            'loss' => 0.0,
            default => 0.5,
        };
    }

    private function expectedScore(int $playerRating, float $opponentRatingAvg): float
    {
        return 1 / (1 + 10 ** (($opponentRatingAvg - $playerRating) / 400));
    }

    private function tierFromRating(int $rating): int
    {
        return (int) min(100, floor(max(0, $rating) / 100));
    }
}
