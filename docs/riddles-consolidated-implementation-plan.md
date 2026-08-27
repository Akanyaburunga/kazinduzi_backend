# 🧩 Kazinduzi Riddles — Consolidated Implementation Plan

## Overview

This document is the single source of truth for the next wave of the **riddle feature**, delivered
in dependency-ordered phases — one commit per completed phase, reviewed before moving on.

It consolidates three work-streams into one master plan:

- **Part A — Back-office operations on riddles** (web admin panel: creating, deleting, editing, moderating, etc.)
- **Part B — Mobile app backend** (Android API: authentication, playing, points, leaderboard + "my rank")
- **Part C — Riddle system improvements** (polish + innovation, taking inspiration from best-in-class riddle products such as riddles.com, Riddle.Me, Wordle-style daily games, and 2026 gamification best practices)

Much of the foundation already exists (see *What Already Exists*). This plan layers new work on top
and is intentionally ordered so each phase is independently shippable and testable.

---

## Status Legend

| Marker | Meaning |
|--------|---------|
| ✅ done | Already implemented and committed |
| 🔶 partial | Partially implemented; gaps listed |
| ⬜ planned | To be built in a phase below |

---

## What Already Exists (audit)

Sentence on how the current state was verified: controllers, routes, models, tests, admin UI
(see `routes/api.php`, `app/Http/Controllers/...`, `resources/js/admin/**`, `tests/Feature/**`).

### Backend (API)

| Area | Status | Notes |
|------|--------|-------|
| Riddle data model (`riddles`, `riddle_categories`, `riddle_attempts`) | ✅ done | Migrations + models + factories |
| Answer normalization (`RiddleHelper::normalize`) | ✅ done | lowercase, trim, strip diacritics, collapse spaces |
| Game API (`GameController`: index / show / daily) | ✅ done | answers never leaked to players |
| Answer API (`AnswerController::store`) | ✅ done | normalized compare, one-time reputation reward |
| Curator CRUD API (`Api\Riddle\RiddleController`, `CategoryController`) | ✅ done | reputation-gated via `IsCurator` |
| Admin web API (`Admin\RiddleController`, `RiddleCategoryController`) | ✅ done | paginated + searchable, suspend/unsuspend |
| Admin auth / session login | ✅ done | `Admin\SessionController` store/destroy/show |
| Auth flow (register / login / logout / user / verify) | 🔶 partial | works; needs hardening & points payload (Part B) |
| Leaderboard API | 🔶 partial | **buggy** response shape; **no "my rank"** (Part B) |

### Frontend (admin Vue panel)

| Area | Status | Notes |
|------|--------|-------|
| Admin shell (layout, sidebar, router guard) | ✅ done | Tailwind, Vue 3, Pinia |
| Riddle list + create/edit/suspend/unsuspend/delete | ✅ done | DataTable (search/sort/pagination) + forms |
| Category list + CRUD | ✅ done | |
| Dashboard stats | ✅ done | |
| Tailwind content scan for Vue files | ✅ done | fixed the "invisible UI" bug |

### Known issues to fold in

- `routes/api.php`: `AuthController::resendVerificationEmail` is routed but the method is actually `resendVerificationCode` — **fix in Part B**.
- `LeaderboardController` returns `[$users]` (a raw array) and has no authenticated-user rank — **fix in Part B**.
- No duplicate-riddle detection, no difficulty, no source/attribution, no streaks, no achievements — **Part C**.

---

# PART A — Back Office Operations on Riddles

## Goals

Turn the existing admin riddle screen into a complete **riddle operations console**: rich create/edit,
moderation, quality tooling, bulk actions, and activity visibility.

## A-Phases

### Phase A1 — Core riddle management hardening (audit current, close gaps)

`A1` completes what CRUD already does but does not yet cover:

