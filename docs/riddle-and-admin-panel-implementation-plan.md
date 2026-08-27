# 🧩 Riddle Feature + Admin Panel — Unified Implementation Plan

## Purpose

This is the **single, authoritative** implementation plan for two intertwined deliverables on the Kazinduzi platform:

1. **The Riddle feature backend** — the database, models, and JSON API that serve the **Android** client (the game-facing side).
2. **The Vue.js admin panel** — a general-purpose management console that, for its first release, manages **riddles and riddle categories**, and will later grow to manage the whole platform.

These two plans are merged into one so that **no component is implemented before the feature it depends on**. The implementation order below is strictly dependency-driven: each step is the prerequisite of the next.

---

## Dependency Graph (read top → bottom)

```
[0] Tooling & env config          (no dependencies)
   └── [1] Riddle data model      (migrations + models + helper)
          ├── [2] Riddle game API (Android/mobile routes + controllers)
          │
          └── [3] Admin panel shell (Vue app, layout, router, auth, foundation)
                 └── [4] Admin riddles & categories management (depends on [1] + [3])
                        └── [5] Tests at every layer
```

- **Step 1** is the base everything else sits on.
- **Steps 2 and 3** are independent of each other — both depend only on **Step 1** — and can be developed in parallel by different people. The ordering relative to each other doesn't matter; **Step 1 must come first for both**.
- **Step 4** depends on **Steps 1 and 3**.
- **Step 5** runs throughout.

---

## Step 0 — Tooling & Environment Config

**Dependencies:** none.

- Fix `package.json` — it currently has a **duplicate `build` script key** (Vite build vs. Tailwind CLI). Consolidate into a single predictable pipeline that compiles both Tailwind and Vite assets.
- Add Vue tooling: `vue`, `@vitejs/plugin-vue`, `vue-router`, `pinia`.
- Add env config for the riddle reward:
  ```env
  RIDDLE_SOLVE_REPUTATION=5
  ```
  Add the same to `.env.example`.

**Deliverables:** working build toolchain, Vue packages installed, env key documented.

---

## Step 1 — Riddle Data Model (Foundation)

**Dependencies:** Step 0 (tooling only; models need no Vue, but the build must not be broken).

### Migrations

Three tables: `riddle_categories`, `riddles`, `riddle_attempts`.

#### `riddle_categories`
| column | type | notes |
|--------|------|-------|
| `id` | bigint PK | |
| `name` | string(100) | label |
| `slug` | string(100) unique | stable identifier |
| `description` | text nullable | |
| `timestamps` | | |

#### `riddles`
| column | type | notes |
|--------|------|-------|
| `id` | bigint PK | |
| `category_id` | FK → `riddle_categories` nullable, SET NULL | |
| `question` | text | the riddle (Kirundi) |
| `answer` | string(255) | normalized for comparison |
| `hint` | text nullable | optional clue |
| `is_suspended` | boolean default false | moderation flag |
| `created_by` | FK → `users` | curator |
| `timestamps` | | |

#### `riddle_attempts`
| column | type | notes |
|--------|------|-------|
| `id` | bigint PK | |
| `user_id` | FK → `users` cascade | |
| `riddle_id` | FK → `riddles` cascade | |
| `submitted_answer` | string(255) | user's guess |
| `is_correct` | boolean | |
| `rewarded` | boolean default false | gates one-time reputation reward |
| `timestamps` | | |

Unique constraint: `unique(user_id, riddle_id)`.

### Models

- `App\Models\RiddleCategory` — `hasMany(Riddle)`.
- `App\Models\Riddle` — `belongsTo(RiddleCategory)`, `belongsTo(User, 'created_by')`, `hasMany(RiddleAttempt)`; `is_suspended` cast to boolean.
- `App\Models\RiddleAttempt` — `belongsTo(User)`, `belongsTo(Riddle)`; `is_correct`/`rewarded` cast to boolean.

