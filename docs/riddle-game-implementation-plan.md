# 🧩 Riddle Game — Backend Implementation Plan (Android API)

## Overview

This document describes the **backend-only implementation** of a **riddle game** for the Kazinduzi platform. The goal is to expose a fully functional REST API that the **Android app** can consume, without building any server-rendered (Blade) UI yet. The web UI can be added later without changing the API.

The riddle game fits the platform's **Phase 3** goal ("Word games and quizzes" and "Quizzes and gamified learning") and ties into the existing **reputation system** so that solving riddles rewards contributors in the same gamified fashion as words and meanings.

---

## Feature Summary

- Curated **riddles** (in Kirundi, with optional translated hint/clue) stored in the database.
- A **category** system so riddles can be grouped (proverbs, everyday objects, animals, etc.).
- Users answer a riddle and receive **immediate feedback** (correct/incorrect).
- Correct answers award **reputation points** (via the existing `updateReputation()`), with a per-riddle **one-time reward** to prevent farming.
- **Attempts** are tracked (which user tried which riddle, when, and whether correct) for analytics and to prevent re-awarding.
- Admin/curator endpoints to create, update, suspend, and delete riddles (protected by reputation threshold, mirroring the moderation model).

---

## Scope (This Implementation)

- ✔ Database migrations + Eloquent models
- ✔ API controllers under `App\Http\Controllers\Api\Riddle`
- ✔ API routes in `routes/api.php`
- ✔ Optional Form Requests for validation
- ✔ Reputation reward integration
- ✘ No Blade/UI views (deferred to a later phase)

---

## Data Model

Three new tables: `riddles`, `riddle_categories`, and `riddle_attempts`.

### `riddle_categories` table

| column | type | notes |
|--------|------|-------|
| `id` | bigint, PK | |
| `name` | string(100) | human-readable label |
| `slug` | string(100), unique | stable identifier for routing/API |
| `description` | text, nullable | optional |
| `timestamps` | | |

### `riddles` table

| column | type | notes |
|--------|------|-------|
| `id` | bigint, PK | |
| `category_id` | bigint, FK → `riddle_categories` | nullable, `SET NULL` on delete |
| `question` | text | the riddle itself (Kirundi) |
| `answer` | string(255) | stored plaintext (normalized for comparison) |
| `hint` | text, nullable | optional clue / translation |
| `is_suspended` | boolean, default `false` | moderation flag, mirrors words/meanings |
| `created_by` | bigint, FK → `users` | curator who added it |
| `timestamps` | | |

> **Answer normalization note:** store both the original answer and rely on the API layer to normalize both the stored and submitted answer (lowercase, trim, strip diacritics) before comparison. This improves correctness without storing a separate normalized column.

### `riddle_attempts` table

| column | type | notes |
|--------|------|-------|
| `id` | bigint, PK | |
| `user_id` | bigint, FK → `users`, cascade | |
| `riddle_id` | bigint, FK → `riddles`, cascade | |
| `submitted_answer` | string(255) | the answer the user gave |
| `is_correct` | boolean | result |
| `rewarded` | boolean, default `false` | whether reputation was granted for this attempt |
| `timestamps` | | |

Unique constraint: `unique(user_id, riddle_id)` for tracking **first/subsequent attempts** cleanly. Because the reward should only be granted once, the presence of any `rewarded = true` attempt for a `(user_id, riddle_id)` pair gates the reward.

---

## Models

### `App\Models\RiddleCategory`

```php
class RiddleCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function riddles()
    {
        return $this->hasMany(Riddle::class);
    }
}
```

### `App\Models\Riddle`

```php
class Riddle extends Model
{
    protected $fillable = [
        'category_id', 'question', 'answer', 'hint',
        'is_suspended', 'created_by',
    ];

    protected $casts = [
        'is_suspended' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(RiddleCategory::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attempts()
    {
        return $this->hasMany(RiddleAttempt::class);
    }
}
```

### `App\Models\RiddleAttempt`

```php
class RiddleAttempt extends Model
{
    protected $fillable = [
        'user_id', 'riddle_id', 'submitted_answer', 'is_correct', 'rewarded',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'rewarded'   => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function riddle()
    {
        return $this->belongsTo(Riddle::class);
    }
}
```

---

## Reputation Reward

Reuse the existing `User::updateReputation(int $points, string $reason, $related)` method (`app/Models/User.php:58`). Suggested reward constants:

- **Correct answer:** `+5` points
- Reason string: `"Solved a riddle"`

**One-time only:** before rewarding, check whether the user already has a `rewarded = true` attempt for this riddle. If none, add the points and mark the attempt `rewarded = true`. This prevents farming by repeatedly solving the same riddle.

Define the reward point value as an environment-driven constant for easy tuning:

```env
RIDDLE_SOLVE_REPUTATION=5
```

Read via `(int) env('RIDDLE_SOLVE_REPUTATION', 5)`.

---

## Answer Normalization Helper

Create a small helper (e.g. a static method on `Riddle` or a dedicated `app/Support/RiddleHelper.php`):

```php
public static function normalize(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = str_replace(
        ['à','â','ä','é','è','ê','ë','ì','î','ï','ò','ô','ö','ù','û','ü','ç'],
        ['a','a','a','e','e','e','e','i','i','i','o','o','o','u','u','u','c'],
        $value
    );
    $value = preg_replace('/\s+/', ' ', $value); // collapse whitespace
    return $value;
}
```

Both the stored answer and the user's submitted answer are passed through this before comparison, so accents and casing don't cause false negatives.

---

## API Controllers

All live under `app/Http/Controllers/Api/Riddle/` and extend `App\Http\Controllers\Controller`.

### `GameController` — public, game-facing

| method | purpose |
|--------|---------|
| `index(Request)` | List categories (optionally with ready rustle counts) |
| `show(Riddle $riddle)` | Fetch a single riddle **without revealing the answer** (unless requested with `?include_answer=1` for curators) |
| `daily(Request)` | Return a "riddle of the day" for the authenticated user (not yet solved, or a deterministic pick) |

### `AnswerController` — submit and evaluate an answer

| method | purpose |
|--------|---------|
| `store(Request, Riddle $riddle)` | Accept `{ answer }`, normalize & compare, record a `RiddleAttempt`, award reputation on first correct solve, return result JSON |

### `RiddleController` — admin/curator CRUD (protected)

| method | purpose |
|--------|---------|
| `index()` | List riddles (with answer, for curation) |
| `store(Request)` | Create a riddle |
| `update(Request, Riddle $riddle)` | Update a riddle |
| `destroy(Riddle $riddle)` | Delete a riddle |
| `suspend(Riddle $riddle)` | Set `is_suspended = true` |
| `unsuspend(Riddle $riddle)` | Set `is_suspended = false` |

### `CategoryController` — admin/curator CRUD (protected)

| method | purpose |
|--------|---------|
| `index()` | List categories |
| `store(Request)` | Create a category |
| `update(Request, RiddleCategory $category)` | Update a category |
| `destroy(RiddleCategory $category)` | Delete a category |

---

## Middleware / Authorization

Mirror the existing moderation pattern (`app/Http/Controllers/ModerationController.php`) that gates by reputation:

- `index` (game-facing) and `AnswerController::store`: require **authenticated** + **verified** user.
- Admin/curator CRUD (`RiddleController`, `CategoryController`, and `show` with `include_answer`): require the user's reputation to meet the existing `MODERATION_REPUTATION_THRESHOLD` (default 500).

Add a small reusable check, e.g. in `RiddlePolicy` or a shared `IsCurator` middleware:

```php
// Example guard used inside curator-admin endpoints
abort_unless(auth()->user()->reputation >= (int) env('MODERATION_REPUTATION_THRESHOLD', 500), 403);
```

---

## API Routes (`routes/api.php`)

Add under the existing API namespace:

```php
use App\Http\Controllers\Api\Riddle\GameController;
use App\Http\Controllers\Api\Riddle\AnswerController;
use App\Http\Controllers\Api\Riddle\RiddleController;
use App\Http\Controllers\Api\Riddle\CategoryController;

Route::prefix('riddles')->group(function () {

    // Public-ish game routes (authenticated + verified)
    Route::middleware('auth:sanctum', 'verified')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index']);      // list categories
        Route::get('/', [GameController::class, 'index']);                    // list riddles (no answers)
        Route::get('/daily', [GameController::class, 'daily']);               // riddle of the day
        Route::get('/{riddle}', [GameController::class, 'show']);             // single riddle (no answer)
        Route::post('/{riddle}/answer', [AnswerController::class, 'store']);  // submit an answer
    });

    // Curator/admin routes (reputation-gated)
    Route::middleware('auth:sanctum', 'verified')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::post('/', [RiddleController::class, 'store']);
        Route::put('/{riddle}', [RiddleController::class, 'update']);
        Route::delete('/{riddle}', [RiddleController::class, 'destroy']);
        Route::post('/{riddle}/suspend', [RiddleController::class, 'suspend']);
        Route::post('/{riddle}/unsuspend', [RiddleController::class, 'unsuspend']);
    });
});
```

