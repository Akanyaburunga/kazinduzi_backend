# 🧩 Rinjora Parity — Lenient Answer Matching & New Content Modes (Proverbs + Jokes)

## Purpose

`docs/rinjora.html` is a client-side-only interactive prototype that showcases two capabilities our Laravel backend does **not** currently have:

1. **Lenient, Kirundi-aware answer matching** — free word order, `/`-separated alternatives, synonym/stem/suffix tolerance and typo tolerance. Our backend currently does strict equality after `RiddleHelper::normalize()`, which would reject many valid Kirundi answers.
2. **Two extra game modes beyond riddles**: **Heraheza** (proverbs — complete the ending) and **Tujajure** (jokes/facéties — pick the punchline from options). Our backend models riddles only.

This document is the **backend-only** implementation contract (Laravel 13 + Sanctum, branch `upgrade/laravel-13`). It assumes the consumer is the existing Android app / admin SPA; no frontend build is required beyond what is already served.

All endpoint shapes below follow the existing conventions:
- Envelope: `{ success: true, data: ... }` (and `{ success: false, message }` / `errors` on failure).
- **Never** leak an `answer` / `punchline` / `ending` before the player is allowed to see it.
- Auth: Bearer token + `verified`, except explicitly public routes.

---

## 1. Goal & Non-Goals

### Goals
- Add a reusable, permissive **answer-matching engine** used by riddles, proverbs and jokes.
- Add **proverb** and **joke** content types to the data model with a unified solve/attempt/reward path.
- Expose the new modes through the API so the mobile app can exercise them.
- Keep everything consistent with the existing reputation/streak/achievement systems where sensible.

### Non-Goals
- Porting the `rinjora.html` **UI/theme** (branding, mascots, palette). That is a client concern.
- The **localStorage** history model. We keep server-backed per-user progress.
- Rounds of exactly 10 questions or the "level-up modal" flow — those are client presentation details.
- Building the client. This is backend documentation.

---

## 2. Current State (Baseline)

- Answer matching = `App\Support\RiddleHelper::normalize()` (lowercase, strip a fixed set of accents, collapse whitespace) then **strict equality** in `AnswerController::store`.
- One content model: `Riddle` (`riddle_type` enum: `what_am_i|what_is_it|who_am_i|riddle|brain_teaser|math`, `category_id`, `difficulty`, `hint`, `hint2`, `answer`).
- Solve flow: `POST /riddles/{riddle}/answer` → awards reputation on first correct solve, honors the daily cap, evaluates achievements.
- Attempts recorded in `riddle_attempts` (unique `user_id`+`riddle_id`).

---

## 3. Part A — Lenient Answer-Matching Engine

Replaces strict equality with a pure, well-tested matcher that mirrors the intent of `rinjora.html`'s `estCorrect()` but keeps a configurable strictness so curators can opt out.

### 3.1 Design

Create `App\Support\AnswerMatcher` (pure static service, no DB, fully unit-testable). It borrows the prototype's heuristics but defines them explicitly:

