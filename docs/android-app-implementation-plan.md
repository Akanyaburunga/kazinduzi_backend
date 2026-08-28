# 📱 Kazinduzi Android App — API Spec & Phased Implementation Plan

This document is the contract between the **Kazinduzi backend** (Laravel 13 + Sanctum, finished through Phase C7) and the **Android client** that consumes it. It lists every endpoint the mobile app needs, the exact request/response shapes, and a dependency-ordered set of build phases so a single Android developer can deliver a complete, shippable app incrementally.

It is **backend-only documentation** — the Android client is a separate codebase. This plan assumes you build one native Android app (Kotlin, single-module is fine) that talks to this JSON API.

---

## 0. Reading Guide

- **Auth:** nearly every mobile endpoint requires a **Bearer token** (`Authorization: Bearer <token>`) from login, plus an **email-verified** account. Unverified/expired tokens return `401`.
- **Envelope:** all responses are `{ success, data, ... }`. `success` is always boolean. `data` varies. Errors are `{ success: false, message }` (and sometimes `errors` for validation).
- **Never** assume any payload contains a riddle `answer` — the backend deliberately omits it until the player correctly solves that riddle.
- **Base URL:** `https://<your-domain>/api` (dev commonly uses a Laravel Valet/Herd `.test` or a LAN IP + `php artisan serve`).
- **Dates:** ISO-8601 strings. Tokens: `expires_at` null = no expiry.
- **Rate limits** apply per authenticated user/IP — surface `429` handling.

### Common HTTP status codes you must handle
| Code | Meaning | Client action |
|------|---------|---------------|
| 200/201 | Success | Use `data` |
| 400 | Bad request / invalid code | Show `message` |
| 401 | Unauthenticated / invalid token | Force re-login |
| 403 | Forbidden (not admin / not participant) | Show error |
| 404 | Not found | Show error / handle empty |
| 422 | Validation / business rule | Show first `errors` value or `message` |
| 429 | Rate limited | Back off, retry later |

---

## 1. Authentication & Profile

### 1.1 Register — `POST /auth/register`
Body: `name`, `email`, `password`, `password_confirmation` (password min 6, must match confirmation).

```json
// 201
{ "success": true, "message": "Registration successful. A verification code has been sent to your email.", "data": null }
```
> **Note:** In non-production environments the account is **auto-verified** (no email is sent). In production a 6-digit code is emailed.

### 1.2 Verify email — `GET /auth/email/verify/{id}/{hash}`
This is a web-route (click-through). **Android flow:** use the paired JSON endpoints below instead.

### 1.3 Resend code — `POST /auth/email/resend` (throttle `3/min`)
Body: `email`.
```json
{ "success": true, "message": "A new verification code has been sent.", "data": null }
```

### 1.4 Login — `POST /auth/login` (throttle `10/min`)
Body: `email`, `password`, optional `device_name` (default `AndroidApp`). One active token is kept **per device name** (old token revoked).

```json
{
  "success": true,
  "message": "Logged in successfully.",
  "data": {
    "user": {
      "id": 1, "name": "Blaise", "email": "b@x.com", "email_verified_at": "2026-08-28T10:00:00Z",
      "profile_picture": null, "referral_code": "1_66f9...",
      "reputation": 0, "current_streak": 0, "longest_streak": 0, "streak_freezes": 3
    },
    "token": "1|abc...plaintext...",
    "token_type": "Bearer",
    "expires_at": null
  }
}
```
- **401** on bad credentials.
- **422** if not yet verified (web-layer verified middleware) → direct user to verify flow.

### 1.5 Logout — `POST /auth/logout`
Body: optional `device_name` (revokes that device only) — omit to revoke all. Revoke locally + call endpoint.

### 1.6 Get current user — `GET /auth/user`
```json
{ "success": true, "data": { "id": 1, "name": "...", "email": "...", "reputation": 5, ... } }
```

### 1.7 Change password — `POST /auth/password/change`
Body: `current_password`, `password`, `password_confirmation`. Revokes all tokens → client must re-login.

---

## 2. Game Play Loop

All under `auth:sanctum` + `verified`, except the public share resolution.

