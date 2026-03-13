<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\FacilityPresence;
use App\Models\Game;
use App\Models\GameParticipant;
use App\Models\PlayerStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * Get facility-specific stats. Auth required.
     * Only users with a presence at the facility can access. Returns 404 otherwise.
     * Returns 404 for non-existing facility IDs.
     * Returns user_id, games_played, wins, losses, points; ordered by points descending.
     */
    public function facility(Request $request, string $facility_id)
    {
        $request->merge(['facility_id' => $facility_id]);
        $request->validate([
            'facility_id' => ['required', 'integer'],
        ]);

        $facilityId = (int) $facility_id;
        if (! Facility::where('id', $facilityId)->exists()) {
            return $this->apiError('Not found.', null, 404);
        }
        $notFound = $this->requireFacilityPresence($request, $facilityId);
        if ($notFound !== null) {
            return $notFound;
        }

        $stats = PlayerStats::query()
            ->where('facility_id', $facility_id)
            ->with('user:id,name,nickname,global_rating,tier,avatar_seed')
            ->orderByDesc('points')
            ->get()
            ->map(fn (PlayerStats $row, int $index) => [
                'rank' => $index + 1,
                'user_id' => $row->user_id,
                'user' => $row->user ? [
                    'id' => $row->user->id,
                    'name' => $row->user->name,
                    'nickname' => $row->user->nickname,
                    'global_rating' => $row->user->global_rating,
                    'tier' => $row->user->tier,
                    'avatar_seed' => $row->user->avatar_seed,
                ] : null,
                'games_played' => $row->wins + $row->losses,
                'wins' => $row->wins,
                'losses' => $row->losses,
                'points' => $row->points,
            ])
            ->values();

        return $this->apiSuccess('Stats retrieved.', $stats);
    }

    /**
     * Personal stats overview: global rating/tier, aggregate totals,
     * per-sport breakdown, and per-facility performance.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $facilityStats = PlayerStats::where('user_id', $userId)
            ->with('facility:id,name')
            ->get();

        $totalWins = $facilityStats->sum('wins');
        $totalLosses = $facilityStats->sum('losses');
        $totalGames = $totalWins + $totalLosses;
        $winRate = $totalGames > 0 ? round($totalWins / $totalGames * 100, 1) : 0;

        $perSport = GameParticipant::where('game_participants.user_id', $userId)
            ->join('games', 'games.id', '=', 'game_participants.game_id')
            ->where('games.status', Game::STATUS_COMPLETED)
            ->whereNotNull('game_participants.result')
            ->select(
                'games.sport',
                DB::raw('COUNT(*) as games_played'),
                DB::raw("SUM(CASE WHEN game_participants.result = 'win' THEN 1 ELSE 0 END) as wins"),
                DB::raw("SUM(CASE WHEN game_participants.result = 'loss' THEN 1 ELSE 0 END) as losses"),
                DB::raw("SUM(CASE WHEN game_participants.result = 'draw' THEN 1 ELSE 0 END) as draws")
            )
            ->groupBy('games.sport')
            ->orderByDesc('games_played')
            ->get()
            ->map(fn ($row) => [
                'sport' => $row->sport,
                'games_played' => (int) $row->games_played,
                'wins' => (int) $row->wins,
                'losses' => (int) $row->losses,
                'draws' => (int) $row->draws,
                'win_rate' => $row->games_played > 0
                    ? round($row->wins / $row->games_played * 100, 1)
                    : 0,
            ]);

        $perFacility = $facilityStats->map(fn (PlayerStats $s) => [
            'facility_id' => $s->facility_id,
            'facility_name' => $s->facility?->name ?? 'Unknown',
            'games_played' => $s->wins + $s->losses,
            'wins' => $s->wins,
            'losses' => $s->losses,
            'points' => $s->points,
            'win_rate' => ($s->wins + $s->losses) > 0
                ? round($s->wins / ($s->wins + $s->losses) * 100, 1)
                : 0,
        ])->sortByDesc('points')->values();

        $facilitiesVisited = FacilityPresence::where('user_id', $userId)->count();

        $streakRows = GameParticipant::where('game_participants.user_id', $userId)
            ->join('games', 'games.id', '=', 'game_participants.game_id')
            ->where('games.status', Game::STATUS_COMPLETED)
            ->whereNotNull('game_participants.result')
            ->orderByDesc('games.end_time')
            ->select('game_participants.result')
            ->limit(50)
            ->get();

        $currentStreak = 0;
        $currentStreakType = null;
        foreach ($streakRows as $row) {
            if ($currentStreakType === null) {
                $currentStreakType = $row->result;
                $currentStreak = 1;
            } elseif ($row->result === $currentStreakType) {
                $currentStreak++;
            } else {
                break;
            }
        }

        $uniqueOpponents = GameParticipant::where('game_participants.user_id', '!=', $userId)
            ->whereIn('game_participants.game_id', function ($q) use ($userId) {
                $q->select('game_id')
                    ->from('game_participants')
                    ->where('user_id', $userId);
            })
            ->join('games', 'games.id', '=', 'game_participants.game_id')
            ->where('games.status', Game::STATUS_COMPLETED)
            ->distinct('game_participants.user_id')
            ->count('game_participants.user_id');

        return $this->apiSuccess('Personal stats retrieved.', [
            'global_rating' => (int) $user->global_rating,
            'tier' => (int) $user->tier,
            'name' => $user->name,
            'member_since' => $user->created_at?->toIso8601String(),
            'totals' => [
                'games_played' => $totalGames,
                'wins' => $totalWins,
                'losses' => $totalLosses,
                'win_rate' => $winRate,
                'facilities_visited' => $facilitiesVisited,
            ],
            'per_sport' => $perSport,
            'per_facility' => $perFacility,
            'streaks' => [
                'current_type' => $currentStreakType,
                'current_count' => $currentStreak,
            ],
            'unique_opponents' => $uniqueOpponents,
        ]);
    }

    /**
     * Paginated match history across all facilities.
     */
    public function history(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $perPage = min((int) ($request->query('per_page', 15)), 50);

        $games = Game::where('status', Game::STATUS_COMPLETED)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->with([
                'facility:id,name',
                'participants.user:id,name,avatar_seed',
                'winners:id,name',
            ])
            ->orderByDesc('end_time')
            ->paginate($perPage);

        $items = collect($games->items())->map(function (Game $game) use ($userId) {
            $myParticipant = $game->participants->firstWhere('user_id', $userId);
            $opponents = $game->participants
                ->where('user_id', '!=', $userId)
                ->map(fn (GameParticipant $p) => [
                    'user_id' => $p->user_id,
                    'name' => $p->user?->name ?? "Player {$p->user_id}",
                    'result' => $p->result,
                    'avatar_seed' => $p->user?->avatar_seed,
                ])
                ->values();

            $durationMinutes = null;
            if ($game->start_time && $game->end_time) {
                $durationMinutes = (int) $game->start_time->diffInMinutes($game->end_time);
            }

            return [
                'game_id' => $game->id,
                'sport' => $game->sport,
                'match_type' => $game->match_type,
                'facility_id' => $game->facility_id,
                'facility_name' => $game->facility?->name ?? 'Unknown',
                'result' => $myParticipant?->result,
                'score' => $game->score,
                'duration_minutes' => $durationMinutes,
                'opponents' => $opponents,
                'end_time' => $game->end_time?->toIso8601String(),
            ];
        });

        return $this->apiSuccess('Match history retrieved.', [
            'games' => $items,
            'pagination' => [
                'current_page' => $games->currentPage(),
                'last_page' => $games->lastPage(),
                'per_page' => $games->perPage(),
                'total' => $games->total(),
            ],
        ]);
    }
}
