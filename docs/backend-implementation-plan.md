# Backend Implementation Plan — Rinjora Parity Experience

**Source of truth:** `docs/rinjora.html` (the prototype whose user experience we replicate).
**Branch:** `upgrade/laravel-13` (all commits GPG-signed, sig `G`, Blaise Nduwimana).
**DBs:** dev = MySQL `kazinduzi`; test = SQLite `:memory:` (full suite green after every step).
**Commanding principle:** the app already contains ~90% of the mechanics. This plan packages them into the prototype's **round-of-10, tiered-level, per-mode-score** user experience and backfills the **full source dataset**. No existing endpoint is broken; new additions are additive.

---

## 0. Prototype UX Recap (what we are replicating)

From `docs/rinjora.html` (arrays: `SOKWE` 216 q/a, `HERAHEZA` 162 q/a, `TUJAJURE` 16 t/p; constants `ROUND_SIZE = 10`, `NOMBRES` 1–20 in Kirundi; `difficulte()` = `a.length*2 + q.length`; `poolNiveau()` tiered levels; `noterPartie()` history; `T` = 100% Kirundi UI strings).

Screens & flows to mirror:
1. **Home** — brand, slogan, 3 game cards (Sokwe/Heraheza/Tujajure) + footer nav (History, Contribute, About).
2. **Quiz (Sokwe/Heraheza)** — top progress bar (index/round), `⭐ score` pill, `🔥 streak` pill (in-round), level badge, the puzzle text, free-text input, buttons *Raba ko wabitoye / Ndaguhaye ! / Rengana / ‹ Subira inyuma / Bandanya / Subira ku ntango*. Feedback card (ok/no), reveal of the answer after solve/concede. Confetti on correct. **Level-up modal** when `score ≥ 8` and there is a harder tier (optionally continue harder; else end).
3. **Tujajure** — setup text, 4 shuffled punchline options, correct/wrong highlight, think prompt, next.
4. **End** — `score / round_length`, score label, performance message (top/mid/low), *Replay / Share / Home*.
5. **History (Amateka)** — total, #games, best, and per-mode rows (played count + total points).
6. **About + Contribution (Intererano)** — type selector (Igisokozo/Umwibutsa/Akajajuro/other), body, answer, name; send/copy.

The **Share** on end-screen shares the score text "Rinjora — … — x / 10 ⭐".

---

## 1. Current vs. Target (gap analysis)

| Prototype concept | Current backend | Gap to close |
|---|---|---|
| Game modes Sokwe / Heraheza / Tujajure | Riddles, Proverbs, Jokes (separate controllers) | Only naming/packaging; data split into 3 tables is fine. |
| 216 + 162 + 16 items | 24 riddles, 18 proverbs, 16 jokes seeded | Backfill full source dataset. |
| Round of 10 (`ROUND_SIZE=10`) | None — `next` returns one unsolved item | **New: server-side round/session of 10.** |
| Tiered levels (pool size /5, `hasNext`) | `Levels` thresholds are *reputation*-based | **New: game-tier levels** for the round pools. |
| In-round `⭐ score` + `🔥 streak` | Score tracked as lifetime reputation + daily-cap; streak = consecutive days | **New: in-round score + in-round streak** returned per solve. |
| Level-up modal when `score≥8` + has harder tier | n/a | Endpoint returns `level_available` + `next_level`; client shows modal. |
| Feedback, concede, reveal, answer | `AnswerController` returns `correct/rewarded/points/capped/conceded/answer/message/new_achievements` flat envelope | Reuse; add round fields + round-level completion handling. |
| History (total/games/best/per-mode) | Attempts + reputation logs exist; no per-mode "round" tally | **Round history table** + aggregate endpoint. |
| Contribution (type selector) | Riddle/Proverb/Joke submission endpoints exist | Expose a single `type`-aware submit + wire answers. |
| 100% Kirundi UI (T object) | Mixed English rep reasons, `message` strings | Keep server `message` generic; put ALL display strings in the Android client (which mirrors `T`). |

