# 🛠 Admin Panel — Implementation Plan (Vue.js)

## Overview

This document describes the plan for a **modern admin/management panel** built with **Vue.js**, mounted inside the existing Laravel Blade application. For its initial release the panel will manage **riddles and riddle categories** (see `docs/riddle-game-implementation-plan.md`), but it is architected as a **general-purpose administration console** that will later grow to manage **every feature of the platform** (words, meanings, votes, users, moderation, leaderboards, settings, etc.).

The web UI intentionally complements the currently backend-only riddle API. Where the Android client consumes the **JSON API**, this admin panel will (for its first phase) operate against the same data through dedicated admin endpoints, giving the platform a single source of truth.

---

## Goals

- Provide an authenticated, role-/reputation-aware **management console** for curators and moderators.
- Ship **riddle & category CRUD** first (Phase A) with a reusable, scalable architecture.
- Build a foundation (auth store, layout, routing, reusable table/form components) that future feature modules plug into with minimal effort.
- Keep the existing public site untouched — the admin panel lives on a separate route prefix (`/admin`) with its own layout.

---

## Tech Choices & Rationale

| Area | Choice | Rationale |
|------|--------|-----------|
| Framework | **Vue 3** (Composition API, `<script setup>`) | Modern, reactive, best-in-class DX |
| Build tool | Existing **Vite** (already configured for Laravel) | No new tooling; add Vue plugin + component inputs |
| Language | **JavaScript** (matching current repo; no TS setup yet) | Keeps onboarding simple; can migrate to TS later |
| Routing | **Vue Router** | For nested admin sub-routes (`/admin/riddles`, `/admin/riddles/new`, ...) |
| State | **Pinia** | Official Vue store; auth + data state; scalable |
| HTTP | **Axios** (already a dependency) + token auth | Reuse existing Sanctum API; admin endpoints use a dedicated token |
| UI | **Tailwind CSS** (already present) + optional component lib | Fast, consistent styling; keep dependency footprint light |
| Backend bridge | Laravel `web` + `api` routes returning JSON | Panël calls JSON endpoints; server enforces authorization |

> **Note:** The repo currently uses **Bootstrap 5** in the public Blade views. The admin panel will use **Tailwind** in its own scoped layout to avoid style collisions and to enable a distinct, dense dashboard aesthetic. The two styling systems are isolated by route/layout.

---

## High-Level Architecture

```
Blade host (/admin/*)
   └─ <div id="admin-app">  ← mount point
         └─ Vue 3 application
              ├─ Vue Router (nested routes under /admin)
              ├─ Pinia stores (auth, riddles, categories, ui)
              └─ Feature modules
                   ├─ auth (login, guard)
                   └─ riddles / categories  ← Phase A
                        └─ ...future modules (words, users, moderation, settings)
```

- A thin **Blade placeholder view** mounts the Vue root and loads the compiled assets.
- The Vue app is **completely isolated** from the public site: its own layout, its own sidebar, its own styling.
- All data flows through **JSON endpoints** (see "Backend API" section). This keeps the panel decoupled from server-rendered HTML and makes it reusable for future modules.

---

## Backend API (Admin JSON Endpoints)

The admin panel needs **admin-scoped** counterparts to the game-facing API. To keep these separate and auditable, add a dedicated admin controller namespace:

```
app/Http/Controllers/Admin/
├── DashboardController.php
├── RiddleController.php
└── RiddleCategoryController.php   ← Phase A minimal set
```

Routes (in a new `routes/admin.php` required from the bootstrapping, or inside `web.php` guarded by a prefix):

```php
Route::prefix('admin/api')->middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/dashboard', [Admin\DashboardController::class, 'index']);

    // Riddles
    Route::get('/riddles', [Admin\RiddleController::class, 'index']);
    Route::post('/riddles', [Admin\RiddleController::class, 'store']);
    Route::get('/riddles/{riddle}', [Admin\RiddleController::class, 'show']);
    Route::put('/riddles/{riddle}', [Admin\RiddleController::class, 'update']);
    Route::delete('/riddles/{riddle}', [Admin\RiddleController::class, 'destroy']);
    Route::post('/riddles/{riddle}/suspend', [Admin\RiddleController::class, 'suspend']);
    Route::post('/riddles/{riddle}/unsuspend', [Admin\RiddleController::class, 'unsuspend']);

    // Categories
    Route::get('/riddle-categories', [Admin\RiddleCategoryController::class, 'index']);
    Route::post('/riddle-categories', [Admin\RiddleCategoryController::class, 'store']);
    Route::put('/riddle-categories/{category}', [Admin\RiddleCategoryController::class, 'update']);
    Route::delete('/riddle-categories/{category}', [Admin\RiddleCategoryController::class, 'destroy']);
});
```