### 2.1 List riddles — `GET /riddles`
Query: `category_id`, `type` (`what_am_i|what_is_it|who_am_i|riddle|brain_teaser|math`), `sort` (`new|trending`).
```json
{
  "success": true,
  "data": [
    {
      "id": 5, "solved": false, "hints_revealed": 0,
      "category": { "id": 2, "name": "Inkuru", "slug": "inkuru" },
      "question": "I have cities but no houses...", "difficulty": "medium", "riddle_type": "riddle",
      "tags": [], "hint": "A map.", "hint2": null, "created_at": "..."
    }
  ]
}
```

### 2.2 Single riddle — `GET /riddles/{id}` → same payload shape as above (no answer).

### 2.3 Trend — `GET /riddles/trending` → array of most-popular riddles (same payload).

### 2.4 Daily riddle — `GET /riddles/daily`
```json
{
  "success": true,
  "data": {
    "streak": { "current": 2, "longest": 5 },
    "solved_by_count": 128,
    "best_streak": 21,
    "daily": { "id": 3, "solved": false, "hints_revealed": 0, "category": {...}, "question": "...", "difficulty": "easy", "riddle_type": "riddle", "tags": [], "hint": "...", "hint2": null, "created_at": "..." }
  }
}
```

### 2.5 Daily history — `GET /riddles/daily/history?date=YYYY-MM-DD` → same as daily (replay a past date).

### 2.6 Daily status (badges / bell) — `GET /riddles/daily/status`
```json
{
  "success": true,
  "data": {
    "daily_available": true,
    "streak_at_risk": false,
    "pending_challenges": 1,
    "streak": { "current": 2, "longest": 5 }
  }
}
```
Use `daily_available` to enable/disable the "Solve today" button; `streak_at_risk` for a warning chip; `pending_challenges` for a duels inbox badge.

### 2.7 Next unsolved riddle — `GET /riddles/next?difficulty=medium`
Returns a single riddle (payload above) the user hasn't solved. `404` when everything is solved.

### 2.8 Reveal a hint — `GET /riddles/{id}/hint`
Returns `{ id, hint, hint2, hints_revealed: 2 }` and marks the riddle as hint-used (affects the "no hint" badge). Use to progressively reveal hints.

### 2.9 Answer a riddle — `POST /riddles/{id}/answer` (throttle `30/min`)
Body: `{ "answer": "a map" }` — answer comparison ignores case/accents/whitespace.
```json
{
  "success": true,
  "correct": true,
  "rewarded": true,
  "points": 5,
  "capped": false,
  "message": "Correct! You earned 5 reputation points.",
  "new_achievements": [ { "slug": "first_riddle", "name": "First Riddle", "description": "...", "icon": "..." } ]
}
```
- `correct` false → no points, message `"Not quite. Try again."`
- Only one exact riddle+user row is kept — re-solving the same riddle rewards **once** (first correct solve).
- Education path: `POST /riddles/{id}/reveal` returns `{ id, question, answer }` with **no reward** (learning mode).

### 2.10 History — `GET /riddles/history?per_page=15` → paginated attempt history (attempt `id`, `riddle` object, `submitted_answer`, `is_correct`, `rewarded`, `attempted_at`). No answers.

### 2.11 Stats — `GET /riddles/history/stats`
```json
{
  "success": true,
  "data": {
    "total_attempts": 12, "riddles_solved": 8, "unique_riddles": 8, "accuracy": 66.7,
    "by_category": [ { "category_id": 2, "name": "Inkuru", "attempts": 5, "solved": 4 } ]
  }
}
```

### 2.12 Streak freeze — `POST /riddles/streak/freeze`
Spends one freeze to protect today's streak. Returns `{ freezes_remaining, freeze_active, streak:{current,longest} }`. `422` if none remain / already frozen today.

---

## 3. Categories

### 3.1 List — `GET /riddles/categories`
```json
{ "success": true, "data": [ { "id": 1, "name": "Inyamaswa", "slug": "inyamaswa", "description": "...", "riddles_count": 34 } ] }
```
(Curator create/update/delete also exist under `POST/PUT/DELETE /riddles/categories` — only expose these to users above a reputation threshold if you implement "curator" flows.)

