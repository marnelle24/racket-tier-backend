# Authorization Rules Audit

Audit date: 2026-02-13. Scope: game and facility endpoints; ensure creator/invited/participant rules and no cross-user data modification.

---

## Summary

| Rule | Status | Implementation |
|------|--------|----------------|
| Only creator can submit result | OK | `submitResult`: 403 unless `$game->creator_id === $request->user()->id`. Result allowed only when game status is `ongoing`. |
| Only invited users can respond | OK | `respond`: 403 for creator; 404 if no `GameParticipant` for current user (i.e. not invited). |
| Only participants can confirm | OK | `confirm`: 403 if no participant record for current user. |
| Users cannot confirm twice | OK | `confirm`: 403 if `participant->confirmed_at !== null`; double-checked inside transaction with lock. |
| Users cannot modify other users' data | OK | No endpoint allows one user to change another user's profile, presence, or game data except the intended flow: creator submits result for all participants. |

---

## Endpoint-by-endpoint authorization

### GameController

| Action | Who can act | Checks |
|--------|-------------|--------|
| **start** | Creator only | `$game->creator_id !== $request->user()->id` → 403. Status must be `awaiting_confirmation`. |
| **invite** | Creator only | Same creator check. Status must be `awaiting_confirmation`. |
| **respond** | Invited users only (not creator) | Creator → 403. No participant row for current user → 404. Participant can accept/decline (updates only own row). |
| **submitResult** | Creator only | Creator check → 403. Status must be `ongoing` (completed/cancelled/awaiting_result_confirmation/awaiting_confirmation → 403). Validation ensures payload only references current game participants. |
| **confirm** | Participants only, once each | No participant row → 403. `participant->confirmed_at !== null` → 403. Inside transaction: lock participant row, re-check `confirmed_at`, then set `confirmed_at` for current user only. |
| **abort** | Creator only | Creator check → 403. |

### Other controllers

- **Auth**: register/login act on unauthenticated requests; logout/me require auth and act on current user only.
- **FacilityJoinController::join**: Uses `$request->user()->id` only; creates/updates only the current user's presence.
- **FacilityController::players**, **StatsController::facility**: Read-only; no modification of user data.

---

## Data modification scope

- **Creator submitting result**: Intentionally sets `result` (and `result_confirmed_at`) on every `GameParticipant` for that game. This is the only allowed case of one user writing data that affects others; it is confined to the game’s participants and to result fields.
- **Respond**: User updates or deletes only their own `GameParticipant` row (`invitation_responded_at` or row delete).
- **Confirm**: User updates only their own `GameParticipant.confirmed_at` (row identified by `game_id` + current `user_id`).
- **Facility join**: Only current user’s `FacilityPresence` is created/updated.

No endpoint allows a user to change another user’s profile, credentials, or stats directly; stats are updated only by the system when a game is completed after majority confirmation.

---

## Change made during audit

- **submitResult**: Require `$game->status === Game::STATUS_ONGOING` before accepting a result. Previously, result could be submitted for `awaiting_confirmation` games. Now returns 403 with message: "Result can only be submitted for an ongoing game. Start the game first."
