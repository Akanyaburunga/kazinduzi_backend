# 🤖 Rinjora Android (Java) — Proverb & Joke Modes + Lenient Answer UX

## Purpose

This document is the **backend → Android (Java)** contract for two improvements defined in [`docs/rinjora-parity-implementation-plan.md`](rinjora-parity-implementation-plan.md) (read that first for the wire contract):

1. **Heraheza** — proverb mode (complete the ending).
2. **Tujajure** — joke mode (pick the punchline from 4 options).
3. **Lenient answer matching** — the backend now accepts free word order, accents/case-insensitivity, `/`-alternatives, synonyms and small typos. The Android client must surface this behaviour (multi-attempt allowed, "concede" via typing `ndaguhaye`, no-hint education reveal) and pass plain user text through unchanged.

It assumes the Android client described in [`docs/android-app-implementation-plan.md`](android-app-implementation-plan.md) already exists (networking, auth, admin/home screens). The new work extends that app with two more game modes and their history/statistics.

This is **frontend-only documentation** — the backend endpoints are specified in the parity plan. Endpoint shapes below are quoted from that contract so you can implement without switching files.

---

## 0. Reading Guide

- **Base URL / auth / envelope** are identical to the existing Android plan: Bearer token + `verified`; responses are `{ success, data, ... }`.
- **Never** render an `answer` / `punchline` / `ending` to an unsolved item. Only show it after the player solved it or used "reveal".
- **Conventions:** package `com.kazinduzi.app`; MVVM + `ViewModel` + `LiveData/StateFlow` + `Repository` as in the existing app; Retrofit + Moshi/Gson; Room for offline cache of solved items.
- All new screens follow the existing navigation (single top-level Activity + `NavHostFragment`, or Compose if your codebase uses it — keep it consistent with riddles).

### Status codes you must handle (same as existing app)
| Code | Meaning | Client action |
|------|---------|---------------|
| 200 | Correct / loaded | Use `data` |
| 404 | Nothing left to solve (proverbs/jokes) | Show "all done" empty state |
| 422 | Wrong answer (jokes) / validation / daily cap | Show feedback (joke endpoint returns `correct:false` + `answer`) |

---

## 1. Shared: Lenient Answer UX (applies to Sokwe riddles + Heraheza proverbs)

The backend matcher is lenient, but the **client does NOT do any matching** — it passes raw user text to the API. Client responsibilities:

### 1.1 Auto-normalize the typed input for the *display of* what was accepted
Only for echoing back the solved answer, mirror the backend's normalisation so the UI looks consistent. Add a small util:

```java
public final class TextUtil {
    // Lowercase, strip accents, trim. Matches backend intent (not for scoring).
    public static String normalize(String s) {
        if (s == null) return "";
        String n = Normalizer.normalize(s, Normalizer.Form.NFKD)
                .replaceAll("\\p{M}", "")          // strip combining marks
                .replaceAll("[\\p{Punct}]+", " ")  // punctuation -> space
                .replaceAll("\\s+", " ").trim();
        return n.toLowerCase(Locale.ROOT);
    }
}
```

### 1.2 Allow multiple attempts
Because matching is lenient + typos are accepted, do **not** disable the answer box after one wrong guess. Keep it editable and let the user retry (the backend only rewards the first correct solve, but a wrong-then-right sequence is allowed). Only lock the box once `correct:true` (or the player concedes).

### 1.3 Concede ("give up") gesture
- If the user types **`ndaguhaye`** (case/accents-insensitive), the backend treats it as a concede → records a failed attempt and returns the answer, **no reward**.
- Client: on the answer screen, show a hint line "Andika 'ndaguhaye' kugira uronke inyishu" (Write "ndaguhaye" to get the answer) or a "Ndaguhaye ! 🤲" button that fills the box and submits.
- After concede, display the correct answer with a "learning" label and offer "next".

### 1.4 Reveal (education path)
- Provide a secondary "reveal" action → `POST /.../{id}/reveal` returns `{ id, question, answer }` with **no reward**. Use for "I'm stuck, show me".
- Separate the reveal action visually from "check" (dimmed ghost button) so players know it earns nothing.

### 1.5 Same answer screen component
Extract a single reusable `AnswerFragment`/`AnswerScreen` used by both the riddle (Sokwe) and proverb (Heraheza) modes. Props: `question`, `placeholder`, `accent colour`, and submit handler. This enforces identical lenient/retry/concede/reveal behaviour in both modes with one code path.