> The game can filter the riddle list by `category_id` (see 2.1).

---

## 4. Points, Levels & Achievements

### 4.1 Profile summary — `GET /me/summary` (single call to render the home screen)
```json
{
  "success": true,
  "data": {
    "user": { "id": 1, "name": "Blaise", "profile_picture_url": "https://.../default-profile.png" },
    "points": { "reputation": 120, "level": 3 },
    "streak": { "current": 4, "longest": 9 },
    "badges": { "earned_count": 5, "total": 10, "earned_slugs": ["first_riddle","streak_3","..."] },
    "favorites_count": 7,
    "activity": {
      "total_attempts": 40, "riddles_solved": 25, "accuracy": 62.5, "unique_riddles": 24,
      "submissions_count": 2, "shares_count": 3
    }
  }
}
```
> Recommended as the single endpoint backing the app's Home screen (auto-refresh on resume).

### 4.2 Full profile — `GET /me`
Same idea with legacy fields (`stats.words_contributed`, `stats.meanings_contributed`, `stats.riddles_solved`).

### 4.3 Levels — `GET /me/levels`
```json
{ "success": true, "data": { "current": 3, "levels": [ { "level": 1, "title": "...", "min_reputation": 0 }, ... ] } }
```

### 4.4 Achievements / badges — `GET /me/achievements`
```json
{
  "success": true,
  "data": {
    "earned_count": 5, "total": 10,
    "achievements": [ { "id": 1, "slug": "streak_3", "name": "3-Day Streak", "description": "...", "category": "streak", "icon": "...", "threshold": 3, "metric": "current_streak", "earned": true, "earned_at": "...", "progress": 3, "goal": 3 } ]
  }
}
```

### 4.5 Points ledger — `GET /points?per_page=15`
```json
{
  "success": true,
  "data": {
    "total": 120,
    "history": { "current_page": 1, "data": [ { "id": 9, "change": 5, "reason": "Solved a riddle", "related_type": "...", "related_id": 3, "created_at": "..." } ], "total": 9, ... }
  }
}
```

---

## 5. Leaderboard

### 5.1 Ranked board — `GET /leaderboard?filter=all_time&page=1&per_page=20`
`filter` ∈ `today|this_week|this_month|this_year|all_time`. Ranks by **net positive** points earned in the period.
```json
{
  "success": true,
  "filter": "all_time",
  "data": [
    { "rank": 1, "id": 2, "name": "Aline", "points": 450, "words_contributed": 3, "meanings_contributed": 2, "profile_picture_url": "..." }
  ],
  "me": { "id": 1, "name": "Blaise", "rank": 12, "points": 120, "total_players": 40, "percentile": 72 },
  "meta": { "current_page": 1, "per_page": 20, "total": 40, "last_page": 2 }
}
```
- `me` gives "where am I" even if the user isn't on the current page — render a highlight row.

---

## 6. Favorites, Sharing & Progress

### 6.1 Favorites — `GET /me/favorites`, `POST /me/favorites/{riddle}`, `DELETE /me/favorites/{riddle}`
List returns solved-marked riddle payloads. POST idempotent; cannot favorite a suspended riddle.

### 6.2 Share a riddle — `POST /riddles/{id}/share`
Body: optional `recipient_email`. Returns a short `share_url` / `code` you can hand to Android share-sheets.

### 6.3 Resolve a shared link — `GET /riddles/share/{code}` (public, no auth)
Reveals the riddle (no answer) and counts the view. Deep-link target for `https://<domain>/r/<code>`-style URIs.

### 6.4 Saved progress — revealed hints are persisted server-side; `hints_revealed` in riddle payloads is the resume point.

---

## 7. Advanced Play: Leaderboards → Duels (PvP)

### 7.1 List my duels — `GET /duels`
Array of challenge payloads you are party to (incoming/outgoing).
```json
{
  "success": true,
  "data": [
    {
      "id": 11, "status": "pending", "wager": 10,
      "direction": "incoming",
      "accepted_at": null, "resolved_at": null,
      "riddle": { "id": 3, "question": "...", "difficulty": "easy", "riddle_type": "riddle", "category": {...}, "answer": null },
      "initiator": { "id": 2, "name": "Aline", "reputation": 300 },
      "opponent": { "id": 1, "name": "Blaise", "reputation": 120 },
      "my_attempt": null,
      "opponent_attempt": null,
      "winner_id": null,
      "created_at": "..."
    }
  ]
}
```
`status` ∈ `pending|accepted|declined|completed|expired`.

