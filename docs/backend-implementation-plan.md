# Backend & Back-Office Implementation — Rinjora Parity Experience

**Source of truth:** `docs/rinjora.html` (the prototype whose user experience we replicate).
**Branch:** `upgrade/laravel-13` (GPG-signed commits).
**DBs:** dev = MySQL `kazinduzi`; test = SQLite `:memory:` (full suite green after every step).
**Scope of this document:** adapt the **current, already-implemented** backend + back office so the combined API surface and admin tooling reproduce the prototype's round-of-10, tiered-level, per-mode-score experience. This is not a from-scratch plan: §2 inventories what exists today (verified against the code), §3 lists the exact gaps to close.

---

## 0. Prototype UX Recap (what we are replicating)

From `docs/rinjora.html` (arrays: `SOKWE` 216 q/a, `HERAHEZA` 162 q/a, `TUJAJURE` 16 t/p; constants `ROUND_SIZE = 10`, `NOMBRES` 1–20 in Kirundi; `difficulte()` = `a.length*2 + q.length`; `poolNiveau()` tiered levels; `noterPartie()` history; `T` = 100% Kirundi UI strings).

Screens & flows to mirror:
1. **Home** — brand, slogan, 3 game cards (Sokwe/Heraheza/Tujajure) + footer nav (History, Contribute, About).
2. **Quiz (Sokwe/Heraheza)** — top progress bar (index/round), `⭐ score` pill, `🔥 streak` pill (in-round), level badge, the puzzle text, free-text input, buttons *Raba ko wabitoye / Ndaguhaye ! / Rengana / ‹ Subira inyuma / Bandanya / Subira ku ntango*. Feedback card (ok/no), reveal of the answer after solve/concede, **re-answer allowed on wrong** (item stays active until solved or conceded). Confetti on correct. **Back navigation re-renders previously answered items in their solved state.** **Level-up modal** when `score ≥ 8` and there is a harder tier (Yes → start next level, No → end).
3. **Tujajure** — setup text + think prompt, 4 shuffled punchline options, correct/wrong highlight, next. **No levels, no level-up** (flat experience).
4. **End** — `score / round_length`, score label, performance message (top/mid/low), *Replay / Share / Home*.
5. **History (Amateka)** — total, #games, best, and per-mode rows (played count + total points).
6. **About + Contribution (Intererano)** — type selector (Igisokozo/Umwibutsa/Akajajuro/Iyindi ngingo), body, answer, name; send/copy.

---

## 1. Current vs. Target (verified gap analysis)

| Prototype concept | Current backend (verified) | Remaining gap |
|---|---|---|
| Full dataset | `RiddleSeeder` 216, `ProverbSeeder` 162, `JokeSeeder` 16 (tier thresholds 52/66, 37/50) | ✅ Closed. |
| Round of 10 (`ROUND_SIZE=10`) | `rounds` + `round_items` tables, `RoundManager::buildPool` (size from config) | ✅ Closed. |
| Tiered levels (`poolNiveau`, `hasNext`) | `RinjoraTier::poolFor` mirrors `difficulte`/`pas`/`debut`; `hasNextTier` | ✅ Closed — **except** jokes (§G-2). |
| Level-up modal (score≥8 + harder tier) | `roundPayload.level_available` + `.next_level` + `.has_more_levels` | ✅ Closed for sokwe/hera — **jokes must never level up** (§G-2). |
| In-round `⭐ score` + `🔥 streak` | `rounds.score`, `current_streak`, `best_streak` updated per solve | ✅ Closed. |
| Feedback, concede, reveal, re-answer | `AnswerMatcher` (`isConcede`/`isCorrect`), item stays pending on wrong, answer revealed only on solve/concede/skip | ✅ Closed (answer never leaked in `itemPayload`). |
| Joke options (4 punchlines) | `RoundManager::optionsFor` (punchline + distractors, server-shuffled) | ✅ Closed. |
| **Back → previously answered item** | `GET /rounds/{round}` returns only the *first pending* item | ❌ **G-1** — no per-position answered-state endpoint. |
| History (total/games/best/per-mode) + reset | `RoundHistoryController@index/destroy` | ✅ Closed. |
| Contribution (type selector) | `POST /api/contributions` routes sokwe/hera/tuja/other to the right submission store | ✅ Closed. |
| Performance label (top/mid/low) | `RoundController::performance()` (`>=8` top, `>=5` mid, else low) | ✅ Closed. |
| 100% Kirundi UI (`T`) | Server stays language-neutral; strings live client-side | ✅ Closed by design. |
| **Back office: curate *all* game content** | Riddle CRUD/bulk/export only; proverbs & jokes are moderation-only | ❌ **G-3**. |
| **Back office: monitor all three modes** | Dashboard + analytics are riddle-only | ❌ **G-4**. |

