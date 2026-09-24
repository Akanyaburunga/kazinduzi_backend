<?php

namespace App\Http\Controllers\Api\Riddle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Riddle\StoreChallengeRequest;
use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\User;
use App\Support\Duels;
use App\Support\RiddleHelper;
use Illuminate\Http\Request;

class DuelController extends Controller
{
    /**
     * Create a pending challenge against another player.
     */
    public function store(StoreChallengeRequest $request)
    {
        $user = $request->user();
        $wager = (int) $request->integer('wager');

        if ((int) $request->opponent_id === $user->id) {
            return response()->json(['success' => false, 'message' => 'You cannot challenge yourself.'], 422);
        }

        $riddle = Riddle::findOrFail($request->riddle_id);
        if ($riddle->is_suspended) {
            return response()->json(['success' => false, 'message' => 'The chosen riddle is not available for a duel.'], 422);
        }

        if ($wager > (int) $user->reputation) {
            return response()->json(['success' => false, 'message' => 'You cannot wager more reputation than you hold.'], 422);
        }

        $opponent = User::findOrFail($request->opponent_id);

        if ($this->hasSolved($opponent, $request->riddle_id)) {
            return response()->json(['success' => false, 'message' => 'Your opponent has already solved that riddle.'], 422);
        }

        $pendingOutgoing = Challenge::where('initiator_id', $user->id)
            ->where('opponent_id', $opponent->id)
            ->where('status', Challenge::STATUS_PENDING)
            ->exists();
        if ($pendingOutgoing) {
            return response()->json(['success' => false, 'message' => 'You already have a pending challenge with that player.'], 422);
        }

        $challenge = Challenge::create([
            'initiator_id' => $user->id,
            'opponent_id' => $opponent->id,
            'riddle_id' => $request->riddle_id,
            'wager' => $wager,
            'status' => Challenge::STATUS_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->challengePayload($challenge, $user),
        ]);
    }

    /**
     * List challenges the authenticated user is party to (incoming and outgoing).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $challenges = Challenge::with(['riddle.category:id,name,slug', 'initiator', 'opponent', 'attempts'])
            ->where(function ($q) use ($user) {
                $q->where('initiator_id', $user->id)->orWhere('opponent_id', $user->id);
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $challenges->map(fn (Challenge $c) => $this->challengePayload($c, $user))->values(),
        ]);
    }

    /**
     * A single challenge's live status.
     */
    public function show(Request $request, Challenge $challenge)
    {
        $user = $request->user();
        if (!$this->isParticipant($challenge, $user)) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $challenge->load(['riddle.category:id,name,slug', 'initiator', 'opponent', 'winner', 'attempts']);

        return response()->json([
            'success' => true,
            'data' => $this->challengePayload($challenge, $user),
        ]);
    }