### 7.2 Create — `POST /duels`
Body: `opponent_id`, `riddle_id`, `wager` (0–`duel_max_wager`, default 20; cannot exceed your own reputation). Business `422`s: self-challenge, too-high wager, suspended riddle, opponent already solved it, duplicate pending duel with that opponent.

### 7.3 Show — `GET /duels/{id}`
Live status for participants. **The riddle's `answer` is only non-null if *you* solved it.** The opponent's `submitted_answer` is always null (anti-cheat).

### 7.4 Accept — `POST /duels/{id}/accept` (opponent only; `422` if wager beyond opponent's rep)
### 7.5 Decline — `POST /duels/{id}/decline` (opponent only; wager untouched)
### 7.6 Solve — `POST /duels/{id}/solve` with `{ "answer": "..." }` (one attempt per player)
```json
{
  "success": true,
  "data": { "correct": true, "resolved": false, "answer": null, "message": "Correct! Waiting on your opponent." }
}
```
- If `correct` → `answer` is revealed **to that player only**.
- If **both** solve, the duel resolves immediately: faster correct solve wins the wager (`resolved: true`).
- Timer/no-show: pending duels auto-expire after `duel_stale_hours` (24h); unfinished accepted duels settle to whoever solved (or void).

### 7.7 Duel lifecycle to render
| status | UI |
|--------|----|
| `pending` (incoming) | "Accept / Decline" buttons |
| `pending` (outgoing) | "Waiting for opponent" |
| `accepted` | Show riddle, answer box, "Waiting on opponent" after your move |
| `completed` | Winner banner + reputation delta |
| `declined` / `expired` | Inactive row |

---

## 8. User-Generated Submissions (curation)

### 8.1 Submit a riddle — `POST /submissions/riddles`
Body: `question`, `answer`, `difficulty`, `riddle_type`, `hint`?, `hint2`?, `source` (required). Creates a `pending` submission for admin review. `422` if the answer already exists.

### 8.2 My submissions — `GET /submissions/riddles` → list with `status` (`pending|approved|rejected`) and `rejection_reason`.

> Expose an in-app "Contribute a riddle" form. Approved submissions become live riddles.

---

## 9. Reputation, Fairness & Tuning Values

| Concept | Default | Notes |
|---------|---------|-------|
| `RIDDLE_SOLVE_REPUTATION` | 5 | Points per first correct solve |
| `DAILY_SOLVE_REPUTATION_CAP` | 50 | cap on daily solve earnings (and duel wager gains), anti-farming |
| `DUEL_MAX_WAGER` | 20 | max stake per duel |
| `DUEL_STALE_HOURS` | 24 | pending-expiry / no-show settle window |
| `STREAK_FREEZE_LIMIT` | 3 | streak-saver freezes granted at signup |
| Reputation = "points" | — | single source of truth for levels + leaderboard |

The Android app just reads these numbers; do **not** hardcode balances — always trust the API's `reputation`/`points` values.

---

## 10. Phased Implementation Plan (dependency-ordered, one PR/commit per phase)

Each phase is independently shippable and demonstrable. Suggested order:

### Phase A — Project scaffolding & networking
- Kotlin project, min SDK 24+, Material 3 theme.
- **Networking layer:** Retrofit + OkHttp + kotlinx-serialization (or Moshi). Add an `AuthInterceptor` that attaches the Bearer token.
- Central `ApiClient`, base URL config (debug vs release), JSON envelope model `{ success, data }`.
- **Secrets:** token stored in EncryptedSharedPreferences.
- **Acceptance:** a debug screen pings `GET /riddles` (requires login) and prints JSON.

### Phase B — Onboarding & auth
- Register, login, verify-email (production), logout, change-password.
- Token persistence + auth state holder; 401-interceptor → force re-login.
- **Acceptance:** full sign-up → login → session persists across app restarts.