### Helper

`App\Support\RiddleHelper::normalize(string): string` — lowercase, trim, strip diacritics, collapse whitespace. Used for answer comparison.

**Deliverables:** tables + models + helper. **This is the foundation every other step requires.**

---

## Step 2 — Riddle Game API (Android / Mobile)

**Dependencies:** Step 1 (models + migrations).

Controllers under `App\Http\Controllers\Api\Riddle\`, extending the base controller, following the existing Sanctum API pattern.

| Controller | Methods | Purpose |
|------------|---------|---------|
| `GameController` | `index`, `show`, `daily` | List riddles (**answers never exposed**), single riddle, "riddle of the day" |
| `AnswerController` | `store` | Normalize & compare answer, record `RiddleAttempt`, award reputation once on first correct solve |
| `RiddleController` | `index`,`store`,`update`,`destroy`,`suspend`,`unsuspend` | Curator CRUD (reputation-gated) |
| `CategoryController` | `index`,`store`,`update`,`destroy` | Category CRUD (reputation-gated) |

- **Reputation reward:** reuse `User::updateReputation(+RIDDLE_SOLVE_REPUTATION, 'Solved a riddle', ...)`, granted only when the user has no prior `rewarded = true` attempt for that riddle (idempotent, no farming).
- **Authorization:** game routes = `auth:sanctum` + `verified`; curator routes additionally require reputation ≥ `MODERATION_REPUTATION_THRESHOLD`.
- **Form Requests** under `app/Http/Requests/Riddle/` for validation.

### Routes (`routes/api.php`)

```php
Route::prefix('riddles')->group(function () {
    Route::middleware('auth:sanctum', 'verified')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/', [GameController::class, 'index']);
        Route::get('/daily', [GameController::class, 'daily']);
        Route::get('/{riddle}', [GameController::class, 'show']);
        Route::post('/{riddle}/answer', [AnswerController::class, 'store']);
    });
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

> **Route ordering:** declare `/categories` and `/daily` before `/{riddle}` so the literal routes aren't captured by the wildcard. Scope `{riddle}` to `[0-9]+` to match the ID-based API style.

**Deliverables:** mobile/game API complete and consumable by Android independent of any admin UI.

---

## Step 3 — Admin Panel Shell (Vue.js)

**Dependencies:** Step 1 only (independent of Step 2). Can be developed in parallel with Step 2.

### Vite configuration

Add Vue plugin + a dedicated admin entry in `vite.config.js`:

```js
import vue from '@vitejs/plugin-vue';
// in plugins: vue(), and laravel input gains 'resources/js/admin/app.js'
```

### Blade host

- `resources/views/admin/layouts/app.blade.php` (minimal shell + Tailwind-only styles, isolated from the public Bootstrap site).
- `resources/views/admin/app.blade.php` → mounts `<div id="admin-app"></div>` + `@vite(['resources/js/admin/app.js'])`.

### Frontend skeleton (`resources/js/admin/`)

```
app.js               ← Vue root + Pinia + Router
router.js            ← nested routes under /admin
layouts/AdminLayout.vue  ← data-driven sidebar + topbar
stores/auth.js       ← session + admin flag
components/DataTable.vue, Modal.vue, ConfirmDialog.vue, Toast, StatCard.vue
stores/base.js       ← generic CRUD store factory
```

- **Auth strategy:** session-based (`auth` middleware + CSRF via Axios) — matches the browser-native public site; the Android app keeps its own Sanctum token on the game API.
- **Route guards:** Vue Router `beforeEach` checks session + admin; server `admin` middleware is the source of truth.
- **Reusable foundation:** `DataTable`, `Modal`, `ConfirmDialog`, `Toast`, and a **base CRUD store factory** — all designed so future platform modules (words, users, moderation, settings) plug in with minimal code.

**Deliverables:** the isolated Vue admin shell + all reusable building blocks, with no feature modules yet.