    /**
     * Accept a pending challenge (only the opponent).
     */
    public function accept(Request $request, Challenge $challenge)
    {
        $user = $request->user();
        if ($challenge->opponent_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }
        if ($challenge->status !== Challenge::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'This challenge is no longer pending.'], 422);
        }
        if ((int) $challenge->wager > (int) $user->reputation) {
            return response()->json(['success' => false, 'message' => 'You cannot cover this wager with your current reputation.'], 422);
        }

        $challenge->update([
            'status' => Challenge::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->challengePayload($challenge->fresh(['riddle.category:id,name,slug', 'initiator', 'opponent', 'attempts']), $user),
        ]);
    }

    /**
     * Decline a pending challenge (only the opponent). No wager changes hands.
     */
    public function decline(Request $request, Challenge $challenge)
    {
        $user = $request->user();
        if ($challenge->opponent_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }
        if ($challenge->status !== Challenge::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'This challenge is no longer pending.'], 422);
        }

        $challenge->update(['status' => Challenge::STATUS_DECLINED]);

        return response()->json([
            'success' => true,
            'data' => $this->challengePayload($challenge->fresh(['riddle.category:id,name,slug', 'initiator', 'opponent', 'attempts']), $user),
        ]);
    }

    /**
     * Submit a single answer to an accepted duel. Each player gets one attempt;
     * the faster correct solve (or the lone solver on a timeout) wins the wager.
     */
    public function solve(Request $request, Challenge $challenge)
    {
        $user = $request->user();

        if (!$this->isParticipant($challenge, $user)) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }
        if ($challenge->status !== Challenge::STATUS_ACCEPTED) {
            return response()->json(['success' => false, 'message' => 'This duel is not open for answers.'], 422);
        }
        if ($challenge->attempts()->where('user_id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'You have already answered this duel.'], 422);
        }

        $riddle = $challenge->riddle;
        $isCorrect = RiddleHelper::normalize((string) $request->input('answer'))
            === RiddleHelper::normalize((string) $riddle->answer);

        $attempt = ChallengeAttempt::create([
            'challenge_id' => $challenge->id,
            'user_id' => $user->id,
            'submitted_answer' => (string) $request->input('answer'),
            'is_correct' => $isCorrect,
        ]);

        $resolved = false;
        if ($isCorrect && $this->bothSolved($challenge)) {
            Duels::resolve($challenge->fresh());
            $resolved = true;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'correct' => $isCorrect,
                'resolved' => $resolved,
                'answer' => $isCorrect ? $riddle->answer : null,
                'message' => $isCorrect
                    ? ($resolved ? 'Correct! The duel is resolved.' : 'Correct! Waiting on your opponent.')
                    : 'Not quite — your single attempt is used.',
            ],
        ]);
    }

    /**
     * Whether a user has correctly solved a riddle through the normal game.
     */
    private function hasSolved(User $user, int $riddleId): bool
    {
        return RiddleAttempt::where('user_id', $user->id)
            ->where('riddle_id', $riddleId)
            ->where('is_correct', true)
            ->exists();
    }

    private function isParticipant(Challenge $challenge, User $user): bool
    {
        return $challenge->initiator_id === $user->id || $challenge->opponent_id === $user->id;
    }

    private function bothSolved(Challenge $challenge): bool
    {
        $solved = $challenge->attempts()
            ->where('is_correct', true)
            ->get()
            ->pluck('user_id')
            ->unique()
            ->count();

        return $solved >= 2;
    }

    /**
     * Build the game-facing payload for a challenge. The riddle's answer is
     * only exposed to a viewer who has actually solved that challenge.
     */
    private function challengePayload(Challenge $challenge, User $viewer): array
    {
        $myAttempt = $challenge->attempts->firstWhere('user_id', $viewer->id);
        $theirAttempt = $challenge->attempts->firstWhere('user_id', '!=', $viewer->id);

        $riddle = $challenge->riddle;
        $solvedByViewer = $myAttempt && $myAttempt->is_correct;

        return [
            'id' => $challenge->id,
            'status' => $challenge->status,
            'wager' => $challenge->wager,
            'direction' => $challenge->initiator_id === $viewer->id ? 'outgoing' : 'incoming',
            'accepted_at' => $challenge->accepted_at,
            'resolved_at' => $challenge->resolved_at,
            'riddle' => $riddle ? [
                'id' => $riddle->id,
                'question' => $riddle->question,
                'difficulty' => $riddle->difficulty,
                'riddle_type' => $riddle->riddle_type,
                'category' => $riddle->category
                    ? ['id' => $riddle->category->id, 'name' => $riddle->category->name, 'slug' => $riddle->category->slug]
                    : null,
                'answer' => $solvedByViewer ? $riddle->answer : null,
            ] : null,
            'initiator' => $this->playerPayload($challenge->initiator),
            'opponent' => $this->playerPayload($challenge->opponent),
            'my_attempt' => $this->attemptPayload($myAttempt),
            'opponent_attempt' => $this->attemptPayload($theirAttempt, false),
            'winner_id' => $challenge->winner_id,
            'created_at' => $challenge->created_at,
        ];
    }

    private function playerPayload(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'reputation' => (int) $user->reputation,
        ];
    }

    private function attemptPayload(?ChallengeAttempt $attempt, bool $isMine = true): ?array
    {
        if (!$attempt) {
            return null;
        }

        return [
            'user_id' => $attempt->user_id,
            'is_correct' => $attempt->is_correct,
            // Only ever expose the match answer to the player who submitted it.
            'submitted_answer' => $isMine ? $attempt->submitted_answer : null,
            'solved_at' => $attempt->created_at,
        ];
    }
}