---

## 2. Heraheza — Proverb Mode

### 2.1 Flow
1. Tapping **Heraheza** opens the proverb home listing.
2. Tap a proverb → proverb screen: shows the beginning `question` (ends with `…`), a text box, retry/concede/reveal.
3. On `correct:true`: show the full proverb (beginning + ending), award points toast if `rewarded:true`, mark solved, "next".
4. History/stats per-section (see §4).

### 2.2 Endpoints (from parity plan)
```
GET  /proverbs?category_id=&difficulty=&sort=new|trending     [auth + verified]
GET  /proverbs/{id}
GET  /proverbs/next?difficulty=                               (404 when none left)
POST /proverbs/{id}/answer   body: { "answer": "..." }        (throttle 30/min)
POST /proverbs/{id}/reveal   → { id, question, answer }       no reward
```

List item payload:
```json
{
  "id": 3, "solved": false,
  "category": { "id": 2, "name": "Inkuru", "slug": "inkuru" },
  "question": "Abahigi benshi…", "difficulty": "medium",
  "source": "Imigani y'ikirundi", "created_at": "..."
}
```
`GET /proverbs/{id}` → same payload (still **no answer** until solved/revealed).

Answer response (same shape as riddles):
```json
{ "success": true, "correct": true, "rewarded": true, "points": 5, "capped": false,
  "message": "Correct! You earned 5 reputation points.", "new_achievements": [] }
```
Wrong → `{ "success": true, "correct": false, "rewarded": false, "message": "Not quite. Try again." }` (retry allowed).

### 2.3 Models & repository

```java
public class Proverb {
    public long id; public boolean solved;
    @SerializedName("category") public Category category;
    public String question; public String difficulty; public String source;
    @SerializedName("created_at") public String createdAt;
    // Server never sends answer until solved/revealed
    public String answer;
}
```

`ProverbRepository` with `getProverbs(filter)`, `getProverb(id)`, `getNext()`, `submitAnswer(id, answer)`, `reveal(id)`. Cache solved proverbs in Room (`proverb_cache` table keyed by id + `answer`).

### 2.4 Screens
- `ProverbListFragment` — same list/composable used by the riddle list, but labelled with the Heraheza accent (gold, per prototype).
- `ProverbDetailFragment` — reuse `AnswerFragment` with `question` + placeholder "Heza uyu mugani aha…" and the gold accent.

---

## 3. Tujajure — Joke Mode (multiple choice)

### 3.1 Flow
1. Tapping **Tujajure** fetches a round.
2. Screen shows the setup and **4 options** as buttons.
3. Player taps one → client POSTs the chosen option text.
4. Backend returns correct/wrong. On correct: celebrate + `next`. On wrong: backend returns the correct punchline → highlight the right option and the wrong tap, then `next`.

### 3.2 Endpoints (from parity plan)
```
GET  /jokes/round        → { "joke_id": 9, "setup": "...", "options": ["...","...","...","..."] }
POST /jokes/{id}/answer  body: { "option": "..." }
GET  /jokes/next         → next unsolved setup (404 when none left)
POST /jokes/{id}/reveal  → { id, setup, punchline }    no reward
```

`GET /jokes/round` payload:
```json
{
  "success": true,
  "data": {
    "joke_id": 9,
    "setup": "Agaca gacakiye agahori gati:",
    "options": ["Mwana wa mama undiye twari bamwe.", "Nagira ngo akaguruka ntikoriye akandi.", "...", "..."]
  }
}
```
> The 4 options arrive already **server-shuffled** and include the correct punchline exactly once. The client must **not** re-sort; render in the given order.

Answer:
- Correct → `{ "success": true, "correct": true, "rewarded": true, "points": 5, "message": "...", "new_achievements": [] }`
- Wrong → `{ "success": false, "correct": false, "message": "...", "answer": "<correct punchline>" }` (the `answer` field lets the client reveal which option was right).

### 3.3 Models & repository

```java
public class JokeRound {
    @SerializedName("joke_id") public long jokeId;
    public String setup;
    public List<String> options;   // DO NOT reorder client-side
}
```

`JokeRepository.getRound()`, `submitAnswer(jokeId, option)`, `getNext()`, `reveal(jokeId)`.