- **Answer preview & verify** — on create/edit, show the normalized answer and a live "expected match" preview so curators see exactly how answers will be compared.
- **Duplicate detection** — on create/edit, warn if an active riddle with the *same normalized answer + category (or same question)* already exists; block or confirm.
- **Validation & UX polish** — persistent errors from the server mapped to fields; disable submit while saving; confirm-close on dirty forms; empty/loading states; created-by + updated audit display.
- **Per-riddle activity column** — show attempt count, correct rate, and a mini status in the list (API already returns `attempts_count`; add `solved_count` and `success_rate`).

**Deliverables:** `Admin\StoreRiddleRequest` / `UpdateRiddleRequest` duplicate-check; `Admin\RiddleController` index to include `attempts_count`, `solved_count`, `success_rate`; `RiddleForm.vue` verify/preview + duplicate warning; frontend wiring.

**Tests:** duplicate create rejected/warned; index exposes solved_count & success_rate; normalized preview matches stored value.

---

### Phase A2 — Difficulty, hints & richer riddle fields

Add fields that the improvements phase (Part C) and the mobile game will rely on:

- Add `difficulty` enum (`easy | medium | hard`) + optional `source` (attribution: *imigani*, book, user, "Riddles.com – adapted") to `riddles`.
- Add `hint_cost` behavior-ready field? — No: keep hints free for now but store **progressive hints** (`hint`, `hint2`) so the game can reveal gradually.
- Migration + model casts; validation rules (`in:easy,medium,hard`; source max length).
- Admin UI: difficulty picker (badge color), source field, second hint field in `RiddleForm.vue`; list shows difficulty badge + source.
- Seeder/import tooling so curators can bulk-add real Kirundi riddles (a `RiddleSeeder` with a small starter set + a CSV import artisan command later).

**Deliverables:** migration `add_riddle_gameplay_fields`; `Riddle` casts + fillable; form requests; admin list/form updates; `RiddleSeeder`.

**Tests:** difficulty/source persisted & validated; game API still never leaks answer while surfacing difficulty + hints.

---

### Phase A3 — Bulk operations & moderation tooling

- Bulk suspend / unsuspend / delete / change category on selected rows in the riddles table.
- Filter list by status (suspended / active), category, and difficulty.
- Soft-delete consideration: **keep hard delete but add an audit column** (`deleted_by`) — or switch to `SoftDeletes` so deletions are recoverable. Decision: use `SoftDeletes` on `riddles` (safer for a curated library); expose a "recently deleted / restore" view for admins only.
- Moderation queue: quick list of flagged/suspended riddles with "why" notes.

**Deliverables:** route group for bulk actions (`Admin\RiddleBulkController` or extend existing); DataTable row-selection + bulk toolbar; soft-delete migration + restore endpoints; filter controls.

**Tests:** bulk suspend/unsuspend/delete affect only selected; soft-deleted rows hidden from game API; restore works; filters return correct sets.

---

### Phase A4 — Riddle activity & analytics in the panel

- Per-riddle drill-down view: attempts over time, success rate by day, distribution of submitted (wrong) answers.
- Global operations dashboard: total solves, active players, today's solves, top riddles, difficulty breakdown.
- Export riddles to CSV/JSON (curator/admin).

**Deliverables:** `Admin\RiddleController::stats`, `Admin\DashboardController` extended; `resources/js/admin/views/riddles/Show.vue` (drill-down); CSV export endpoint; frontend charts (lightweight, no heavy chart lib — small SVG/bars).

**Tests:** stats payload correctness; export file generates; auth gating on all new endpoints.

---

# PART B — Mobile App Backend (Android API)

## Goals

Provide a clean, secure, and complete JSON API for the Android client: authentication, playing,
viewing points, and a leaderboard that tells the user **where they rank**.

## B-Phases

### Phase B1 — Authentication hardening & profile/points endpoint

Fix and harden the existing `AuthController`:

- **Fix route/method mismatch:** change `routes/api.php` to call `resendVerificationCode` (the real method), and add `throttle` to login/register/resend.
- **Token lifecycle:** return token + token type (`Bearer`) + expiry metadata; add token revocation on password change; single active-token-per-device option.
- **Consistent envelope:** standardize all responses (`{ success, data, message }`) — the game endpoints already use it; align `AuthController`.
- **Profile/points endpoint:** `GET /api/me` returns profile **plus** `reputation` (points), `level`, `stats` (words/meanings contributed, riddles solved, correct attempts), and `streak` (once Part C lands). Reuse existing `User` fields + `riddleAttempts()`.

**Deliverables:** `AuthController` cleanup; `routes/api.php` fixes; `Api\User\MeController` or extend `AuthController::user`; `ApiResource`/payload builder for the user; tests.

**Tests:** login returns token+user; register → verify → login flow; `/me` returns points/stats without leaking secrets; resend uses correct route.

---

### Phase B2 — Playing API completion (game loop)

Polish the game-facing API so the mobile client has a full play loop:

- **Endpoint for "next unsolved riddle"** (respecting difficulty filter) — `GET /api/riddles/next?difficulty=...`.
- **Return per-riddle solved status** so the client can show answered vs unanswered.
- **Reveal-answer endpoint** `POST /api/riddles/{riddle}/reveal` (no reputation change, for learning) and **hint endpoints** `GET /api/riddles/{riddle}/hint`.
- **Attempts history** `GET /api/riddles/history` (paginated) — what I solved, when, correct/wrong; and `GET /api/riddles/history/stats` (totals, accuracy, by category).
- Ensure answer submission is **idempotent** (already is via `updateOrCreate` + `rewarded` flag) and returns `{ correct, rewarded, points }` consistently.

**Deliverables:** `GameController::next`, `history`, `historyStats`; answer `reveal` + hint on existing `AnswerController`/`GameController`; payloads include solved status; routes; tests.

**Tests:** `next` returns an unsolved active riddle; reveal returns answer but does not reward; history paginates and stats are correct; double-solve no double-reward.

---

### Phase B3 — Points & progression API

- Expose **points ledger detail**: `GET /api/points` returns total and paginated `reputation_logs` (what earned/lost points, when, reason) — aligned with `reputation_logs` table.
- **Level model:** introduce a simple **level** derived from total reputation (e.g. thresholds) so the client can show "Level 3 — Umukunzi". Provide `GET /api/me/levels` with current level, progress to next, and the threshold table.
- Keep `reputation` on `User` as the single source of points; levels are computed (no schema change) in Part B3, with optional `reputation_levels` config for tuning.

**Deliverables:** `Api\PointsController`; level helper/service (`app/Support/Levels.php`); payload builders; routes; tests.

**Tests:** points ledger returns reasons in order; level boundaries correct; progress-to-next accurate.

---

### Phase B4 — Leaderboard + "Where am I" (my rank)

Fix the existing leaderboard and add rank:

- **Fix response shape:** return `{ success, data: [users...], me: {...} }` instead of a bare array.
- **Filters:** `today`, `this_week`, `this_month`, `this_year`, `all_time` (already parameterized but not surfaced in a clean payload); optional category/difficulty-limited leaderboard later.
- **Ranking basis:** total earned points (sum of `reputation_logs.changes`) within the period — keep it, but make the SQL robust and indexed.
- **"Where am I":** include `me` = authenticated user's `{ rank, points, total_players, percentile }` even if they're outside the top N. Compute rank via a subquery count of users with more points in the same period.
- **Pagination:** keep top list but return `meta` (current_page, last_page) and the user's page.

**Deliverables:** rewrite `Api\LeaderboardController::index`; add `rank` resolution; `me` payload; optional pagination; tests.

**Tests:** correct response envelope; rank values correct for a known dataset; filters (today/week/all-time) return right periods; unauthenticated → 401; zero-reputation users excluded; `me` accurate even when outside top 10.

---

### Phase B5 — Streaks & daily challenge (mobile-facing)

Lays the groundwork for retention (shared with Part C):