---

## Step 4 — Admin Riddles & Categories Management

**Dependencies:** Step 1 (models) + Step 3 (shell). Consumes the **same** Riddle models as Step 2.

### Backend admin API

`app/Http/Controllers/Admin/`:

| Controller | Methods | Purpose |
|------------|---------|---------|
| `DashboardController` | `index` | Stats: totals + quick links |
| `RiddleController` | `index`,`store`,`show`,`update`,`destroy`,`suspend`,`unsuspend` | Riddle management |
| `RiddleCategoryController` | `index`,`store`,`update`,`destroy` | Category management |

### Admin middleware

`App\Http\Middleware\EnsureIsAdmin` — gate on session auth + reputation ≥ `MODERATION_REPUTATION_THRESHOLD`. Register as the `admin` alias in `bootstrap/app.php` (Laravel 13 style, via `$middleware->alias([...])`).

### Routes (`routes/admin.php` or in `web.php`)

```php
Route::prefix('admin')->middleware('admin')->group(function () {
    Route::get('/{vueRoute?}', ...)->where('vueRoute', '.*'); // catch-all → host view
});
Route::prefix('admin/api')->middleware(['auth', 'verified', 'admin'])->group(function () {
    // Riddle + category CRUD + dashboard (list of endpoints as in Step 2, but in Admin namespace)
});
```

### Vue screens (in `resources/js/admin/views/`)

- `Dashboard.vue` — stat cards + quick links.
- `riddles/Index.vue` + `RiddleForm.vue` — data table (search/sort/pagination), create/edit/suspend/delete with confirm + toasts.
- `categories/Index.vue` + `CategoryForm.vue` — table with inline add/edit/delete.

**Deliverables:** a working admin panel managing riddles & categories end-to-end.

---

## Step 5 — Tests & Verification

Run as each layer lands.

- **Riddle game API** (`tests/Feature/Api/RiddleGameTest.php`):
  - unauthenticated rejected;
  - list does **not** leak the answer field;
  - correct answer → `correct:true`, +reputation, **exactly once** (no double reward on repeat solve);
  - incorrect answer → `correct:false`, no reward.
- **Riddle curator** (`tests/Feature/Api/RiddleCuratorTest.php`): low-reputation users blocked; curators can CRUD + suspend/unsuspend.
- **Admin middleware + CRUD** (`tests/Feature/Admin/AdminRiddleTest.php`): `admin` gate rejects non-admins; riddle & category CRUD and suspend/unsuspend work under admin.
- **Frontend:** confirm `npm run build` compiles both bundles; router guards behave (manually).

---

## Implementation Sequencing Summary

| Step | Deliverable | Depends on | Parallelizable |
|------|-------------|-----------|----------------|
| 0 | Tooling, Vue install, env config, build cleanup | — | — |
| 1 | Riddle migrations + models + helper | 0 | — (blocking) |
| 2 | Riddle game API (Android) | 1 | Yes, with 3 |
| 3 | Admin panel shell (Vue foundation) | 1 | Yes, with 2 |
| 4 | Admin riddles & categories CRUD | 1 + 3 | After 3 |
| 5 | Tests & verification | all above | Continuous |

**Rule enforced throughout:** a step is only started once all of its dependencies (listed in "Depends on") are complete and reviewed/committed. This guarantees the admin panel is never built before the backend feature (riddle model) it manages, and the mobile backend is never built before the data model.

---

## Notes & Consistency

- The riddle **models** are shared by **both** the game API (Step 2) and the admin panel (Step 4) — a single source of truth.
- Game-facing API never exposes answers; admin API is the only place answers are returned, and it's guarded by `admin` middleware.
- Reputation-based authorization is used initially; a real role/permission model is a planned later milestone (see original plan).
- Reuse established conventions: `$fillable`, Sanctum + `verified`, reputation-gated moderation.