> **Route ordering note:** register the literal `/{riddle}` `show` route **after** `/categories` and `/daily`, and route-model-bind `{riddle}` by ID or slug. If using implicit binding on the `Riddle` model, ensure `/categories` and `/daily` are declared first so they are not captured by `{riddle}`. Consider scoping `{riddle}` to a `where('riddle','[0-9]+')` constraint to match the existing ID-based API style.

---

## Request Validation (Form Requests)

Create Form Request classes under `app/Http/Requests/Riddle/` for cleaner validation, consistent with `app/Http/Requests/Auth/`:

- `AnswerRiddleRequest`: `'answer' => 'required|string|max:255'`
- `StoreRiddleRequest`: `'question' => 'required|string'`, `'answer' => 'required|string|max:255'`, `'category_id' => 'nullable|exists:riddle_categories,id'`, `'hint' => 'nullable|string'`
- `StoreCategoryRequest`: `'name' => 'required|string|max:100'`, `'slug' => 'nullable|string|max:100|unique:riddle_categories'`, `'description' => 'nullable|string'`

---

## Response Shapes

The API uses JSON. Riddle payloads for the game should **never** expose the answer to non-curators.

### Single riddle (game-facing, no answer)

```json
{
    "id": 1,
    "category": { "id": 3, "name": "Inkuru n'imigani", "slug": "stories-and-proverbs" },
    "question": "Kirazira no kugira amaso atatu, ko ...",
    "hint": "Optional hint",
    "created_at": "2026-08-27T12:00:00Z"
}
```

### Answer submission result

```json
{
    "correct": true,
    "message": "Correct! You earned 5 reputation points.",
    "rewarded": true
}
```

```json
{
    "correct": false,
    "message": "Not quite. Try again."
}
```

---

## Implementation Steps (ordered)

1. **Ensure `docs/` exists** → already created.
2. **Add environment config** `RIDDLE_SOLVE_REPUTATION` (with `.env.example` entry).
3. **Write migrations** for `riddle_categories`, `riddles`, `riddle_attempts`.
4. **Create models** `RiddleCategory`, `Riddle`, `RiddleAttempt`.
5. **Create helper** `RiddleHelper::normalize()`.
6. **Create Form Requests** for validation.
7. **Create API controllers** (Game, Answer, Riddle, Category).
8. **Register routes** in `routes/api.php`.
9. **Add reputation reward** logic in `AnswerController`.
10. **Add curator authorization** guard (reputation threshold).
11. **Write feature tests** for: listing riddles (no answer leak), answering correctly (reward + tracked attempt), answering incorrectly (no reward), duplicate solve (no double reward), and curator CRUD/suspend.

---

## Tests

Add feature tests under `tests/Feature/Api/`:

- `RiddleGameTest`
  - unauthenticated access is rejected
  - list riddles does not include the answer field
  - submitting a correct answer returns `correct: true` and awards reputation exactly once
  - submitting an incorrect answer returns `correct: false` and awards nothing
  - solving the same riddle twice does not double-reward
- `RiddleCuratorTest`
  - non-curator (low reputation) cannot create/suspend riddles
  - curator can create, update, suspend, and unsuspend riddles
  - categories CRUD works with authorization

---

## Out of Scope / Future Work (for a later phase)

- Server-rendered Blade UI / web pages for the riddle game
- Feed of "today's riddle" personalization & scheduling
- Stats dashboard (per-user streaks, category progress)
- Social/sharing features for riddles
- Audio/proverbs integration

---

## Notes & Consistency

- Reuse existing conventions: `$fillable`, JSON responses, `auth:sanctum` + `verified` middleware, reputation-based moderation gating.
- The `riddle_attempts.rewarded` flag keeps reward logic idempotent and auditable, consistent with the philosophy of `reputation_logs`.
- Because no Blade views are needed, this backend can be shipped, tested, and consumed by the Android client immediately — the later web UI can reuse the same controllers (or wrap them in web routes) without schema changes.