- **Daily challenge:** `GET /api/riddles/daily` already returns a deterministic daily riddle. Extend so it also returns whether today's was already solved and the user's **current streak**.
- **Streak tracking:** derive streak from `riddle_attempts` correct solves grouped by day (a "calendar" of consecutive days with ≥1 correct solve ending today or yesterday → streak; preserve on allowing streak freeze later). Store denormalized `current_streak`/`longest_streak` on `users` for performance, recomputed after each solve (or a scheduled job).
- **Streak payload** in `/me` and in the daily response.

**Deliverables:** streak computation service; migration adding streak columns to `users`; `GameController::daily` extended; `/me` includes streak; tests.

**Tests:** solving on consecutive days increments streak; missing a day resets; payload reflects streak.

---

# PART C — Riddle System Improvements

## Goals

Make the riddle system genuinely engaging and best-in-class, informed by **riddles.com**
(curated collections, "what am I" formats, daily + trending + difficulty + kid/adult audiences)
and by 2026 gamification best practices (streaks, levels, badges/achievements, progress bars,
personalization, fairness, community).

Not everything ships at once — each idea is sized as its own micro-phase so we can stop anytime.

## C-Phases (improvement backlog, dependency-ordered)

### Phase C1 — Taxonomy & discovery (foundation for everything else)

- **Collection/tagging system:** add `tags` + many-to-many `collection_tags` so riddles belong to themed collections (proverbs, animals, people, things, "Inkuru", math, funny, kids, adults) — mirrors riddles.com collections & taxonomy.
- **`what_am_i` formats:** add a `riddle_type` enum (`what_am_i | what_is_it | who_am_i | riddle | brain_teaser | math`) so the client can present the right UX and the admin can filter/curate by type.
- **Trending/popular:** compute `popularity_score` from solve counts + recency; expose `GET /api/riddles/trending` and "new" sorting.
- **Difficulty surfaced end-to-end** (from A2) for discovery filtering.

**Deliverables:** `tags`, `collection_tag`, `riddle_tag` tables + models; `riddle_type` column; popularity scoring service; trending endpoint; admin filter by type/tag and tag management; tests.

---

### Phase C2 — Daily riddle experience (retention)

- **Daily riddle with social proof:** daily response includes solved-by count and "best streak" hint messaging.
- **Archive:** `GET /api/riddles/daily/history?date=` lets players revisit past daily riddles (with solved status).
- **Streak freeze / streak saver** (configurable) so an active player can preserve a streak — ties to B5.
- **Notifications badge data:** return `{ daily_available, streak_at_risk, pending_challenges }`.

**Deliverables:** daily archive endpoint; streak-saver column/config; payload extensions; tests.

---

### Phase C3 — Achievements & levels (progression)

Abstract over Part-B points/levels, then add badges:

- **New `achievements` table + `user_achievements` (unlock tracking):** badge catalogue (first riddle, 10/50/100 solved, streak 3/7/30, correct without hint, category master, daily champion).
- **Unlock evaluator service:** run after each solve; issue unlock events; idempotent (earn each badge once).
- **`GET /api/me/achievements`:** earned + locked badges with progress (e.g. "7/10 solved").
- **Push-ready:** emit a field when new badges are earned in the answer response.

**Deliverables:** achievements migrations/models/seeder; `AchievementService`; endpoints; admin badge manager screen; tests.

---

### Phase C4 — Social, sharing & personalization

- **Share/send:** `POST /api/riddles/{riddle}/share` returns a shareable URL/text; optional "challenge a friend" with a short link + invitation record (mirrors Riddle.Me "send to friends").
- **Favorites/bookmarks:** `user_riddle_favorites` (many-to-many) + `GET/POST/DELETE /api/me/favorites`.
- **Saved progress for progressive hints:** track which hints a user has revealed per riddle (extend `riddle_attempts` or a `user_riddle_progress` table) so clients can resume.
- **Personalized next-riddle:** prefer categories the user has history with (or avoids) — simple heuristic in Part B2 `next`.

**Deliverables:** favorites migration; share/`invite` migration; progress table; endpoints; client payloads; tests.

---

### Phase C5 — Curation, quality & fairness

