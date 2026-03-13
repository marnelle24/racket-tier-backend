<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\FacilityPresence;
use App\Models\PlayerStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FacilityController extends Controller
{
    /**
     * List all facilities with optional name search and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Facility::query()
            ->select('id', 'name', 'join_token', 'country', 'address')
            ->withCount([
                'presences as active_players' => fn ($q) => $q->where('last_seen_at', '>=', now()->startOfDay()),
            ])
            ->orderBy('name');
        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = (int) ($validated['per_page'] ?? 10);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (Facility $facility) => [
                'facility_id' => $facility->id,
                'name' => $facility->name,
                'join_token' => $facility->join_token ?? null,
                'country' => $facility->country ?? 'Philippines',
                'address' => $facility->address,
                'active_players' => (int) ($facility->active_players ?? 0),
            ])
            ->values();

        return $this->apiSuccess('Facilities retrieved.', [
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Public check: does this facility exist? Returns 404 if not, 200 with minimal data if yes.
     * Used by the frontend to show 404 for invalid facility IDs (with or without auth).
     */
    public function show(string $id): JsonResponse
    {
        $facilityId = filter_var($id, FILTER_VALIDATE_INT);
        if ($facilityId === false) {
            return $this->apiError('Not found.', null, 404);
        }

        $facility = Facility::find($facilityId);
        if (! $facility) {
            return $this->apiError('Not found.', null, 404);
        }

        return $this->apiSuccess('Facility found.', [
            'id' => $facility->id,
            'name' => $facility->name,
            'country' => $facility->country ?? 'Philippines',
            'address' => $facility->address,
        ]);
    }

    /**
     * List facilities the current user has checked in to, with join date, last seen, and games played.
     */
    public function mine(Request $request): JsonResponse
    {
        $presences = FacilityPresence::where('user_id', $request->user()->id)
            ->with('facility:id,name,country,address')
            ->orderByDesc('last_seen_at')
            ->get();

        $stats = PlayerStats::where('user_id', $request->user()->id)
            ->whereIn('facility_id', $presences->pluck('facility_id'))
            ->get()
            ->keyBy('facility_id');

        $facilities = $presences->map(function (FacilityPresence $p) use ($stats) {
            $ps = $stats->get($p->facility_id);
            $wins = $ps ? $ps->wins : 0;
            $losses = $ps ? $ps->losses : 0;
            $gamesPlayed = $wins + $losses;

            return [
                'facility_id' => $p->facility_id,
                'name' => $p->facility?->name ?? 'Unknown',
                'country' => $p->facility?->country ?? 'Philippines',
                'address' => $p->facility?->address,
                'joined_at' => $p->joined_at->toIso8601String(),
                'last_seen_at' => $p->last_seen_at->toIso8601String(),
                'games_played' => $gamesPlayed,
                'wins' => $wins,
                'losses' => $losses,
            ];
        })->values();

        return $this->apiSuccess('Facilities retrieved.', $facilities);
    }

    /**
     * Get the current user's facility_presences row for a facility (last_seen_at, joined_at).
     * Returns 404 if the user is not checked in to this facility.
     */
    public function presence(Request $request, string $id): JsonResponse
    {
        $facilityId = filter_var($id, FILTER_VALIDATE_INT);
        if ($facilityId === false) {
            return $this->apiError('Not found.', null, 404);
        }

        $presence = FacilityPresence::where('user_id', $request->user()->id)
            ->where('facility_id', $facilityId)
            ->with('facility:id,name')
            ->first();

        if (! $presence) {
            return $this->apiError('Not found.', null, 404);
        }

        return $this->apiSuccess('Presence retrieved.', [
            'last_seen_at' => $presence->last_seen_at->toIso8601String(),
            'joined_at' => $presence->joined_at->toIso8601String(),
            'facility_name' => $presence->facility?->name ?? null,
        ]);
    }

    /**
     * List active players at a facility (users with presence and last_seen_at within today).
     */
    public function players(Request $request, int $id): JsonResponse
    {
        $request->merge(['id' => $id]);
        $request->validate(['id' => ['required', 'integer', 'exists:facilities,id']]);

        $notFound = $this->requireFacilityPresence($request, $id);
        if ($notFound !== null) {
            return $notFound;
        }

        $presences = FacilityPresence::where('facility_id', $id)
            ->where('last_seen_at', '>=', now()->startOfDay())
            ->with('user:id,name,nickname,avatar_seed,global_rating,tier')
            ->orderBy('last_seen_at', 'desc')
            ->get();

        $players = $presences->map(fn ($p) => [
            'user_id' => $p->user_id,
            'name' => $p->user?->name ?? "Player {$p->user_id}",
            'nickname' => $p->user?->nickname,
            'avatar_seed' => $p->user?->avatar_seed,
            'tier' => (int) ($p->user?->tier ?? 0),
            'global_rating' => (int) ($p->user?->global_rating ?? 0),
        ]);

        return $this->apiSuccess('Players retrieved.', $players);
    }

    /**
     * Create a new facility.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'join_token' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('facilities', 'join_token'),
            ],
            'country' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $createData = [
            'name' => $validated['name'],
            'country' => $validated['country'] ?? 'Philippines',
            'address' => $validated['address'] ?? null,
        ];
        $joinToken = isset($validated['join_token']) ? trim($validated['join_token']) : null;
        if ($joinToken !== '' && $joinToken !== null) {
            $createData['join_token'] = $joinToken;
        }

        $facility = Facility::create($createData);

        return $this->apiSuccess('Facility created.', [
            'facility_id' => $facility->id,
            'name' => $facility->name,
            'country' => $facility->country,
            'address' => $facility->address,
            'join_token' => $facility->join_token,
        ], 201);
    }

    /**
     * Update an existing facility.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $facilityId = filter_var($id, FILTER_VALIDATE_INT);
        if ($facilityId === false) {
            return $this->apiError('Not found.', null, 404);
        }

        $facility = Facility::find($facilityId);
        if (! $facility) {
            return $this->apiError('Not found.', null, 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'join_token' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('facilities', 'join_token')->ignore($facility->id),
            ],
            'country' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $updateData = collect($validated)->filter(fn ($v) => $v !== null && $v !== '')->all();
        if (isset($updateData['join_token']) && trim((string) $updateData['join_token']) === '') {
            unset($updateData['join_token']);
        } elseif (isset($updateData['join_token'])) {
            $updateData['join_token'] = trim((string) $updateData['join_token']);
        }
        $facility->update($updateData);

        return $this->apiSuccess('Facility updated.', [
            'facility_id' => $facility->id,
            'name' => $facility->name,
            'country' => $facility->country,
            'address' => $facility->address,
        ]);
    }

    /**
     * Delete a facility.
     */
    public function destroy(string $id): JsonResponse
    {
        $facilityId = filter_var($id, FILTER_VALIDATE_INT);
        if ($facilityId === false) {
            return $this->apiError('Not found.', null, 404);
        }

        $facility = Facility::find($facilityId);
        if (! $facility) {
            return $this->apiError('Not found.', null, 404);
        }

        $facility->delete();

        return $this->apiSuccess('Facility deleted.', null);
    }
}
