# API Endpoints Audit

Audit date: 2026-02-13. Scope: input validation, required fields, invalid facility/game IDs, unauthorized access.

---

## Summary

| Area | Status | Notes |
|------|--------|--------|
| **Invalid game IDs** | OK | Game routes use `{game}` model binding → 404 when game not found. |
| **Invalid facility IDs** | Gaps | Three endpoints accepted invalid or non-existent facility IDs; fixes applied. |
| **Required fields** | OK | Required fields enforced via validation or explicit checks. |
| **Unauthorized access** | Partial | Game actions correctly restrict by creator/participant; facility/stats list endpoints do not scope by presence. |

---

## Endpoint-by-endpoint

### Auth (no auth required for register/login)

| Endpoint | Validation | Required fields | Unauthorized | Notes |
|----------|-------------|-----------------|--------------|--------|
| `POST /register` | name, email, password (min:8), email unique | All present | N/A | 422 with `errors` on validation. |
| `POST /login` | email, password | Both | N/A | ValidationException → 422. Same structure as register if you use same exception handler. |

### Auth (auth:sanctum)

| Endpoint | Validation | Required fields | Invalid IDs | Unauthorized |
|----------|-------------|-----------------|-------------|--------------|
| `POST /logout` | None | N/A | N/A | OK (token required). |
| `GET /me` | None | N/A | N/A | OK. |
| `POST /facilities/join` | token required, string | token | Invalid token → 404 | OK. |
| `GET /facilities/{id}/players` | **Fixed:** id integer, exists:facilities,id | id in URL | **Was:** no check (empty list). **Now:** 422 for invalid/non-existent. | Any authenticated user can list any facility’s players. Consider restricting to users with presence at that facility. |
| `GET /games` | **Fixed:** facility_id integer, exists:facilities,id | facility_id (query) | **Was:** no check (empty list). **Now:** 422 for missing/invalid/non-existent. | Any authenticated user can list games for any facility. Consider restricting to users with presence. |
| `POST /games` | facility_id, sport; creator must have presence | facility_id, sport | OK (exists:facilities,id) | 403 if user not at facility or has active game. |
| `POST /games/{game}/start` | N/A (game from binding) | N/A | 404 if game missing | 403 unless creator; status must be awaiting_confirmation. |
| `POST /games/{game}/invite` | user_ids array, user_ids.* integer, exists:users | user_ids | 404 if game missing | 403 unless creator; status awaiting_confirmation. Suggestion: cap array size (e.g. max:50) to avoid abuse. |
| `POST /games/{game}/respond` | action in:accept,decline | action | 404 if game missing | 403 for creator; 404 if not invited. |
| `POST /games/{game}/result` | results array, one per participant; result in win,loss,draw | results | 404 if game missing | 403 unless creator. Suggestion: require status === ongoing (currently allows awaiting_confirmation). |
| `POST /games/{game}/confirm` | N/A | N/A | 404 if game missing | 403 unless participant; 403 if already confirmed. |
| `POST /games/{game}/abort` | N/A | N/A | 404 if game missing | 403 unless creator; 403 if completed/cancelled. |
| `GET /stats/facility/{facility_id}` | **Fixed:** facility_id integer, exists:facilities,id | facility_id in URL | **Was:** no check (empty list). **Now:** 422 for invalid/non-existent. | Any authenticated user can view any facility’s stats. Consider restricting to users with presence. |

---

## Improvements made (no new features)

1. **FacilityController::players** – Validate `id` (route param merged into request) as required, integer, and `exists:facilities,id`. Invalid or non-existent facility returns 422.
2. **GameController::index** – Validate `facility_id` as required, integer, and `exists:facilities,id`. Missing, invalid, or non-existent facility returns 422.
3. **StatsController::facility** – Validate `facility_id` (route param merged into request) as required, integer, and `exists:facilities,id`. Invalid or non-existent facility returns 422.

---

## Suggested improvements (not implemented)

- ~~**submitResult** – Explicitly require `$game->status === Game::STATUS_ONGOING`~~ **Done** (2026-02-13; see AUTHORIZATION_AUDIT.md).
- **invite** – Add `max:50` (or similar) on `user_ids` to limit request size and avoid abuse.
- **GET /facilities/{id}/players**, **GET /games**, **GET /stats/facility/{id}** – Consider restricting to users who have an active presence at the given facility (or another policy) so that only authorized users can list players, games, or stats for a facility.