- **Submit-a-riddle (user-generated) flow:** `POST /api/submissions/riddles` → moderation queue → curator approves (creates a `Riddle`) or rejects. Gives the community a contribution path like riddles.com "submit a riddle".
- **Fairness/anti-abuse:** rate-limit answer submission; cap reputation farming (per-day solve reward cap via config); transparency of points in payloads.
- **Source integrity:** require `source` on UGC submissions; block obviously duplicated content via A1 duplicate check.

**Deliverables:** `riddle_submissions` table + endpoints; approval flow in admin; reward cap config + enforcement; tests.

---

### Phase C6 — Reporting & insights (admin + game)

- Admin: category/type/difficulty performance, daily-active players, conversion of daily challenge.
- Game: per-user progress summary (`GET /api/me/summary`) combining points, level, streak, badges, favorites, history counts — a single payload the Android home screen can render.

**Deliverables:** summary endpoint; admin analytics views; tests.

---

# Suggested Build Order (combined, one commit per phase)

Phases are deliberately coupled so each commit is meaningful and testable:

1. **A1** — Riddle management hardening (verify/preview, duplicates, activity columns)
2. **A2** — Difficulty, hints, source + seeder/import tooling
3. **B1** — Auth hardening + `/me` profile & points endpoint
4. **B2** — Playing API completion (next, reveal, hint, history, stats)
5. **B3** — Points ledger + levels
6. **B4** — Leaderboard + "Where am I" (rank) — *unblocks the most visible mobile feature*
7. **A3** — Bulk operations + soft-delete + moderation queue
8. **B5** — Streaks & daily challenge data
9. **C1** — Taxonomy & discovery (tags, types, trending, difficulty filter)
10. **C2** — Daily riddle experience (archive, streak saver)
11. **A4** — Riddle activity & analytics in the panel
12. **C3** — Achievements & levels (badges)
13. **C4** — Social, sharing & personalization (favorites, progress, invites)
14. **C5** — User-generated submissions + fairness/reward cap
15. **C6** — Reporting & insights / user summary

> **Ordering rationale:** B4 (leaderboard+rank) is pulled early because it is the headline mobile
> feature and cheap to do once B1/B3 give a clean points query. Part C builds on A2/B5 fields, so
> those come first.

---

# Conventions & Consistency (apply everywhere)

- **Envelopes:** all API responses use `{ success, data, message }`; game-facing payloads **never** expose `answer`.
- **Auth:** Android uses `auth:sanctum` + `verified`; admin uses session `auth` + `verified` + `admin` (reputation gate).
- **Points:** single source = `User::reputation` + `reputation_logs`; reward reasons are human-readable ("Solved a riddle").
- **Answer comparison:** always via `RiddleHelper::normalize()`.
- **Reputation-gated curator actions:** reuse `IsCurator` on the API side and the `admin` middleware on the web side.
- **Env-driven tuning:** new reward/bound values live in `.env` / `.env.example` (`RIDDLE_SOLVE_REPUTATION`, `MODERATION_REPUTATION_THRESHOLD`, streak-freeze, daily reward cap).
- **Tests:** feature tests under `tests/Feature/Api/` (mobile) and `tests/Feature/Admin/` (panel), matching existing mirrors; run with `php artisan test`.
- **Build:** run `npm run build` (tailwind + vite) before committing admin UI changes.

---

# Out of Scope (this plan)

- Building the Android client itself (UI) — this plan is **backend/admin only**.
- Audio/read-aloud riddles, video riddles.
- Paid monetization / coins / in-app purchases.
- AI generation of riddles (the 2026 trend exists, but curate human-authored content first).

---

# Reference & Inspiration

- riddles.com — taxonomy/collections, "what-am-I" formats, daily + trending + difficulty + kid/adult audiences.
- Riddle.Me / Riddle.Run — daily challenge, streaks, leaderboards, challenge-a-friend.
- Wordle-style daily games — one-per-day scarcity, streak preservation.
- Lasting Dynamics *Gamification in App Development 2026* — streaks, levels, badges, progress bars, fairness, personalization.
