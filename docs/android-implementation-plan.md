# Android Frontend Implementation Plan — Rinjora Parity Experience

**Source of truth:** `docs/rinjora.html` (the prototype whose user experience we replicate pixel-for-pixel, one screen at a time).
**Language/UI:** Java + XML Views (classic Android).
**SDK:** `minSdk 24` (Android 7.0), `targetSdk` = latest stable (34+).
**Networking model:** **strictly online** — every round/action comes from the backend. Local storage is used only for the session token (and lightweight UI prefs), never for puzzle content or solved state.

This is the **new native Android companion** to the backend plan in `docs/backend-implementation-plan.md`. It consumes the backend's round/history/contribution APIs and mirrors the prototype's 100% Kirundi UI strings and screen flows.

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
2. Render: `ProgressBar` (custom drawable, width %), `⭐ score`, `🔥 streak` pill (visible only when streak>0), level badge `KirundiUi: "Urugero" + level`, `rideau` text, free-text `EditText`.
3. **Check** (`Raba ko wabitoye`): `POST .../items/{position}/answer` with `{answer}`.
   - `correct => true`: confetti, feedback ok card with random GOOD_MSG + streak flair, reveal "Inyishu yari: <firstAns>", enable Next.
   - `correct => false`: shake input, "trying" state (impa), keep position, user retypes.
   - `conceded => true`: feedback no card `CONCEDE_MSG`, reveal answer, Next.
4. **Give up** (`Ndaguhaye ! 🤲`) / **Skip** (`Rengana`): `POST .../items/{position}/skip` → same as concede (server reveals answer, resets in-round streak).
5. **Back** (`‹ Subira inyuma`): `GET .../rounds/{round}/items/{position-1}` → if already answered, render solved state (answer hidden + feedback), input disabled.
6. **Next** (`Bandanya`): server advances; render next item.
7. On `completed` or last item: call `POST .../complete`, get `{score, level_available, next_level, performance}`.
   - If `level_available` → show **level-up dialog** (below); `Yes` → start a new round at `next_level` (re-enter Quiz); `No` → `EndFragment`.
   - Else → `EndFragment`.
8. **Quit** (`Subira ku ntango`): confirm, discard round, home.

### 4.3 Level-up dialog (`LevelUpDialog`)
Mascot image, `Uriko uratsinda neza! 🔥`, "Ushaka gutera intambwe igoye kurusha?", Yes/No buttons. `Yes` calls `RoundRepository.start(mode, level=next_level)`; `No` → end.

### 4.4 Tujajure — `JokeFragment`
1. `POST /api/games/tuja/rounds` → `item` contains `setup` + `options` (exactly 4 punchlines, shuffled server-side).
2. "think" prompt `Iyumvire inyishu, uhitemwo 🤔` above 4 option buttons.
3. On tap option: `POST .../items/{position}/answer {option}`.
   - Correct → highlight chosen green, `correct`, confetti, feedback ok.
   - Wrong → highlight chosen red + reveal correct green, disable all, feedback `CONCEDE_MSG` (prototype treats wrong as concede-level "no").
4. `Bandanya` → next joke; last → complete → end flow (level-up applies to tuja too where relevant per backend).
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
POST /api/auth/register {name,email,password} -> {success,data:{token,user}}
POST /api/auth/login     {email,password}     -> {success,data:{token,user}}
POST /api/auth/logout    (Bearer)
GET  /api/me             -> {name, points:{reputation, level:{level,title,...}}, streak:{current,longest}, ...}

POST /api/games/{mode}/rounds            {level?}  -> {success,data:{round, item}}
GET  /api/games/{mode}/rounds/{round}              -> {success,data:{round, item?}}  (resume)
POST /api/games/{mode}/rounds/{round}/items/{pos}/answer  {answer|option} -> flat
POST /api/games/{mode}/rounds/{round}/items/{pos}/skip    {}
POST /api/games/{mode}/rounds/{round}/complete            -> {success,data:{round:{...}, performance}}
GET  /api/games/history        -> {success,data:{total,games,best,rows:[{mode,games,points}]}}
DELETE /api/games/history      -> {success,data:{...}}
POST /api/contributions        {type,body,answer?,who?} -> {success,data:{status:'pending'}}
```

**DTO fields** (Java classes `Dtos.java`): `Round{id, mode, level, item_count, index, score, best_streak, current_streak, completed, level_available, next_level}`, `Item{type, id, position, question|setup, category{Dtos}, options?:[String]}` (note `question` for riddle/proverb, `setup` for joke — parse both), answer response `{correct, conceded, answer?, message, round{...}, new_achievements:[]}`.

---

## 6. Error handling, offline, security

- **Strictly online:** any network failure shows a friendly retry (toast + retry button on the failing screen). No local puzzle cache, no offline solving. Token expiry (401) → cleared session → forced `LoginFragment`.
- **Auth guard:** app opens to login if no token; else home. Auth state via `SessionStore` + LiveData.
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
- **Integration:** `RoundRepositoryTest` with a fake `RinjoraApi` (OkHttp `MockWebServer`) asserting correct URL/payload and DTO mapping for start/answer/complete/history.
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
- [ ] Answer revealed only after solve or concede; never before.
- [ ] Tujajure: 4 shuffled options, correct=green, wrong=red + correct highlighted, think prompt, next.
- [ ] End screen: score/round, correct performance message per score band, Replay/Share/Home.
- [ ] Level-up dialog appears only when `score≥8` + harder tier exists; Yes continues, No ends.
- [ ] History totals/games/best + 3 rows + reset confirmation.
- [ ] Contribution form type dropdown + send/copy + note.
- [ ] All strings 100% Kirundi (mirror `T`/`NOMBRES`); no English leaks.
- [ ] Strictly-online (401 → login, offline → retry), no puzzle caching.
