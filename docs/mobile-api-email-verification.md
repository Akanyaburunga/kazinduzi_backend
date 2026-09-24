# Mobile API — Email Verification Flow (6-digit code)

**Audience:** the developer integrating registration into the Android/mobile client.
**Backend status:** implemented, tested (`tests/Feature/Api/EmailVerificationApiTest.php`).

This document describes how the mobile app should implement account registration
with email verification against the Kazinduzi backend.

---

## 1. Flow overview

```
 ┌──────────┐    1. POST /api/auth/register        ┌─────────┐
 │  Mobile  │ ───────────────────────────────────▶ │ Backend │
 │   app    │ ◀─────────────────────────────────── │         │
 └──────────┘    201 { success: true, data: null } └─────────┘
      │                                                 │ creates unverified user,
      │                                                 │ emails 6-digit code
      │  ┌───────────────────────────────┐              │ (expires in 10 minutes)
      │  │  User reads email, enters code │◀────────────┘
      │  └───────────────────────────────┘
      │
      │    2. POST /api/auth/email/verify
      │       { email, verification_code }
      │ ◀─────────────────────────────────────────── 200 { success: true }
      │
      │    3. POST /api/auth/login  (as before)
      │       { email, password }
      │ ◀─────────────────────────────────────────── 200 { data: { user, token } }
      │
      ▼
  App now has a Sanctum token and can call game endpoints.
```

Rules:

- **Registration never returns a token.** The user must verify their email first,
  then log in to get a Sanctum bearer token.
- The 6-digit code (`verification_code`) is **valid for 10 minutes** and is only
  emailed — never returned in an API response.
- Verify and resend endpoints are **public** (no token required) so a freshly
  registered user can complete the flow before logging in.
- Until the email is verified, authenticated game routes return **403** with
  `{ success: false, message: "Your email address is not verified." }`.
  These routes are: `/api/users`, `/api/riddles`, `/api/proverbs`, `/api/jokes`,
  `/api/duels`, `/api/games`, `/api/me/favorites`, `/api/submissions/*`,
  `/api/contributions`, `/api/me` (profile/points/summary).

---

## 2. Endpoints

### 2.1 Register

`POST /api/auth/register`

Request (JSON):

```json
{
  "name": "Remy",
  "email": "remy@example.com",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

Response `201`:

```json
{
  "success": true,
  "message": "Registration successful. A verification code has been sent to your email.",
  "data": null
}
```

Error `422` for invalid payload (validation errors under `errors`).

Notes:

- The `data` field is `null` — login does NOT happen here.
- The email is sent with a 6-digit code from `App\Mail\VerificationCodeMail`
  (Kirundi template, subject: *Kwemeza Imeyile yawe*).

### 2.2 Verify email

`POST /api/auth/email/verify`  (public — no token; throttled `10/min` per client)

Request (JSON):

```json
{
  "email": "remy@example.com",
  "verification_code": "482913"
}
```

Responses:

| Status | Body (`message`) | Meaning |
|--------|------------------|---------|
| `200` | `Email verified successfully.` | Code correct & fresh → account activated |
| `200` | `Email already verified.` | Already verified — treat as success |
| `400` | `Invalid verification code.` | `verification_code` mismatch |
| `400` | `Verification code has expired.` | > 10 min since code was sent → resend |
| `404` | `User not found.` | No user with that email |
| `422` | validation errors | Missing `email` or 6-digit `verification_code` |

### 2.3 Resend code

`POST /api/auth/email/resend`  (public — no token; throttled `3/min` per client)

Request (JSON):

```json
{
  "email": "remy@example.com"
}
```

Responses:

| Status | Body (`message`) | Meaning |
|--------|------------------|---------|
| `200` | `A new verification code has been sent.` | New code emailed (10-min expiry) |
| `200` | `Email already verified.` | Nothing to do — treat as success |
| `404` | `User not found.` | No user with that email |
| `422` | validation errors | Missing `email` |

### 2.4 Login (unchanged)

`POST /api/auth/login` → `data: { user, token, token_type, expires_at }`.

Consume `data.token` as the `Authorization: Bearer <token>` header afterward.

---

## 3. Suggested mobile implementation

### 3.1 Registration screen

1. POST `/api/auth/register` with `name`, `email`, `password`, `password_confirmation`.
2. On `201` → navigate to the **Enter Code** screen (pass the email along).
3. On `422` → surface validation errors under `errors` (e.g. `email` unique
   violation, weak password).
4. Show a "10 minutes" expiry countdown on the Enter Code screen.

### 3.2 Enter Code screen

1. User types a 6-digit code (numeric keyboard, one field or 6 boxes).
2. POST `/api/auth/email/verify` with `{ email, verification_code }`.
3. On `200` → proceed to login (auto-fill the remembered email + password, or
   take the user to the login screen).
4. On `400` **expired** → call resend or prompt "Resend code".
5. On `400` **invalid** → show "Igiharuro si ibyo" inline, allow retry.
6. Provide a "Sindibona code" / resend link → POST `/api/auth/email/resend`.
7. Handle `422` for short/empty input before hitting the API.

### 3.3 Auth-gate after login

After login the user owns a token but may still be unverified (e.g. skipped
verification). When any game/API request returns `403` with
`"Your email address is not verified."`, bring the user back to the Enter Code
screen (email is already known). Treat `200 "Email already verified."` from
either verify or resend as a valid pass state.

### 3.4 State handling

- Store registration pending state (email) in memory/prefs while on the
  Enter Code screen, so resend works without re-registering.
- Never store or log `verification_code` client-side beyond the current input.
- On network failure, implement retry with backoff — verify and resend are
  idempotent from the client's perspective.

---

## 4. Code delivery to the user (dev vs prod)

How the 6-digit code reaches the user depends on the backend environment
(`APP_ENV`); the change is transparent to the mobile app — it still registers,
then verifies via `email + verification_code`.

| `APP_ENV` | Delivery | Mobile impact |
|-----------|----------|---------------|
| `local` (dev) | Code is written to the **Laravel log** (`storage/logs/laravel.log`) | No email is sent, so **do not show any "email unreachable" toast / Mailpit errors**. Read the code from the backend log and enter it in the app. |
| `staging`, `production`, ... | Normal verification email via the configured `MAIL_*` sender | The registered user receives the code by email and types it in the app. |

**Dev checklist (backend):** the code appears in `storage/logs/laravel.log` as
an `info` line `Email verification code for <email>: <code> (expires <ts>)`.
Run `php artisan serve` and watch the log while the app registers. No SMTP /
Mailpit setup is needed locally.

**Production:** set real `MAIL_MAILER` / `MAIL_HOST` / `MAIL_FROM_*` values and
make sure the mail queue/worker runs (`VerificationCodeMail` is a plain
Mailable; with a queue you must run `php artisan queue:work`).

---

## 5. Security notes (what the backend guarantees)

- Code is a cryptographically-random 6-digit value (`random_int(100000, 999999)`),
  hashed/stored on the user row, never serialized in API responses
  (`$hidden` on `App\Models\User`).
- 10-minute expiry enforced server-side on verify.
- Verify is throttled `10/min`, resend `3/min` (Laravel `throttle` middleware)
  to slow brute-force attempts.
- Successful verification fires the framework `Verified` event (referral
  reputation to the inviting user).