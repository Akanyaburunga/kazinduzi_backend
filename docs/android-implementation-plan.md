# Android Frontend Implementation Plan — Rinjora Parity Experience

**Source of truth:** `docs/rinjora.html` (the prototype whose user experience we replicate pixel-for-pixel, one screen at a time).
**Language/UI:** Java + XML Views (classic Android).
**SDK:** `minSdk 24` (Android 7.0), `targetSdk` = latest stable (34+).
**Networking model:** **strictly online** — every round/action comes from the backend. Local storage is used only for the session token (and lightweight UI prefs), never for puzzle content or solved state.

This is the **native Android companion** to `docs/backend-implementation-plan.md`. The current Android client in production **diverges completely from the prototype** (it drives the legacy single-item riddle API). This document is the **adaptation target**: it consumes only the round/history/contribution endpoints below and mirrors the prototype's 100% Kirundi UI strings and screen flows. Existing client code that calls `/api/riddles/next` and friends must be retired in favour of the round API.

**Backend prerequisites** (already implemented, verified): rounds/round_items on the server, `RoundManager`, tiered pools, history, contributions. The Android client depends on two behaviours that must be live first:
- `GET /api/games/{mode}/rounds/{round}/items/{position}` — per-position item state for Back navigation (backend gap **G-1**).
- Tujajure is **flat**: no tiers, no level-up, plain shuffled unsolved jokes (backend gap **G-2**).

---

## 1. UI reference (from `docs/rinjora.html`)

The prototype is a single-page mobile web app, `max-width: 440px`, with these exact rules I must replicate as Activities/Fragments:

| Screen id | Purpose | Key elements |
|-----------|---------|--------------|
| `s-home` | Home | Hero SVG, brand "Rinjora", slogan, **3 game cards** (Sokwe… Niruze ! / Heraheza / Tujajure !) with icons + short labels, footer with **History / Contribute / About** buttons. |
| `s-quiz` | Sokwe/Heraheza the quiz | topbar (progress bar, ⭐ score pill, 🔥 streak pill), eyebrow (mode label, `count` "Rimwe / Cumi", level badge), puzzle card, free-text input, 4 primary + 3 ghost action buttons, feedback card (ok/no), reveal of answer on solve/concede. |
| `s-joke` | Tujajure | setup card, "think" prompt, 4 option buttons, feedback card, next, quit. |
| `s-end` | End | 🎊, title "Urukino ruraheze !", scorecard (`score / round`), performance message (top/mid/low), Replay / Share / Home. |
| `s-hist` | History `Amateka` | big total, duo (#games, #best), per-mode rows (icon, name, #games, ⭐points), reset, quit. |
| `s-about` | About `Ivyerekeye Rinjora` | gradient card, logo, tagline, credits. |
| `s-contrib` | Contribution `Intererano` | type selector (Igisokozo/Umwibutsa/Akajajuro/other), body textarea, answer input, name input, Send/Copy, note. |
| `lvlup` modal | Level-up | mascot, cheer "Uriko uratsinda neza! 🔥", "Ushaka gutera intambwe igoye kurusha?", Yes/No. |
| toast | transient | bottom toast for copy/reset confirmations. |

**Visual language to port:** Fredoka + Nunito fonts, ivory `#FFF6E9` bg, `--green`/`--gold`/`--red` accents, `--choco`/`--terra`/`--ochre` palette, rounded cards (~20-26dp), hard shadows (`0 5px 0`), `imigongo`-style striped top/bottom bands, emoji confetti, pop/fadeUp/shake animations.

---

## 2. Kirundi UI strings & numbers — single source

Mirror `T` and `NOMBRES` from the prototype **verbatim** in a Java class so the app is 100% Kirundi and stays in lockstep with the reference:

`app/src/main/java/com/.../ui/KirundiUi.java`
```java
public final class KirundiUi {
    public static final String SLOGAN = "Amayagwa magufi y'Ikirundi";
    public static final String[] GOOD_MSGS = {"Urabitoye ! 🎉","Uri intwari ! 💪","Uraciye ubwenge pe ! 🧠✨","Amashi menshi ! 👏"};
    public static final String STREAK_MSG = "Amashi menshi cane 👏👏👏";
    public static final String IMPA = "Impa 😉";
    public static final String CONCEDE_MSG = "Ntudebukirwe ! 💪";
    public static final String PERF_TOP = "Turagukeje cane. Uri muri bake bashoboye kuronka amanota nk'aya ! Amashi menshi 🎉🔥👏";
    public static final String PERF_MID = "Turagukeje. Ariko ubandanye wiga ibisokozo, hanyuma ubitore vyose. 👍📚✨";
    public static final String PERF_LOW = "Wagerageje. Ariko subira kwiga hahaha! 😄📖💪";
    // ... replicate ENTIRE T object (Check/Next/Quit/GiveUp/Skip/Back/Replay/Share/Home labels,
    //     History labels, About labels, Contribution labels, history names, etc.)
    public static final String[] NOMBRES = {"Rimwe","Kabiri","Gatatu","Kane","Gatanu","Gatandatu","Indwi","Umunani","Icenda","Cumi", /*... through "Mirongo ibiri"*/};
    static String motNombre(int n){ return (n>=1 && n<=20) ? NOMBRES[n-1] : String.valueOf(n); }
}
```
Rule: **no user-facing hardcoded English strings** anywhere. Everything displayable lives here (or in `strings.xml` referencing resource-qualified values; the class keeps it explicit for parity).

---

## 3. Architecture & tech stack

**Stack**
- **Language:** Java 17.
- **UI:** XML layouts + `ViewBinding` (no Compose).
- **Networking:** Retrofit 2 + OkHttp + [Moshi/Gson]. Base URL from `BuildConfig` / a `Local.properties`-backed value (e.g. `https://kazinduzi.example`); HTTP dev fallback allowed via `network_security_config` cleartext for local `10.0.2.2` (emulator) but never in release.
- **Auth:** Laravel Sanctum bearer tokens stored in **EncryptedSharedPreferences** (androidx.security-crypto). `TokenAuthenticator` re-401 → force login screen.
- **DI:** manual (Application-scoped singletons) or Hilt — recommend Hilt for testability; keep it minimal.
- **Async:** Retrofit coroutines/callbacks — since Java, use Retrofit Callbacks + an `ExecutorService`/`LiveData` wrapper; or add Kotlin coroutines only in the network layer. Prefer **Java + RxJava3** or plain callbacks to stay pure-Java.
- **Activity/Fragment nav:** single `MainActivity` + fragments per screen (5.0 fragments via androidX), with the level-up **Dialog** overlaying.

**Package map**
```
com.kazinduzi.rinjora/
  data/
    api/{RinjoraApi.java, Dtos.java, AuthInterceptor.java, TokenAuthenticator.java}
    repo/{AuthRepository, RoundRepository, HistoryRepository, ContributionRepository, MeRepository}
    session/{SessionStore.java}        // EncryptedSharedPreferences holder
  ui/
    home/{HomeFragment, adapter/GameCardAdapter}
    quiz/{QuizFragment(src sokwe|hera..)}
    joke/{JokeFragment}
    end/{EndFragment}
    hist/{HistoryFragment}
    about/{AboutFragment}
    contrib/{ContributionFragment}
    common/{FeedbackView, ConfettiView, ToastHelper, ProgressPill}
  util/{KirundiUi.java, ScoreMath.java, Matching.java(no server-matching needed), ShareHelper.java}
  MainActivity.java
  di/{AppContainer.java}
```

---

## 4. Screens & flow (one-to-one with prototype)

### 4.1 Home (`HomeFragment`)
- Hero header (static drawable approximating the campfire SVG; can be a `VectorDrawable` or an in-app banner image — fidelity here is cosmetic, keep it warm/kirundi).
- Brand + slogan text.
- 3 game cards, each: icon, `h3` name (`Sokwe… Niruze !` / `Heraheza` / `Tujajure !`), subtitle (`Ibisokozo` / `Imyibutsa — Heraheza/Tangura` / `Utujajuro — tube turatwenga`), and a `n` count/shortcut.
- On tap:
  - **Sokwe** → `QuizFragment(mode=SOKWE)`
  - **Heraheza** → `QuizFragment(mode=HERA)`
  - **Tujajure** → `JokeFragment`
- Footer nav buttons `Amateka yawe` / `Intererano yawe hano` / `Ivyerekeye Rinjora`.

### 4.2 Quiz (Sokwe/Heraheza) — `QuizFragment`
Driven by `RoundRepository`:
1. On load, `POST /api/games/{mode}/rounds` (level parsed from a saved pref if continuing). Response: `round` + first `item` (riddle/proverb text, never answer).

**Position contract (read once, apply everywhere):** the backend positions are **0-based**. The first item of a round is `position = 0`, the tenth is `position = 9`, and the payload's `item.position` is always the value to echo verbatim in the answer/skip/back URLs. Never add or subtract 1 to it, and never derive the URL position from the 1-based Kirundi display count ("Rimwe" = `position` 0, "Kabiri" = `position` 1, ...). The `round.index` field is likewise 0-based and equals the position of the *next pending* item (or `item_count` when the round is finished). Display-only conversions: UI count = `item.position + 1` for the "Rimwe / Cumi" label; progress bar shows `round.index / round.item_count`.
2. Render: `ProgressBar` (custom drawable, width %), `⭐ score`, `🔥 streak` pill (visible only when streak>0), level badge `KirundiUi: "Urugero" + level`, `rideau` text, free-text `EditText`.
3. **Check** (`Raba ko wabitoye`): `POST .../items/{position}/answer` with `{answer}` where `{position}` = the `item.position` of the currently displayed item (0-based, echoed verbatim from the payload).
   - `correct => true`: confetti, feedback ok card with random GOOD_MSG + streak flair, reveal "Inyishu yari: <firstAns>", enable Next.
   - `correct => false`: shake input, "trying" state (impa), keep position, user retypes.
   - `conceded => true`: feedback no card `CONCEDE_MSG`, reveal answer, Next.
4. **Give up** (`Ndaguhaye ! 🤲`) / **Skip** (`Rengana`): `POST .../items/{position}/skip` → same as concede (server reveals answer, resets in-round streak). Position = current displayed `item.position`.
5. **Back** (`‹ Subira inyuma`): `GET .../rounds/{round}/items/{position-1}` → the item is served in per-position state:
   - `answered: false` → render as an editable, pending item (input enabled).
   - `answered: true` + `answered_correct` → render the **solved state** (feedback ok card, revealed answer, input disabled).
   - `answered: true` + !`answered_correct` (conceded) → render the **conceded state** (feedback no card, `CONCEDE_MSG`, revealed answer, input disabled).
   - Back is only meaningful from `position ≥ 1`; there is no item at `position -1` (guard against negative positions client-side).
6. **Next** (`Bandanya`): server advances; render next item from the next `POST .../items/{position}/answer` response's `round.index`, or re-fetch `GET /api/games/{mode}/rounds/{round}` — always display the payload's `item.position`, never a locally incremented counter.
7. On `completed` or last item: call `POST .../complete`, get `{score, level_available, next_level, performance}`.
   - If `level_available` → show **level-up dialog** (below); `Yes` → start a new round at `next_level` (re-enter Quiz); `No` → `EndFragment`.
   - Else → `EndFragment`.
8. **Quit** (`Subira ku ntango`): confirm, discard round, home.

### 4.3 Level-up dialog (`LevelUpDialog`)
Mascot image, `Uriko uratsinda neza! 🔥`, "Ushaka gutera intambwe igoye kurusha?", Yes/No buttons. `Yes` calls `RoundRepository.start(mode, level=next_level)`; `No` → end.

### 4.4 Tujajure — `JokeFragment`
Tujajure is **flat by design**: the backend never tiers jokes and never sets `level_available`/`next_level` for `tuja` — ignore these fields in this fragment.
1. `POST /api/games/tuja/rounds` → `item` contains `setup` + `options` (exactly 4 punchlines, shuffled server-side).
2. "think" prompt `Iyumvire inyishu, uhitemwo 🤔` above 4 option buttons.
3. On tap option: `POST .../items/{position}/answer {option}` — `{position}` = the current displayed `item.position` (0-based, echoed verbatim; the "Rimwe / Cumi" count is that position **+ 1**).
   - Correct → highlight chosen green, `correct`, confetti, feedback ok.
   - Wrong → highlight chosen red + reveal correct green, disable all, feedback `CONCEDE_MSG` (prototype treats wrong as concede-level "no").
4. `Bandanya` → next joke; last → complete → `EndFragment` (no level-up dialog).
5. `Quit` → home.

### 4.5 End — `EndFragment`
- 🎊, `Urukino ruraheze !`, big `score / round`, label `Ivyo wari uzi` (tuja) / `Amanota uronse` (quiz).
- performance message from `KirundiUi` by backend `performance` field.
- **Replay** (`Subira ugerageze !`): same mode & level, new round.
- **Share** (`Sangiza abandi`): build text `"Rinjora — "+SLOGAN+" — "+score+" / "+round+" ⭐"` → `Intent.createChooser(ACTION_SEND)` (system share sheet).
- **Home** (`Subira ku ntango`).

### 4.6 History — `HistoryFragment`
`GET /api/games/history` → big total, #games, #best, and 3 per-mode rows (icon, name, `d.g "incuro"`, `⭐ d.p`). Empty state `hEmpty`. **Reset** (`Futa amateka yose 🗑️`) → confirm dialog with `hAsk` → `DELETE /api/games/history` → toast `hDone` → refresh. `Quit` → home.

### 4.7 About — `AboutFragment`
Static content from `KirundiUi` (tagline, credits: idea Rivardo Niyonizigiye, implementation Akanyaburunga & Gisabo Tours). `Quit` → home.

### 4.8 Contribution — `ContributionFragment`
Spinner type (`Igisokozo 🧠` / `Umwibutsa 🌾` / `Akajajuro 😂` / `Iyindi ngingo 💡`), body `TextInputLayout`, answer input, name input (`si ngombwa`).
- **Send** (`Rungika 📤`): validate `!body.isEmpty()` else toast `hEmpty`-analogue; `POST /api/contributions {type, body, answer?, who?}` → success toast + clear form (backend routes to the right submission table).
- **Copy** (`Kopora 📋`): compose the formatted text exactly as prototype `texteContrib()` ("RINJORA — <type>\n\n<body>\n\nInyishu: <ans>\n\nUwabitanze: <who>") → clipboard → toast `cCopied`.
- Note text shown beneath.

---

## 5. Network contract (endpoints consumed)

All JSON envelope `{success, data}` except answer endpoints (flat `{correct, conceded, ...}`). DTOs must match the backend doc exactly:

```
POST /api/auth/register {name,email,password,guest_uid?} -> {success,data:{converted_guest}}
POST /api/auth/login     {email,password}     -> {success,data:{token,user}}
POST /api/auth/logout    (Bearer)
POST /api/auth/guest     {guest_uid}          -> {success,data:{user,token,token_type:'Bearer',expires_at,guest}}
GET  /api/auth/guest     (Bearer)             -> {success,data:{guest:bool,summary:[...]?}}
GET  /api/me             -> {name, points:{reputation, level:{level,title,...}}, streak:{current,longest}, ...}

POST /api/games/{mode}/rounds            {level?}  -> {success,data:{round, item, guest?}}  (guest = {mode,limit,used,remaining,requires_registration})
GET  /api/games/{mode}/rounds/{round}              -> {success,data:{round, item?}}  (resume)
GET  /api/games/{mode}/rounds/{round}/items/{pos}  -> {success,data:{item}} + answered-state (Back nav, G-1)
POST /api/games/{mode}/rounds/{round}/items/{pos}/answer  {answer|option} -> flat
POST /api/games/{mode}/rounds/{round}/items/{pos}/skip    {}
POST /api/games/{mode}/rounds/{round}/complete            -> {success,data:{round:{...}, performance}}
GET  /api/games/history        -> {success,data:{total,games,best,rows:[{mode,games,points}]}}
DELETE /api/games/history      -> {success,data:{...}}
GET  /api/riddles/next  /api/proverbs/next  /api/jokes/round  /api/jokes/next   -> {success,data:{...}} (guest+account, capped for guests)
POST /api/riddles/{id}/answer  /api/proverbs/{id}/answer  /api/jokes/{id}/answer  {answer|option} -> flat
POST /api/contributions        {type,body,answer?,who?} -> {success,data:{status:'pending'}}
```

**Guest mode:** first ever launch (no stored account, no stored session) generates a persistent UUID and calls `POST /api/auth/guest` instead of showing a login wall. Store both the `token` and the `guest_uid` (e.g. app-private `SharedPreferences` backed by `Settings.Secure.ANDROID_ID` for re-install continuity). Round game endpoints work identically for guests and accounts, and the classic single-play endpoints (`riddles/proverbs/jokes/next`, `jokes/round`) are open to guests too — the per-mode cap spans both: each round start and each answered classic puzzle consumes one slot. Answer rewards are always `rewarded:false, points:0` while a guest. A `POST /api/auth/guest` with same `guest_uid` returns the same player with a fresh token (previous `GuestApp` token is revoked server-side). **Cap reached** → any round start or classic load/answer returns `403 { requires_registration: true, guest: {mode,limit,used,remaining,requires_registration}, message }` (scoped to the single mode you tried): show the create-account prompt, on success call `register` with the stored `guest_uid`, then throw away the `GuestApp` token, `login`, and retry the play.

**Position semantics — `{pos}` and every `item.position`/`round.index` are 0-based.** The first item in a round is `position = 0`; there is no position 1-based offset anywhere in the API. The client must echo the `item.position` it received **verbatim** into the answer/skip/back URLs — never `position + 1`, never a locally tracked counter, never the 1-based "Rimwe / Kabiri / ..." display ordinal. Off-by-one submissions are the #1 integration bug between the Android client and this backend; a one-line regression test (start round → answer at the returned `item.position`) prevents it forever.

**DTO fields** (Java classes `Dtos.java`): `Round{id, mode, level, item_count, index, score, best_streak, current_streak, completed, has_more_levels, next_level, level_available}`, `Item{type, id, position, question|setup, category{Dtos}, difficulty, options?:[String], answered?:boolean, answered_correct?:boolean, revealed_answer?:String}` (note `question` for riddle/proverb, `setup` for joke — parse both; `revealed_answer` is non-null only when `answered=true`), answer response `{correct, conceded, answer?, message, rewarded, points, capped, round{...}, new_achievements:[]}`. `round.index` is 0-based and equals the position of the next pending item (or `item_count` when finished); display the progress bar with `index + 1` if the guest count is 1-based ("1 / 10") and the "Rimwe / Cumi" ordinal as `position + 1`.

---

## 6. Error handling, offline, security

- **Strictly online:** any network failure shows a friendly retry (toast + retry button on the failing screen). No local puzzle cache, no offline solving. Token expiry (401) → cleared session → new `POST /api/auth/guest` (reuse stored `guest_uid`; re-registering an account requires a fresh login).
- **Auth guard:** first launch → guest session (`POST /api/auth/guest`, store `guest_uid` + token) → home. On/after 403 `requires_registration` → account prompt → `register` with `guest_uid` → `login` → home. Sessions that already have an account open straight to home when a token exists.
- **Guest invariant:** rounds played as guest continue to work after conversion (server transferred `rounds` + `riddle/proverb/joke_attempts`); the client must discard the `GuestApp` token on conversion and use the account token thereafter. Guests must be treated as non-earning everywhere (no points, no streak, no achievements) — they can still replay at the cap only after registering.
- **Cleartext:** `network_security_config.xml` permits `10.0.2.2`/`192.168.x.x` only in debug builds; release uses HTTPS only.
- **Rate limits:** backend throttles answer/skip (`30,1`); client disables double-taps on Check/Option while in-flight.

---

## 7. Animations & polish (port from CSS)

- **fadeUp**: `ObjectAnimator` translateY+alpha for screen transitions.
- **pop**: scale 0.94→1 for feedback cards.
- **shake**: translateX ±7dp 2 iterations on wrong answer.
- **confetti**: a `ViewGroup` spawning ~14 emoji (`🎉⭐🔥💫🟢🔴🟡🐇`) with vertical fall+rotate via `ValueAnimator`, honoring `PrefersReducedMotion` (`Settings.Global`/`ViewConfiguration`) → skip.
- **pulse/flick** on home hero if a GIF/animated drawable is used (optional).
- Track MVP: implement CSON animations on **correct answer**, **level-up**, **end**; others cosmetic.

---

## 8. Testing plan (Android)

- **Unit (JVM):** `KirundiUiTest` (NOMBRES/motNombre bounds), `ScoreMathTest` (performance classification), `Dtos`/Gson parsing of the backend JSON fixtures (put representative JSON strings as resources).
- **Integration:** `RoundRepositoryTest` with a fake `RinjoraApi` (OkHttp `MockWebServer`) asserting correct URL/payload and DTO mapping for start/answer/complete/history. Add a **position-echo regression test**: start a round, take the returned `item.position` value, POST the answer to that literal `items/{position}/answer` path, and assert the request URL equals the served position (catches 1-based off-by-one before it ships).
- **UI (Robolectric / Espresso):** `QuizFragmentTest` (correct→ok feedback, wrong→shake, conceded→reveal), `LevelUpDialog` flow, `ContributionFragment` validation & copy format. Network faked via MockWebServer.
- Acceptable manual QA on a real device + emulator against the live backend staging.

---

## 9. Delivery order (incremental, each milestone shippable)

1. **Skeleton**: Gradle project, minSdk 24, dependency baseline, `MainActivity` + fragment navigation, theme (font/colors from palette), `KirundiUi`. `HomeFragment` renders.
2. **Auth + Session**: `SessionStore`, `RinjoraApi` Retrofit skeleton, login/register screens, token guard, `/api/me` greeting on home.
3. **Quiz flow (Sokwe/Heraheza)**: `QuizFragment`, round start, answer, feedback, reveal, back/skip/give-up, next; `EndFragment` + share.
4. **Level-up + Tujajure**: `LevelUpDialog`, `JokeFragment` (options, highlight).
5. **History + About + Contribution**: `HistoryFragment` (+reset), `AboutFragment`, `ContributionFragment` (send/copy), `ConfettiView`, animations polish.
6. **Hardening**: error handling, rate-limit guard, release keystore + HTTPS, reduced-motion, final parity pass against `docs/rinjora.html`.

---

## 10. Parity checklist (final acceptance vs `docs/rinjora.html`)

- [ ] Home shows 3 cards + footer History/Contribute/About with exact titles/subtitles.
- [ ] Quiz renders progress bar, score pill, streak pill (streak only when >0), level badge, count in Kirundi ordinals.
- [ ] Check / Give-up / Skip / Back / Next / Quit all match prototype behaviour incl. feedback card states and emoji messages.
- [ ] Back renders a previously answered item in its solved/conceded state from `GET .../items/{pos}` (`answered`, `answered_correct`, `revealed_answer`).
- [ ] Answer revealed only after solve or concede; never before.
- [ ] **Positions are 0-based**: the app always submits answers/skips to the exact `item.position` it received (no `+1`, no counter); the "Rimwe / Cumi" count is `position + 1`. Verified by the position-echo regression test.
- [ ] Tujajure: 4 shuffled options, correct=green, wrong=red + correct highlighted, think prompt, next; **flat (never shows a level-up dialog)**.
- [ ] End screen: score/round, correct performance message per score band, Replay/Share/Home.
- [ ] Level-up dialog appears only when `score≥8` + harder tier exists (sokwe/hera only); Yes continues, No ends.
- [ ] History totals/games/best + 3 rows + reset confirmation.
- [ ] Contribution form type dropdown + send/copy + note.
- [ ] All strings 100% Kirundi (mirror `T`/`NOMBRES`); no English leaks.
- [ ] Strictly-online (401 → login, offline → retry), no puzzle caching.