**Design principle (unchanged):** rounds/live state stay **server-owned** so the Android client is stateless and resumable; reputation/leaderboard keep one source of truth.

---

## 2. Current implementation inventory (verified)

### 2.1 Game engine
- `app/Support/RoundManager.php` — `MODE_MAP` (sokwe→`Riddle`, hera→`Proverb`, tuja→`Joke`), `config()`, `source()` (unsolved-only), `buildPool()`, `hasNextTier()`, `start()`, `currentItem()`, `hasPendingItems()`, `finalize()`, `roundPayload()`, `itemPayload()` (never exposes answers), `revealedAnswer()`, `optionsFor()`.
- `app/Support/RinjoraTier.php` — `difficulte = mb_strlen(a)*2 + mb_strlen(q)`, `tier()`, `poolFor(source, level, roundSize)` mirroring prototype `poolNiveau`.
- `app/Support/AnswerMatcher.php` — Kirundi-aware lenient matching + `ndaguhaye` concede.
- `config/riddles.php` — `round_size: 10`, `round_level_min_score: 8`, `round_levels: 5`, `round_reveal_on_concede: true`, `solve_reputation: 5`, `daily_solve_reputation_cap: 50`, `streak_freezes: 3`.

### 2.2 Schema
- `rounds` (user_id, mode `sokwe|hera|tuja`, level, item_count, score, current_streak, best_streak, status `active|completed`, started_at, completed_at) — `app/Models/Round.php`.
- `round_items` (round_id, puzzle_type `riddle|proverb|joke`, puzzle_id, position, status `pending|solved|conceded`, is_correct, attempts, answered_at; unique per `(round_id, position)`) — `app/Models/RoundItem.php`.

### 2.3 API surface (auth:sanctum, verified email)
| Method | Route | Controller |
|---|---|---|
| POST | `/api/games/{mode}/rounds` | `Api/Game/RoundController@store` |
| GET | `/api/games/{mode}/rounds/{round}` | `Api/Game/RoundController@show` (resume → first pending item) |
| POST | `/api/games/{mode}/rounds/{round}/items/{position}/answer` | `Api/Game/RoundAnswerController@answer` (throttle:30,1) |
| POST | `/api/games/{mode}/rounds/{round}/items/{position}/skip` | `Api/Game/RoundAnswerController@skip` |
| POST | `/api/games/{mode}/rounds/{round}/complete` | `Api/Game/RoundController@complete` |
| GET | `/api/games/history` | `Api/Game/RoundHistoryController@index` |
| DELETE | `/api/games/history` | `Api/Game/RoundHistoryController@destroy` |
| POST | `/api/contributions` | `Api/ContributionController@store` |

`/api/me` (`MeController`) already exposes profile, reputation level, and streak.

### 2.4 Back office (Vue 3 + Pinia SPA at `resources/js/admin`, routes under `/admin/api` in `routes/web.php`)
- **Riddles:** full CRUD (`RiddleController`), bulk actions (`RiddleBulkController`), CSV export, per-riddle stats, suspend/restore. Views: `views/riddles/{Index,Show,RiddleForm}.vue`.
- **Categories / Tags / Achievements:** CRUD (`RiddleCategoryController`, `TagController`, `AchievementController`).
- **Submissions (moderation queue):** `SubmissionController` (riddles), `ProverbSubmissionController`, `JokeSubmissionController` — list/filter + approve/reject with duplicate safe-publish.
- **Dashboard:** `DashboardController@index` — riddle totals, attempts, solves, active players, top riddles, difficulty breakdown.
- **Analytics:** `AnalyticsController@performance/players/dailyConversion` — all riddle-attempt based.