**Design decision:** rounds/live state stay **server-owned** (session records) so the Android client is stateless and resumable, and so the leaderboard/reputation model keeps one source of truth. The round is a lightweight record keyed to `(user, mode, level)`.

---

## 2. New schema

Follow the migration convention `2026_08_28_0000<NN>` (next available = `000018`, i.e. after `_17_create_joke_submissions_table.php`). Use the same fixed-date style.

### 2.1 `rounds` (a single play-through of 10 items in one mode)
```
id
user_id            FK users, cascade
mode               varchar(16)   // 'sokwe' | 'hera' | 'tuja'
level              unsignedTinyInt default 1
item_count         unsignedTinyInt default 10
score              unsignedTinyInt default 0   // # correct in this round
current_streak     unsignedTinyInt default 0   // in-round consecutive
best_streak        unsignedTinyInt default 0
status             varchar(16)   // 'active' | 'completed'
started_at         timestamp
completed_at       timestamp nullable
timestamps
index: (user_id, status), (user_id, mode, status)
```

### 2.2 `round_items` (one row per item in the round; preserves ordering)
```
id
round_id           FK rounds, cascade
puzzle_type        varchar(16)   // 'riddle' | 'proverb' | 'joke'
puzzle_id          unsignedBigInt  // polymorphic target (riddle/proverb/joke PK)
position           unsignedTinyInt // 0..9
status             varchar(16)   // 'pending' | 'solved' | 'conceded' | 'skipped'
is_correct         boolean default false
attempts           unsignedTinyInt default 0
ts_answered_at     timestamp nullable
unique per (round_id, position)
```

### 2.3 Per-mode round history tally (optional optimization; can be derived from `rounds`)
Keep it derived from `rounds` for v1 (total = sum of scores, games = count of completed, best = max score, per-mode rows). No extra table. If queries prove heavy, denormalize later.

**Config (`config/riddles.php`):** add
```php
'round_size'        => (int) env('ROUND_SIZE', 10),
'round_level_min_score' => (int) env('ROUND_LEVEL_MIN_SCORE', 8), // to offer next tier
'round_levels'      => (int) env('ROUND_LEVELS', 5),              // number of difficulty tiers
'round_reveal_on_concede' => (bool) true,
```

---

## 3. Data backfill (seeders)

Create a **night/unmerged** source module so the same data powers riddles, proverbs AND jokes with one import, then split between tables as the prototype does (`SOKWE`→riddles, `HERAHEZA`→proverbs, `TUJAJURE`→jokes).