### 3.4 Screens
- `JokeRoundFragment` — setup text + dynamic option buttons built from `options`. One tap = one submission; disable all buttons while the request is in flight.
- Option button states: normal → (on result) the correct option gets a green border, the tapped-wrong one red. If `correct:true`, colour the tapped (correct) option green.
- Add a "Reveal" ghost button (education, no reward) for when the player just wants the punchline.

---

## 4. History & Statistics (per mode)

### 4.1 Cross-mode history
Extend the existing history screen to show three scoped sections: **Sokwe (riddles)**, **Heraheza (proverbs)**, **Tujajure (jokes)**.

Backend note (§2.11 of parity plan is riddle-only today): this phase may add `GET /proverbs/history/stats` and `GET /jokes/history/stats` mirroring `GET /riddles/history/stats`. **Implement client-side against whatever the backend exposes.** Until then:
- Riddles: existing `GET /riddles/history` + `GET /riddles/history/stats`.
- Proverbs: render `GET /proverbs/history` (list) + `GET /proverbs/history/stats` if present; otherwise show per-mode totals locally from Room.
- Jokes: `GET /jokes/history` + `GET /jokes/history/stats` if present; else local Room totals.

Display per mode: total attempts, solved, accuracy, best score, per-category breakdown (reuse the existing `StatsCard`/summary cards).

### 4.2 Client-side "points" (optional parity with prototype)
The prototype tracks `total/games/best` per mode in localStorage. Since our progress is server-backed, show `reputation` (from `GET /me`) as the global score and per-mode solved counts from history. Do **not** replicate client-local scoring that diverges from the backend.

---

## 5. Phase Order (frontend) & Acceptance Criteria

### Phase F1 — Shared answer UX refactor
- Add `TextUtil` + reusable `AnswerFragment` used by riddles and (new) proverbs.
- Multi-attempt, concede (`ndaguhaye`), reveal — implemented once.
- **Accept:** a wrong-then-right riddle solve works; typing `ndaguhaye` reveals answer with no reward; reveal button earns nothing; accent/case-insensitive input accepted.

### Phase F2 — Heraheza
- `Proverb` model + `ProverbRepository` + `ProverbListFragment` + `ProverbDetailFragment`.
- Wire navigation to a new **Heraheza** entry/icon on the home menu.
- **Accept:** list proverbs, solve with a lenient/alternative answer (points toast when `rewarded`), retry on wrong, concede/reveal, "next", empty state when 404.

### Phase F3 — Tujajure
- `JokeRound` model + `JokeRepository` + `JokeRoundFragment`.
- Home **Tujajure** entry.
- **Accept:** round shows exactly 4 options in server order; one tap submits; correct celebrates + next; wrong reveals the correct option (green/red) then next; reveal ghost button works; buttons disabled during flight.

### Phase F4 — History/statistics per mode
- Extend history screen with three sections using backend endpoints (or local fallback).
- **Accept:** per-mode solved count/accuracy renders; empty states correct.

---

## 6. Risks & Notes

- **Do not reorder joke options** — the backend shuffle is authoritative; re-sorting client-side could bias/break the answer mapping.
- **Punchline leak** — never store a `punchline` separately from the round options in a way the player can inspect (e.g. don't log it, don't prefill it). Only the 4 `options` are safe on the round screen.
- **Multi-attempt scoring** — the backend rewards only the **first correct solve**; after that the answer box may show "already solved". Handle idempotent re-submission gracefully.
- **Shared daily cap** — points toast may say `capped:true` when the daily reputation cap is reached across riddles **and** proverbs. Surface the cap message, don't treat it as an error.
- **Room cache of answers** — only persist the `answer`/`punchline` after `correct:true` or a reveal, and only for the owner; never pre-cache unsolved answers.
- **RTL/Kirundi text** — Kirundi is LTR; ensure fonts render diacritics (Nunito/Fredoka as in the prototype, or bundled fonts in native views). No RTL layout needed.

---

## 7. Deliverables Summary
- `TextUtil.java` (normalisation, display-only).
- `AnswerFragment`/`AnswerScreen` (shared, used by Sokwe + Heraheza).
- `Proverb`, `JokeRound` models; `ProverbRepository`, `JokeRepository`.
- `ProverbListFragment`, `ProverbDetailFragment`, `JokeRoundFragment`.
- Home menu entries for Heraheza and Tujajure.
- History screen extended with three per-mode sections.
- Room cache (`proverb_cache`, `joke_cache`) for offline solved content.
- Unit/UI tests per phase using MockWebServer against the parity-plan JSON fixtures.