Normalization (`normalize`):
- lowercase, trim;
- NFC→NFD then strip combining marks (`\p{M}`) — accents, cedillas, etc.;
- map typographic apostrophes/smart quotes (`' ' ’ ‘ ` ´`) to a space;
- collapse punctuation (`. , ! ? ; : ( ) " …`) to a space;
- collapse runs of whitespace to a single space, trim.

Tokenization helpers:
- `tokens()` — split on whitespace;
- `contentTokens()` — drop stop-words: `na`, `n`, `mu`, `ku`, `i`, `a`, `ya`, `wa`, `y`, `w` (configurable stop-word list), and drop tokens shorter than a configurable length (default 3) for the "partial match" path;
- `normalizeReorder()` — `contentTokens()` sorted alphabetically and joined — used for **free word order**.

Matching rules (evaluated for each alternative answer separately):
1. **Exact** after normalize.
2. **Free-order** — `normalizeReorder(guess) === normalizeReorder(answer)`.
3. **Typo tolerance** — Levenshtein distance ≤ tolerance where tolerance = `0` for length ≤ 4, `1` for ≤ 7, `2` otherwise (same as the prototype).
4. **Contains / radical** — for a guess token of length ≥ 4, accept if `answerToken.indexOf(guessToken) >= 0 || guessToken.indexOf(answerToken) >= 0` (shared root, e.g. `uruyuki`/`akayuki`).
5. **Common suffix** — accept if the two strings share a suffix of ≥ 4 chars (handles class-prefix variation, `umuyuki`/`akayuki`).
6. **Single-part match** — if any *content* guess token is close to any *content* answer token (rules 3–5), accept. This is the lenient "one correct word counts" behaviour. *Controlled by a flag* `allowPartial`.

Alternative answers: split the stored answer on `/`, trim, and evaluate all rules against **each** alternative — accept if any alternative matches (mirrors `Uruyuki / Inzuki`).

Public API:

```php
class AnswerMatcher
{
    public static function isCorrect(string $guess, string $answer, array $options = []): bool;
    public static function normalize(string $value): string;
    public static function isConcede(string $guess): bool; // "ndaguhaye"
}
```

Options (`$options`):
```php
[
  'allowPartial'   => true,   // accept a single matching content word
  'minPartialWord' => 3,      // min token length for partial/radical matches
  'stopWords'      => ['na','n','mu','ku','i','a','ya','wa','y','w'],
]
```

### 3.2 Integrating the matcher

- `AnswerController::store` (riddles): use `AnswerMatcher::isCorrect($guess, $riddle->answer)`.
- Same engine reused for proverbs and jokes (Parts B/C) — one code path.
- **"Concede" hint**: if `AnswerMatcher::isConcede($guess)` (i.e. the player typed `ndaguhaye`), treat it as a concede → record a failed attempt and reveal the answer (learning path), no reward. This mirrors the prototype's "give up" gesture without a separate endpoint.

### 3.3 Data model for alternatives/synonyms

`rinjora.html` stores alternatives inline (`/`-separated). Keep that simple convention:
- Store the canonical first answer in `riddles.answer`.
- Allow `/`-separated alternatives in the **same field** (e.g. `Uruyuki / Inzuki`), and the matcher splits on `/`.
- Add a **new column** `answer_aliases` (nullable `TEXT`) on `riddles` (and the new content tables) for many-variant answers; the matcher evaluates `answer` **plus** each alias line (aliases may themselves contain `/`).

Migration (Part A):
```php
Schema::table('riddles', function (Blueprint $table) {
    $table->text('answer_aliases')->nullable()->after('answer');
});
```

### 3.4 Tests (Part A)

`tests/Unit/Support/AnswerMatcherTest.php` — pure unit tests:
- accent/diacritic equivalence;
- free word order (`"abana banje"` vs `"banje abana"`);
- typo tolerance boundaries (≤4 / ≤7 / >7);
- radical/stem match (`uruyuki` ≈ `akayuki`);
- suffix match;
- partial single-word match + the `allowPartial:false` override;
- `/`-separated alternatives (`Uruyuki / Inzuki`);
- `answer_aliases`;
- `isConcede("Ndaguhaye")` true, `"umugabo"` false;
- case/whitespace/punctuation normalisation.

Update existing `AnswerController` tests if any assumed exact-equality rejection of valid variants.

---

## 4. Part B — New Content: Proverbs (Heraheza)

A proverb is a **two-part statement**; the player completes the ending given the beginning. Model as a distinct table.

### 4.1 Schema

`create_proverbs_table` (timestamped `2026_08_2x_xxxxxx`):
```php
Schema::create('proverbs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->nullable()->constrained('riddle_categories')->nullOnDelete();
    $table->string('question', 1000);      // "Abahigi benshi…"
    $table->string('answer', 500);         // "bayobeza imbwa"
    $table->text('answer_aliases')->nullable();
    $table->enum('difficulty', ['easy','medium','hard'])->default('medium');
    $table->string('source', 255)->nullable();
    $table->boolean('is_suspended')->default(false);
    $table->string('suspended_reason')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->softDeletes();
    $table->timestamps();
});
```

### 4.2 Model

`App\Models\Proverb` — mirrors `Riddle` (scopes `active`, soft-deletes, `creator()`, `category()`, `attempts()`).

### 4.3 Attempts & rewards

Reuse the reputation path. Two options — pick **Option B1** for lowest complexity:

- **Option B1 (recommended):** a single polymorphic attempt/reward approach isn't worth it here. Reuse `riddle_attempts` is not clean (FK to riddles). Instead create a **`proverb_attempts`** table (identical shape to `riddle_attempts` but FK to `proverbs`) and a parallel solve action awarding the same `config('riddles.solve_reputation')` and honouring the same daily cap. Achievements that count "solves" stay riddle-scoped unless a later phase generalises metrics.
- Option B2: unify all content into a single `content_items` table + polymorphic attempts. Larger refactor; only if we later want cross-mode unified metrics/feed. Out of scope by default.

`proverb_attempts`:
```php
Schema::create('proverb_attempts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('proverb_id')->constrained()->onDelete('cascade');
    $table->string('submitted_answer', 255);
    $table->boolean('is_correct')->default(false);
    $table->boolean('rewarded')->default(false);
    $table->timestamps();
    $table->unique(['user_id', 'proverb_id']);
});
```

Reuse the daily cap check (`config('riddles.daily_solve_reputation_cap')`) via the same logic used for riddles (extract a small helper `App\Support\Reputation::dailyRemaining(User $user)` reused by both controllers).

### 4.4 API

`routes/api.php` — new group under `auth:sanctum` + `verified`:
```
GET    /proverbs                     → list (no answers)      [query: category_id, difficulty, sort=new|trending]
GET    /proverbs/{proverb}           → single (no answer)
GET    /proverbs/next                → next unsolved (difficulty filter), 404 when done
POST   /proverbs/{proverb}/answer    → solve (throttle 30/min); uses AnswerMatcher
POST   /proverbs/{proverb}/reveal    → learning reveal, no reward
```

Payloads mirror riddles exactly except `question` = the proverb beginning and `answer` never returned until solved. Example list item:
```json
{
  "success": true,
  "data": [{
    "id": 3, "solved": false, "category": { "id": 2, "name": "Inkuru", "slug": "inkuru" },
    "question": "Abahigi benshi…", "difficulty": "medium",
    "source": "Imigani y'ikirundi", "created_at": "..."
  }]
}
```

Curator/admin routes (reputation-gated, admin panel):
```
POST   /proverbs
PUT    /proverbs/{proverb}
DELETE /proverbs/{proverb}
POST   /proverbs/{proverb}/suspend
POST   /proverbs/{proverb}/unsuspend
```
(These live under the existing `admin/api` session-guarded group for the panel, matching how `RiddleController` is wired.)

### 4.5 Seeder & tests

- `database/seeders/ProverbSeeder.php` — seed a starter set of `Proverb` rows (sourced from the `HERAHEZA` array in `docs/rinjora.html`), reusing `RiddleHelper`/`AnswerMatcher` normalization. Register in `DatabaseSeeder`.
- `tests/Feature/Api/ProverbTest.php` — list/single/next hide answers; solve correct (awarded, capped), wrong (no reward), concede/reveal (no reward), multi-alternative and lenient matching.

---

## 5. Part C — New Content: Jokes (Tujajure)

A joke is a setup + punchline; the player is shown the setup and **4 options** (the correct punchline + 3 distractors). The client picks an option; the backend validates it.

### 5.1 Schema

`create_jokes_table`:
```php
Schema::create('jokes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->nullable()->constrained('riddle_categories')->nullOnDelete();
    $table->string('setup', 1000);           // the joke text
    $table->string('punchline', 500);        // the correct chute (kadobo)
    $table->json('distractors')->nullable(); // 3+ plausible wrong punchlines
    $table->string('source', 255)->nullable();
    $table->boolean('is_suspended')->default(false);
    $table->string('suspended_reason')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->softDeletes();
    $table->timestamps();
});
```

`distractors` as JSON keeps the multiple-choice distractors data-driven (seedable/curatable) while the correct answer stays in `punchline`.

### 5.2 Model

`App\Models\Joke` — like `Riddle`/`Proverb`.

### 5.3 API

```
GET   /jokes/round                → one round: setup + 4 shuffled options (correct + 3 distractors), NO labels
POST  /jokes/{joke}/answer        → body { option: "the chosen punchline" }; server checks it equals punchline
GET   /jokes/next                 → next unsolved setup, 404 when done
POST  /jokes/{joke}/reveal        → learning reveal, no reward
```

- `GET /jokes/round` returns:
```json
{ "success": true, "data": { "joke_id": 9, "setup": "Agaca gacakiye agahori gati:", "options": ["...", "...", "...", "..."] } }
```
  Options must be **shuffled server-side** and the correct punchline included exactly once (client must not be able to infer it from ordering). Note: the punchline is exposed as an option — this is inherent to multiple-choice; the answer is "which option".
- `POST /jokes/{joke}/answer` with `{ "option": "..." }`: 200 correct (+reputation, first-time) / 422 wrong (expose the correct punchline in the error so the client can reveal feedback), or 200 with `correct:false` shape. Pick one contract and document it; recommended: return `{ success: false, message, correct: false, answer: "<punchline>" }` on a wrong guess (learning-friendly), `{ success: true, ... }` on correct.

### 5.4 Seeder & tests

- `database/seeders/JokeSeeder.php` — seed from `TUJAJURE` in `docs/rinjora.html`; auto-generate 3 distractors can be drawn from the other seeded punchlines (fallback), or curator-authored.
- `tests/Feature/Api/JokeTest.php` — round returns shuffled 4 options incl. correct once; answer correct/wrong; reveal; hide-nothing-after-solved consistency; distractors not leaking `punchline` as a separate field.

---

## 6. Content Submissions & Moderation (Proverbs/Jokes)

Extend the existing moderation queue. Current: `POST /submissions/riddles` → `riddle_submissions`. Add parallel:

- `POST /submissions/proverbs` → `proverb_submissions`
- `POST /submissions/jokes` → `joke_submissions`

Both mirror `riddle_submissions` (same columns semantically: `user_id`, `category_id`, body, answer/punchline, source, `status`, `rejection_reason`, `reviewed_by`, `reviewed_at`) so the admin panel's review flow is reusable. Admin approve → creates the `Proverb`/`Joke` (optionally verified by a curator), reject → records reason.

**(Part of this phase.)** Deliverables: two new tables + migrations, two submission controllers/requests shared with the existing `SubmissionController` pattern, admin approve/reject endpoints, and tests.

---

## 7. Phase Order & Deliverables

Dependency-ordered; commit at the end of each part on `upgrade/laravel-13` (GPG-signed, consistent with repo history):

### Phase A — AnswerMatcher engine
- `app/Support/AnswerMatcher.php`
- migration: `riddles.answer_aliases` (text, nullable)
- wire `AnswerController::store` to use the matcher + concede path
- `config/riddles.php`: `answer_match` options block (defaults above)
- tests: `AnswerMatcherTest`, update `RiddleGameTest`/answer tests as needed

### Phase B — Proverbs
- migration `create_proverbs_table` + `create_proverb_attempts_table`
- `App\Models\Proverb`
- `App\Http\Controllers\Api\ProverbController` (+ form requests)
- routes (player + admin)
- `ProverbSeeder`
- `tests/Feature/Api/ProverbTest`
- extract `App\Support\Reputation::dailyRemaining()` and reuse in both `AnswerController` and `ProverbController` solve paths

### Phase C — Jokes
- migration `create_jokes_table`
- `App\Models\Joke`
- `App\Http\Controllers\Api\JokeController` (+ requests) with round shuffling
- routes (player + admin)
- `JokeSeeder`
- `tests/Feature/Api/JokeTest`

### Phase D — Submissions for Proverbs/Jokes
- migrations `create_proverb_submissions_table`, `create_joke_submissions_table`
- extend `SubmissionController` (or new sibling controllers) + admin approve/reject
- routes + tests

### Phase E (optional, if a unified cross-mode feed/leaderboard is wanted later)
- Generalise achievement metrics (`solved`) to optionally count proverbs/jokes; otherwise leave riddle-scoped. Decide explicitly; default = leave as-is.

---

## 8. Risks & Notes

- **False positives from lenient matching** — partial/single-word matching can accept too much. Mitigate with `allowPartial` flag defaulting **on** for now (parity with the prototype) but expose it as a per-content override so curators can tighten. Add a config toggle `answer_match.allow_partial` (default true).
- **`/` in genuine answers** — if a literal slash can appear inside a real answer, escape it (`\/`) or store such cases only in `answer_aliases`. Document the convention in the seeder/admin form.
- **Exposing the punchline in multiple-choice** — inherent to the mode; ensure the correct option is present exactly once and ordering is server-shuffled. Never return a separate `punchline` field on `GET /jokes/round`.
- **Daily cap sharing** — riddles and proverbs share one reputation cap per day. State this explicitly so players can't farm across modes.
- **SQLite vs MySQL** — test on both; the matcher is pure PHP (no DB), the only MySQL-specific concern is reserved words (learned from the `change` incident) — avoid unquoted reserved column names in any new queries.

---

## 9. Acceptance Criteria (all parts)

- `php artisan test` green (full suite passes; new unit + feature tests added per phase).
- `php artisan migrate:fresh --seed` runs clean on MySQL dev DB; proverbs and jokes are present.
- A player can: list proverbs, solve with a lenient/alternative answer and earn capped reputation; fetch a jokes round with exactly 4 shuffled options and submit the correct one; concede/reveal without reward.
- Admin panel can create/suspend proverbs and jokes and approve their submissions.
- No answer/punchline/ending leaks through any list/payload to an unsolved item.