### 3.1 `app/Support/RinjoraData.php` (pure static, no Laravel state)
- `sokwe(): array` — returns the 216 `{ q, a }`; `a` may contain `/`-separated alternatives.
- `heraheza(): array` — returns the 162 `{ q, a }` (q already ends with `…`).
- `tujajure(): array` — returns the 16 `{ t, p }`.
- Optionally a generated `database/seeders/data/rinjora_*.php` file with the raw arrays (so we don't hand-edit a 79 KB HTML). **Approach:** one-time extraction script writes the arrays into a dedicated PHP data file (committed), and the seeders read that file.

### 3.2 Seeders
- **`RiddleSeeder`** — extend from 24 to the full SOKWE set (216). Each mapped: `question=q`, `answer=a` (must store raw; the model boot `RiddleHelper::normalize()`s on create — verify normalize preserves `/` for alternatives; the prototype accepts alternates so keep the alternates in `answer` or `answer_aliases`), `riddle_type='what_am_i'` (default), `difficulty` derived via `difficulte()` thresholds for the tier system. Keep the existing 24 too (updateOrCreate, idempotent).
- **`ProverbSeeder`** — extend from 18 to HERAHEZA (162). `question=q`, `answer=a`, category `Imigani`, difficulty derived.
- **`JokeSeeder`** — extend from 16 to TUJAJURE (16 — same set, keep).
- Add `roundSeeding` of categories (`Imigani` etc.) — reuse `RiddleCategorySeeder`.

All seeders remain idempotent (`updateOrCreate` on `question`/`setup`). **`migrate:fresh --seed` on MySQL must stay green.**

### 3.3 Difficulty tiers (match prototype `poolNiveau`)
`difficulte(it) = mb_strlen(a)*2 + mb_strlen(q)`. Sort ascending, `n = count`, `pas = max(ROUND_SIZE, floor(n/5))`, level `l` starts at `debut = min((l-1)*pas, max(0, n-ROUND_SIZE))`. This is pure and can live in `RinjoraData`/a `RinjoraTier` support class used by the server to build rounds consistently with the client.

---

## 4. Round API (new controllers)

New prefix groups under `routes/api.php` (auth:sanctum + verified), following the envelope `{ success, data }`.

### 4.1 `POST /api/games/{mode}/rounds` — start a round
`{ mode: sokwe|hera|tuja, level?: 1..N }`. Server:
1. Closes any `active` round for `(user, mode)` (soft-finalize).
2. Builds the pool for the tier: fetches **unsolved** items (per prototype, answered items are filtered by solved state) ordered by `difficulte()` ascending, applies `poolNiveau` tiering, picks up to 10.
3. Creates `rounds` + `round_items`.
4. Responds with the **first item only** (answers/punchlines NEVER exposed):
```
{ success, data: {
    round: { id, mode, level, item_count, index:0, score:0,
             level_available:false, next_level:null, has_more_levels:bool },
    item: { type:'riddle'|'proverb'|'joke', id, position, question|setup,
            category:{id,name,slug}, difficulty, options?: (joke only) [4 strings] }
}}
```

### 4.2 `GET /api/games/{mode}/rounds/{round}` — current item (resume)
Returns the item at the round's current position (or `null` if the round is completed → client shows end screen).

### 4.3 `POST /api/games/{mode}/rounds/{round}/items/{position}/answer` — play an item
Body `{ answer?: string }` for sokwe/hera; `{ option?: string }` for tuja.
Logic (reuses existing matchers):
- **sokwe**: `AnswerMatcher::isConcede` → concede; else `AnswerMatcher::isCorrect(answer, riddle.answer[/aliases])`. On correct: score++, current_streak++. On wrong: attempts++ (try again allowed, as in prototype — do NOT move on until correct or concede). Respond like existing `AnswerController` plus round fields.
- **hera**: same via `AnswerMatcher` against `Proverb.answer`.
- **tuja**: check `option === joke.punchline`. On correct: score++, streak++. Wrong → highlight wrong in client (terminal per option; client disables). Respond.
- On **concede or skip**: reveal answer in response (`answer`/`punchline`), status=conceded, `current_streak=0`.

**Response** (flat, matching existing answer-controller convention, plus round state):
```
{ correct, conceded, answer?, message,
  round: { score, item_count, current_streak, best_streak, index,
           completed:bool,
           level_available: bool, next_level: int|null },
  new_achievements: [] }   // riddle only, from Achievements::evaluate
```

**Persisting reputation:** on correct solve still call `$user->updateReputation(...)` behind `Reputation::dailyRemaining()` cap (config `solve_reputation`) exactly as the current `AnswerController` does — so the leaderboard stays consistent. `Streaks::recompute()` and `Achievements::evaluate()` only make sense for riddles (preserve current behaviour; do not regress attempts).

### 4.4 `POST /api/games/{mode}/rounds/{round}/complete` — finalize (optional explicit)
Marks round `completed`, `completed_at`, computes `level_available = score >= ROUND_LEVEL_MIN_SCORE && has_more_levels`. Client sends this when the user hits the end-flow, or the server auto-finalizes when the last item is played. Return:
```
{ success, data: {
    round:{ id, mode, level, score, item_count, best_streak, completed, level_available, next_level },
    performance: 'top'|'mid'|'low' }   // top>=8, mid>=5, low<5 per prototype
}}
```

### 4.5 Resume/back-forward
- `GET .../rounds` → user's recent rounds (for resume + history).
- Back (`‹ Subira inyuma`) → `GET .../rounds/{round}/items/{position}` (already answered => hidden answer + feedback state).
- Skip (`Rengana`) → treat as concede (post an answer `{}` with `concede=1` or a dedicated `POST .../items/{position}/skip`).

---

## 5. History & stats API

### 5.1 `GET /api/games/history` (auth)
Aggregates user's `rounds` (completed) and returns exactly the prototype History screen:
```
{ success, data: {
  total: number,          // sum of all round scores
  games: number,          // count of completed rounds
  best:  number,          // max single-round score
  rows: [ { mode:'sokwe'|'hera'|'tuja', name:'Sokwe… Niruze !'|..., games:number, points:number } ]
}}
```
(Expose display `name` here OR keep names solely client-side via the `T` map — prefer client-side to keep backend language-neutral; return `mode` + counts.)

### 5.2 Optional `DELETE /api/games/history` — reset history (mirrors `h-reset` "Futa amateka yose 🗑️")
Hard-deletes the user's `rounds` + `round_items`. Reputation/attempts stay (they're lifetime stats; prototype's reset is localStorage-only).

---

## 6. Contribution API (reconcile with existing submissions)

The prototype offers one contribution form with a **type** selector (Igisokozo = riddle, Umwibutsa = proverb, Akajajuro = joke, other). Existing endpoints:
- `POST/GET /api/submissions/riddles`, `/proverbs`, `/jokes` (auth:sanctum+verified).
Add a convenience **`POST /api/contributions`** that accepts `{ type: 'sokwe'|'hera'|'tuja'|'other', body, answer?, who? }` and routes to the right `RiddleSubmission`/`ProverbSubmission`/`JokeSubmission` store with sensible field mapping (`body`→question/setup, `answer`→answer/answer-joke-body). `other` → logged as a generic moderation note (reuse `ModerationLog` or a `content_submissions` row). This keeps the prototype's single-screen form while reusing existing moderation/admin approval.

---

## 7. Share endpoints (already existing)

`POST /api/riddles/share` + `GET /api/riddles/share/{code}` exist (link sharing). The prototype **Share** on the end screen shares just a **text score summary** ("Rinjora — <slogan> — x / 10 ⭐") — this is client-side (Android uses the system share sheet); no new backend needed. Optionally add `GET /api/games/{mode}/rounds/{round}` share text if server-generated content is desired later.

---

## 8. Android-native coupling points (backend contract summary)

The Android client (see `docs/android-implementation-plan.md`) needs exactly these endpoints:
1. `POST /api/auth/register|login` (existing) → Sanctum token.
2. `GET /api/me` (`MeController`) → name, points.level (reputation level badge), streak.
3. `POST /api/games/{mode}/rounds` + `.../answer` + `.../complete` + `GET .../history`.
4. `POST /api/contributions`.
5. `GET /api/riddles/daily` + `POST /api/riddles/streak/freeze` (existing daily/streak features).

Client owns ALL Kirundi display strings (`T` object, `NOMBRES`, performance messages, good/streak messages) — mirror `T`/`NOMBRES` as a Kotlin object so the JS and Android stay in lockstep.

---

## 9. Testing plan

All new/edited behaviour must keep the full suite green (`php artisan test`, 241 tests today, SQLite `:memory:`, `RefreshDatabase`), plus additions:

- `tests/Feature/Api/RoundTest.php`:
  - start round returns exactly `item_count` (10) items, only first item exposed, no answer/punchline leaked.
  - answering correct increments score/streak; wrong allows re-answer and doesn't move position.
  - concede reveals answer, resets in-round streak, marks item conceded.
  - skipping behaves like concede.
  - completing returns `level_available`/`next_level` only when score≥8 and tier exists; else the end state.
  - resume returns the current unfinished item; completed round returns `null` item.
  - round items are drawn from the correct mode and unsolved-only.
- `tests/Feature/Api/RoundHistoryTest.php`: totals/games/best/rows aggregation + reset.
- `tests/Feature/Api/ContributionTest.php`: type-routing to each submission store.
- `tests/Unit/Support/RinjoraDataTest.php` (extends `PHPUnit\Framework\TestCase`): counts (216/162/16), no empty q/a, `/` alternatives present, `difficulte`/`poolNiveau` tier math matches prototype (pas computation).
- Extend `RiddleSeeder`/`ProverbSeeder`/`JokeSeeder` counts coverage: assert seeded DB has ≥ the source counts after `migrate:fresh --seed`.
- **`MigrateFreshSeedTest`** (or manual gate): `migrate:fresh --seed` on MySQL clean.

---

## 10. Migration / route summary

### New routes (`routes/api.php`)
```
POST   /api/games/{mode}/rounds
GET    /api/games/{mode}/rounds
GET    /api/games/{mode}/rounds/{round}
POST   /api/games/{mode}/rounds/{round}/items/{position}/answer
POST   /api/games/{mode}/rounds/{round}/items/{position}/skip
POST   /api/games/{mode}/rounds/{round}/complete
GET    /api/games/history
DELETE /api/games/history
POST   /api/contributions
```
All under `auth:sanctum,verified` (answer/skip under `throttle:30,1` like current answer routes).

### New files
```
database/migrations/2026_08_28_000018_create_rounds_table.php
database/migrations/2026_08_28_000019_create_round_items_table.php
app/Models/Round.php
app/Models/RoundItem.php
app/Support/RinjoraData.php
app/Support/RinjoraTier.php            // difficulte + poolNiveau
app/Http/Controllers/Api/Game/RoundController.php
app/Http/Controllers/Api/Game/RoundAnswerController.php
app/Http/Controllers/Api/Game/RoundHistoryController.php
app/Http/Controllers/Api/ContributionController.php
app/Http/Requests/Game/StartRoundRequest.php
app/Http/Requests/Game/AnswerRoundItemRequest.php
app/Http/Requests/ContributionStoreRequest.php
routes/api.php (add groups)
tests/Feature/Api/RoundTest.php
tests/Feature/Api/RoundHistoryTest.php
tests/Feature/Api/ContributionTest.php
tests/Unit/Support/RinjoraDataTest.php
```

---

## 11. Explicit non-goals (now; deferred to "other improvements later")

- Duels, favorites, daily-riddle gameplay parity (they exist; keep as-is, unmodified).
- Localization server-side (strings stay client-side).
- Performance/denormalization of round history.
- Any change to existing riddle/proverb/joke single-item endpoints (they power the web admin + current Android); the new round API is additive.

---

## 12. Delivery order (each step keeps the suite green + `migrate:fresh --seed` clean, GPG-signed commit)

1. **S1 — Round schema + models + config**: migrations `000018`/`000019`, `Round`/`RoundItem` models, `config/riddles.php` additions. Tests: schema factories.
2. **S2 — Tier + data support classes**: `RinjoraData`, `RinjoraTier` + extraction of the source arrays into a committed data file. Unit tests for counts + tier math.
3. **S3 — Seeder backfill**: extend Riddle/Proverb/Joke seeders to full source sets with derived difficulty. `migrate:fresh --seed` green; counts asserted.
4. **S4 — Round endpoints**: `RoundController`, `RoundAnswerController`, requests, routes. Feature tests (start/answer/concede/skip/complete/resume).
5. **S5 — History + contribution**: `RoundHistoryController`, `ContributionController`, routes. Feature tests.
6. **S6 — Full-suite + MySQL verification + commit.**