---

## 3. Gaps to close (adaptation tasks)

### G-1 — Back navigation to answered items
**Prototype:** pressing `‹ Subira inyuma` renders item `position-1`. If already answered, it shows the solved state (correctness + revealed answer + feedback card, input disabled).
**Current:** no endpoint returns a *specific position*'s answered state.

**Implement:**
1. `round_items` already stores everything needed (`status`, `is_correct`, `attempts`, `answered_at`).
2. Add route `GET /api/games/{mode}/rounds/{round}/items/{position}` → `RoundController@item`:
   - Auth: round belongs to user, matches route mode.
   - `status = pending` ⇒ return `itemPayload` (as today, `answered: false`).
   - `status = solved|conceded` ⇒ return the item payload **plus** answer-disclosure fields:
   ```
   { success: true, data: {
       item: {
         type:'riddle'|'proverb'|'joke', id, position, question|setup,
         category:{id,name,slug}, difficulty,
         options?: (joke only) [4 strings],
         answered: true,
         answered_correct: bool,
         revealed_answer: string|null,   // riddle/proverb answer or joke punchline
       }
   }}
   ```
   Answers are exposed **only** for non-pending items (invariant preserved).
3. Add feature tests (§5).
4. Android maps this to the Back button (§4.2 step 5 of `android-implementation-plan.md`).

### G-2 — Tujajure must be flat (no tiers, no level-up)
**Prototype:** jokes are served by plain shuffle of unsolved jokes, take up to 10; the end screen never offers a level-up.
**Current:** `buildPool`/`hasNextTier` tier jokes too, and `roundPayload` can set `level_available` for `tuja`.

**Implement:**
1. In `RoundManager::buildPool`: for `mode === tuja`, ignore level/tiering — take up to `round_size` shuffled **unsolved** jokes.
2. In `RoundManager::roundPayload`: for `tuja`, force `has_more_levels=false`, `next_level=null`, `level_available=false`.
3. Add tests: a `tuja` round never exposes `level_available`/`next_level`; pool items are a random subset of unsolved jokes; with all 16 fresh, item_count is 10.

### G-3 — Back office: curate all three game tables
**Current:** riddles have full CRUD/bulk/export/stats; proverbs and jokes exist as seeded content with **moderation-only** admin surface.

**Implement** (mirror the riddle pair `RiddleController` + `RiddleBulkController`):
1. **`Admin/ProverbController`** — index (filter: search, category, difficulty, suspended; with `attempts`/`solve_count`), store, update, destroy(soft), restore, suspend/unsuspend, export CSV, per-proverb stats.
2. **`Admin/JokeController`** — index (search setup, source, suspended; with attempts/solves), store (setup, punchline, `distractors[]`), update, destroy/restore, suspend/unsuspend, export CSV, per-joke stats.
3. **Views:** `views/proverbs/{Index,Show,ProverbForm}.vue` and `views/jokes/{Index,Show,JokeForm}.vue`; add nav entries in `router.js` and the admin shell.
4. **Bulk actions:** either extend `RiddleBulkController` into a generic `GameContentBulkController` or add parallel endpoints; actions: suspend/unsuspend/delete/restore/change_category.
5. Routes in `routes/web.php` under the existing `/admin/api` auth/admin group (naming convention `submissions/proverbs` shows the precedent for `proverbs`/`jokes` collections).
6. Admin list payloads may reuse the `success_rate` formula already used by `RiddleController@index`.

### G-4 — Back office: multi-mode dashboard & analytics
**Current:** `DashboardController` and `AnalyticsController` count `RiddleAttempt` only.

**Implement:**
1. **Dashboard** — add, per mode (sokwe/hera/tuja): `total_items` (riddles/proverbs/jokes), solve attempt counts, today solves; plus **round stats**: `rounds_played`, `active_rounds`, `completed_rounds`, round score distribution per level, peak concurrent-ish metric from `rounds.started_at` (last 7/30 days).
2. **Analytics** — new or extended endpoints:
   - `analytics/performance` → per mode: solve-rate by category, by type, by difficulty.
   - `analytics/rounds` → rounds per day, completion rate, average score by level, level-up frequency.
   - `analytics/contributions` → pending/approved/rejected counts across all three submission queues (a moderation funnel).