### Admin authorization (`admin` middleware)

Gate access using the same reputation threshold as moderation:

```php
// app/Http/Middleware/EnsureIsAdmin.php
public function handle($request, Closure $next)
{
    abort_unless(auth()->check() && auth()->user()->reputation >= (int) env('MODERATION_REPUTATION_THRESHOLD', 500), 403);
    return $next($request);
}
```

Register it in `app/Http/Kernel.php` under the `$middlewareAliases` (or `$routeMiddleware`) map as `'admin'`. Future platform-wide management will likely move toward explicit `is_admin`/role flags — see "Future Evolution".

---

## Frontend File/Layout Plan

### New entry point (isolated from the public build)

To avoid mixing the admin bundle into every public page, give the admin panel its **own Vite entry**:

```
resources/js/admin/
├── app.js               ← Vue root bootstrap
├── router.js            ← Vue Router (nested under /admin)
├── stores/
│   ├── auth.js          ← login state, token, admin flag
│   ├── riddles.js
│   └── categories.js
├── layouts/
│   └── AdminLayout.vue  ← sidebar + topbar shell
├── views/
│   ├── Dashboard.vue
│   ├── riddles/
│   │   ├── Index.vue
│   │   ├── Edit.vue
│   │   └── RiddleForm.vue (reusable)
│   └── categories/
│       ├── Index.vue
│       └── CategoryForm.vue
└── components/
    ├── DataTable.vue    ← reusable table (search, sort, pagination)
    ├── Modal.vue
    ├── ConfirmDialog.vue
    └── StatCard.vue
```

### Vite configuration

Add the admin entry to `vite.config.js`:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin/app.js',   // ← admin bundle
            ],
            refresh: true,
        }),
        vue(),                                 // ← Vue SFC support
    ],
});
```

### Blade host view

Single placeholder that mounts the Vue app:

```
resources/views/admin/app.blade.php
```

```blade
@extends('admin.layouts.guest')
@section('content')
    <div id="admin-app"></div>
    @vite(['resources/js/admin/app.js'])