### Phase C — Profiles, levels & badges (Home)
- Consume `GET /me/summary` (+ `/me`, `/me/levels`, `/me/achievements`).
- Render Home: avatar, name, level/progress, streak chip, badge grid, activity stats.
- **Acceptance:** Home fully populated from one summary call; offline caching of the payload.

### Phase D — Core game loop (play riddles)
- Riddle list (`/riddles`) with difficulty/type/category filters.
- Riddle screen: question, progressive hints (`/riddles/{id}/hint`), answer input → `POST /riddles/{id}/answer`.
- Reveal/learning mode (`/riddles/{id}/reveal`), solved marking, streak freeze.
- **Acceptance:** solve riddles, gain points, see solved state, attempt history + stats screens.

### Phase E — Daily riddle & streak experience
- Daily screen (`/riddles/daily`), daily history archive, status bell.
- `GET /riddles/daily/status` drives streak-at-risk warnings and the daily-available button.
- Streak freeze spend + confirmation UX.
- **Acceptance:** daily one-per-day loop with streak preservation working end to end.

### Phase F — Leaderboard
- `GET /leaderboard` with period filter tabs, pagination, highlighted "me" row from `me`.
- Pull-to-refresh.
- **Acceptance:** ranked list + own rank/percentile across periods.

### Phase G — Favorites, sharing & progress
- Favorite/unfavorite from riddle screen; favorites list.
- Share via native share sheet using `/riddles/{id}/share`; handle incoming deep links via `/riddles/share/{code}`.
- Show `hints_revealed` resume state.
- **Acceptance:** bookmark a riddle, share a riddle, deep-link opens it (answer hidden).

### Phase H — Badges/achievements polish
- Achievements screen from `/me/achievements` with earned state + progress bars.
- **Acceptance:** library of badges with per-badge progress and unlock toasts.

### Phase I — Duel (PvP) inbox & play
- Duels list (`/duels`) with incoming/outgoing and status badges (bell count from `daily.status.pending_challenges`).
- Accept/decline, create a duel (pick opponent + riddle + wager ≤ max).
- Live duel screen: riddle, single answer attempt, "waiting on opponent", resolution banner, reputation delta.
- **Acceptance:** end-to-end duel between two devices with correct winner/wager transfer.

### Phase J — Contributions
- "Submit a riddle" form → `POST /submissions/riddles`; track my submissions.
- **Acceptance:** submission queued, visible in "My submissions" with status.

### Phase K — Offline, caching & polish
- Cache `/me/summary`, riddle lists, categories for offline read.
- Pull-to-refresh everywhere; empty/error/loading states; 429 retry with backoff.
- **Acceptance:** app opens offline showing cached Home + plays after reconnect.

---

## 11. Conventions & Gotchas for the Android Dev

- **Always** pass responses through the `{ success, data }` envelope; treat `success=false` as an error even on HTTP 200 (some endpoints return business errors that way).
- **Read-only fields:** `riddle.answer` is confidential — never log it; only display after a correct solve or explicit `reveal`.
- **A single attempt per duel** — disable the answer button once sent; show "Waiting on opponent".
- **Daily riddle is deterministic per user/date** — don't cache it as "today's riddle" across dates.
- **Token expiry:** `expires_at` may be null (no expiry). Always handle 401 → relogin as the catch-all.
- **One active token per device name** — send a stable `device_name` (e.g., `"Android_" + installId`) so re-login doesn't explode tokens.
- **Time:** all times from the server are authoritative (streaks, duels, daily). Use server `created_at`/`solved_at` for clocks.

---

## 12. Suggested Build Order (combined)

1. A — networking + auth token plumbing
2. B — register/login/verify/logout
3. C — Home (summary, level, badges, streak)
4. D — play riddles (list, hint, answer, history)
5. E — daily + streak
6. F — leaderboard
7. G — favorites, share, deep links
8. H — achievements polish
9. I — duels (PvP inbox + play)
10. J — submissions
11. K — offline & polish

---

*Contract version 1.0 — reflects backend as of commit `310429c` (all phases A–C7 complete).*