3. Views: extend `views/analytics/Index.vue` tabs and `views/Dashboard.vue` widget grid.

---

## 4. API contract (authoritative)

All game routes under `auth:sanctum,verified`; envelope `{ success, data }`. Flat envelope for answer/skip (existing convention).

### 4.1 Response shapes (verified today)
`POST /api/games/{mode}/rounds` `{ level? }` →
```
{ success, data: {
    round: { id, mode, level, item_count, index, score, current_streak, best_streak,
             completed: false, has_more_levels: bool, next_level, level_available },
    item: { type:'riddle'|'proverb'|'joke', id, position, question|setup,
            category:{id,name,slug}, difficulty,
            options?: (joke only) [4 shuffled punchlines] }
}}
```
`POST .../items/{position}/answer` (sokwe/hera `{ answer? }`, tuja `{ option? }`) →
```
{ correct, conceded, answer?, message, rewarded, points, capped,
  round: { score, index, current_streak, best_streak, completed, level_available, next_level },
  new_achievements: [] }   // riddle only
```
Wrong answers keep the item `pending` (re-answer allowed); `ndaguhaye` or a wrong joke option is terminal for the item (`conceded`), reveals `answer`, resets in-round streak.

`POST .../complete` →
```
{ success, data: { round: { ...round payload }, performance: 'top'|'mid'|'low' } }
```
`GET /api/games/history` →
```
{ success, data: { total, games, best, rows: [{ mode, games, points }] } }
```
`DELETE /api/games/history` → hard-deletes `rounds`+`round_items` (lifetime reputation preserved).
`POST /api/contributions` `{ type:'sokwe'|'hera'|'tuja'|'other', body, answer?, who? }` → 201 `{ success, data: { type, id, status:'pending' } }`.

### 4.2 New endpoint (G-1)
`GET /api/games/{mode}/rounds/{round}/items/{position}` — per-position state; answer disclosed **only** when the item is no longer pending. See §3 G-1.

---

## 5. Testing plan

Keep the full suite green (`php artisan test`, SQLite `:memory:`, `RefreshDatabase`) plus:
- `tests/Feature/Api/RoundItemStateTest.php` (G-1): pending item returns no answer; solved item returns `answered:true`, `answered_correct`, `revealed_answer`; conceded item reveals answer; 403/404 for foreign/other-mode rounds; completed round positions still readable.
- `tests/Feature/Api/RoundJokeTest.php` (G-2): tuja pool is a shuffled unsolved subset capped at 10; `level_available`/`next_level` always null; `has_more_levels` false.
- `tests/Feature/Admin/ProverbAdminTest.php` (G-3): index filters, CRUD, suspend/restore, export CSV, stats.
- `tests/Feature/Admin/JokeAdminTest.php` (G-3): same + `distractors[]` persistence.
- `tests/Feature/Admin/DashboardRoundsTest.php` (G-4): per-mode dashboard numbers and `analytics/rounds` shape.
- Existing `RoundTest`/`RoundHistoryTest`/`ContributionTest` must remain green (no regressions to riddles).

---

## 6. Delivery order (each step keeps the suite green + `migrate:fresh --seed` clean)

1. **A1 — G-1:** `GET .../items/{position}` controller + routes + tests.
2. **A2 — G-2:** flat tuja pools + no level-up for jokes + tests.
3. **A3 — G-3:** `ProverbController`/`JokeController` + bulk + views + routes + tests.
4. **A4 — G-4:** dashboard + analytics multi-mode extensions + views + tests.
5. **A5 —** full-suite + MySQL `migrate:fresh --seed` verification + GPG-signed commit.

---

## 7. Explicit non-goals (unchanged)

- Duels, favorites, daily-riddle gameplay parity (exist; untouched).
- Server-side localization (strings stay client-side).
- Performance/denormalization of round history (derived from `rounds`).
- Changes to existing single-item riddle/proverb/joke endpoints (they power web admin + legacy Android); round API is additive.