@endsection
```

The `admin.layouts.guest` layout renders only the minimal HTML shell + Tailwind CSS (compiled separately) so the admin panel styles do not leak into the public Bootstrap site.

---

## Build/Dependency Considerations

- Add Vue + Pinia + Vue Router + the Vite Vue plugin:
  ```bash
  npm install vue @vitejs/plugin-vue vue-router pinia
  ```
- Keep Axios (already present) and configure an instance pointing at `/admin/api` with a **CSRF + session** or a dedicated token, depending on the auth strategy chosen (see auth below).
- **Important repo quirk:** `package.json` currently has a **duplicate `build` script key** (Vite build vs. Tailwind CLI). Fix this before/while adding the admin build so `npm run build` behaves predictably; consolidate to a single pipeline that compiles both Tailwind and Vite assets.

---

## Authentication Strategy

The public site already uses **Laravel session auth** (`auth:web`) for users. Two viable strategies for the admin panel:

1. **Session-based (recommended for web)**: The admin routes use `auth` + `admin` middleware, and the Vue app talks to `/admin/api` with the existing session cookie + CSRF token via Axios. Simplest, secure, and consistent with the rest of the site — no token handling in the browser.
2. **Sanctum token (hybrid)**: Useful if the admin API should also serve non-browser clients later, but adds token management overhead for a pure-web console.

**Recommendation:** start with **session-based** (`auth` + CSRF) for the admin panel. The Android app keeps using its own Sanctum token against the game-facing API; the admin realm stays browser-native.

> If later the panel should support API-token login, reuse `User::createToken()` as in `Api\AuthController::login` (`post /api/auth/login`).

---

## Phase A Scope — Riddles & Categories

### Riddle list (`/admin/riddles`)
- Data table: id, question, category, status (active/suspended), curator, created date
- Search by question text; pagination; sortable columns
- Row actions: edit, suspend/unsuspend, delete (with confirmation modal)

### Riddle create/edit form
- Fields: question (textarea), answer, hint, category (select), suspend toggle
- Client-side validation + server-side error display
- Save → navigate back to list with success toast

### Category management (`/admin/riddle-categories`)
- Simple table with inline add/edit/delete
- Slug auto-generated from name (editable)

### Dashboard (`/admin`)
- Stat cards: total riddles, active riddles, categories, total attempts
- Quick links to manage riddles/categories

---

## Reusable Foundation (built in Phase A, reused later)

The following components and patterns are designed to be reused by every future feature module (words, meanings, users, moderation, etc.):

- **`AdminLayout.vue`** — responsive sidebar with collapsible nav groups, topbar with user menu. Nav groups are data-driven, so adding a future module only adds a nav entry.
- **`DataTable.vue`** — generic table driven by a column config (key, label, sortable, slot for custom cell rendering), with server-side search/pagination, row selection, and action slot. This is the workhorse for all future list screens.
- **`Modal` / `ConfirmDialog`** — reusable overlays for destructive confirms and lightweight forms.
- **`Toast` / notification composable** — global success/error feedback.
- **Pinia store factory** — a base CRUD store (list/fetch/create/update/delete + pagination + error state) that feature stores extend, minimizing boilerplate for future modules.

---

## Route Guarding (Frontend)

- Vue Router `beforeEach` guard checks for an authenticated session and admin authorization before entering `/admin` routes.
- On 401/403 responses, redirect to login or show a "not authorized" state.
- Server-side `admin` middleware remains the source of truth; frontend guards are UX-only.

---

## Future Evolution (Beyond Phase A)

The panel is deliberately a **general platform management console**. Planned/prospective modules (matching the platform roadmap):

- **Words & Meanings** — review, edit, suspend/unsuspend, delete; approval workflows.
- **Users & Moderation** — ban/unban, view reputation logs, verify users, role assignment.
- **Votes & Quality** — insights into voting, flagging/reporting UI.
- **Leaderboards & Settings** — configure thresholds, site-wide settings, env-backed toggles.
- **Notifications** — compose/manage in-app notifications.
- As scope grows, introduce a real **role/permission model** (e.g. `users.is_admin`, an `admin_roles`/permissions table, or a package like Spatie Laravel Permission) rather than relying solely on a reputation threshold. This is a deliberate later milestone so Phase A can ship quickly on the existing reputation gate.

---

## Implementation Steps (ordered)

1. **Fix `package.json` duplicate `build` script** and consolidate the build pipeline (Vite + Tailwind).
2. **Install** Vue 3, Vue Router, Pinia, `@vitejs/plugin-vue`.
3. **Update `vite.config.js`** to add the Vue plugin and the `resources/js/admin/app.js` entry.
4. **Create admin Blade layout + host view** (`resources/views/admin/...`, `@vite` the admin bundle).
5. **Create the `admin` middleware** (`EnsureIsAdmin`) and register it in `app/Http/Kernel.php`.
6. **Create admin API controllers** (`Admin\RiddleController`, `Admin\RiddleCategoryController`, `Admin\DashboardController`) reusing the game-facing models.
7. **Register `/admin` web routes** (host view) and `/admin/api` JSON routes.
8. **Build the Vue foundation** (layout, router, auth store, DataTable, modal/toast, base CRUD store factory).
9. **Build Phase A screens** (Dashboard, Riddle list/form, Category management).
10. **Wire auth** (session + CSRF via Axios) and frontend route guards.
11. **Write tests**:
    - Backend: `admin` middleware rejects non-admins; riddle & category CRUD endpoints require admin; suspend/unsuspend works.
    - Frontend foundations covered lightly (build compiles; router guards) — note that full FE testing infra is out of scope for now.

---

## Out of Scope (for a later phase)

- Full feature-module set (words, users, moderation, settings) — only riddles/categories now.
- Real role/permission system — deferred; reputation gate used initially.
- Frontend unit test infrastructure (Vitest) — added when UI complexity justifies it.
- Dark mode/admin themes, i18n for the admin UI.

---

## Notes & Consistency

- Reuse the riddle **models** and **reputation/moderation** conventions already established in `docs/riddle-game-implementation-plan.md`.
- Keep the **public site unchanged**: the admin panel is isolated by route prefix, layout, and CSS.
- The **answer must not leak** through any admin JSON that is accessible without admin authorization — the `admin` middleware guards all `/admin/api` routes.
- All destructive actions go through `ConfirmDialog`; all mutations use the toast pattern for feedback.
