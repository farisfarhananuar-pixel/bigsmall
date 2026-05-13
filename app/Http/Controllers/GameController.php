<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\GameRound;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    /**
     * Show main game page
     */
    public function index(Request $request)
    {
        $player = Player::getOrCreate($request->session()->getId());
        $currentRound = GameRound::getCurrentOrCreate();

        // Get last 100 completed rounds for chart
        $history = GameRound::where('is_open', false)
            ->whereNotNull('result_number')
            ->latest()
            ->limit(100)
            ->get();

        // Statistics for last 100 periods
        $stats = $this->getStatistics($history);

        // Player's bet on current round
        $currentBet = Bet::where('player_id', $player->id)
            ->where('game_round_id', $currentRound->id)
            ->first();

        // Player's bet history
        $myHistory = Bet::with('gameRound')
            ->where('player_id', $player->id)
            ->latest()
            ->limit(20)
            ->get();

        return view('game.index', compact(
            'player',
            'currentRound',
            'history',
            'stats',
            'currentBet',
            'myHistory'
        ));
    }

    /**
     * Place a bet
     */
    public function placeBet(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:10000',
            'bet_type' => 'required|in:big,small',
        ]);

        $player = Player::getOrCreate($request->session()->getId());
        $currentRound = GameRound::getCurrentOrCreate();

        // Check if round is still open
        if (!$currentRound->is_open) {
            return response()->json([
                'success' => false,
                'message' => 'Pusingan ini dah tutup. Tunggu pusingan baru!',
            ]);
        }

        // Check if already bet this round
        $existingBet = Bet::where('player_id', $player->id)
            ->where('game_round_id', $currentRound->id)
            ->first();

        if ($existingBet) {
            return response()->json([
                'success' => false,
                'message' => 'Dah letak bet untuk pusingan ini!',
            ]);
        }

        $amount = (float) $request->amount;

        // Deduct balance
        if (!$player->deductBalance($amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Baki tidak mencukupi! Tekan butang isi kredit.',
            ]);
        }

        // Create bet
        $bet = Bet::create([
            'player_id' => $player->id,
            'game_round_id' => $currentRound->id,
            'amount' => $amount,
            'bet_type' => $request->bet_type,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bet berjaya! RM' . number_format($amount, 2) . ' pada ' . strtoupper($request->bet_type),
            'balance' => $player->fresh()->balance,
            'bet' => $bet,
        ]);
    }

    /**
     * Spin / resolve current round (called via AJAX every X seconds)
     */
    public function spin(Request $request)
    {
        $currentRound = GameRound::where('is_open', true)->latest()->first();

        if (!$currentRound) {
            // Create new round
            $newRound = GameRound::create([
                'period' => GameRound::generatePeriod(),
                'is_open' => true,
            ]);

            return response()->json([
                'action' => 'new_round',
                'round' => $newRound,
            ]);
        }

        // Resolve the round - generate random number
        $number = rand(0, 9);
        $type = $number >= 5 ? 'big' : 'small';

        DB::transaction(function () use ($currentRound, $number, $type) {
            $currentRound->update([
                'result_number' => $number,
                'result_type' => $type,
                'is_open' => false,
            ]);

            // Process all bets for this round
            $bets = Bet::with('player')
                ->where('game_round_id', $currentRound->id)
                ->where('status', 'pending')
                ->get();

            foreach ($bets as $bet) {
                if ($bet->bet_type === $type) {
                    // Win - double the amount
                    $payout = $bet->amount * 2;
                    $bet->update([
                        'status' => 'win',
                        'payout' => $payout,
                    ]);
                    $bet->player->addBalance($payout);
                } else {
                    // Lose
                    $bet->update([
                        'status' => 'lose',
                        'payout' => 0,
                    ]);
                }
            }
        });

        // Create next round immediately
        $newRound = GameRound::create([
            'period' => GameRound::generatePeriod(),
            'is_open' => true,
        ]);

        $player = Player::getOrCreate($request->session()->getId());
        $playerBet = Bet::where('player_id', $player->id)
            ->where('game_round_id', $currentRound->id)
            ->first();

        return response()->json([
            'action' => 'result',
            'number' => $number,
            'type' => $type,
            'period' => $currentRound->period,
            'balance' => $player->fresh()->balance,
            'player_bet' => $playerBet,
            'new_round_id' => $newRound->id,
            'new_period' => $newRound->period,
        ]);
    }

    /**
     * Top up balance (fake deposit)
     */
    public function topUp(Request $request)
    {
        $player = Player::getOrCreate($request->session()->getId());
        $player->addBalance(500.00);

        return response()->json([
            'success' => true,
            'message' => 'RM500 kredit berjaya ditambah!',
            'balance' => $player->fresh()->balance,
        ]);
    }

    /**
     * Get latest game data (polling)
     */
    public function getStatus(Request $request)
    {
        $player = Player::getOrCreate($request->session()->getId());
        $currentRound = GameRound::getCurrentOrCreate();

        $history = GameRound::where('is_open', false)
            ->whereNotNull('result_number')
            ->latest()
            ->limit(20)
            ->get();

        $currentBet = Bet::where('player_id', $player->id)
            ->where('game_round_id', $currentRound->id)
            ->first();

        return response()->json([
            'balance' => $player->balance,
            'current_round' => $currentRound,
            'current_bet' => $currentBet,
            'history' => $history,
        ]);
    }

    /**
     * Get statistics for last 100 rounds
     */
    private function getStatistics($history): array
    {
        $stats = [];
        $counts = array_fill(0, 10, 0);
        $lastSeen = array_fill(0, 10, 0);
        $consecutive = array_fill(0, 10, 0);
        $maxConsecutive = array_fill(0, 10, 0);
        $tempConsec = array_fill(0, 10, 0);

        $rounds = $history->values();
        $total = count($rounds);

        foreach ($rounds as $i => $round) {
            $n = $round->result_number;
            if ($n === null) continue;
            $counts[$n]++;

            // Missing = how many rounds since last seen
            $lastSeen[$n] = $i;

            // Consecutive
            if ($i > 0 && $rounds[$i-1]->result_number === $n) {
                $tempConsec[$n]++;
            } else {
                $tempConsec[$n] = 1;
            }
            if ($tempConsec[$n] > $maxConsecutive[$n]) {
                $maxConsecutive[$n] = $tempConsec[$n];
            }
        }

        // Missing = current position minus last seen index
        $missing = [];
        for ($i = 0; $i < 10; $i++) {
            $missing[$i] = $counts[$i] === 0 ? $total : ($total - $lastSeen[$i] - 1);
        }

        // Avg missing (approximate)
        $avgMissing = [];
        for ($i = 0; $i < 10; $i++) {
            $avgMissing[$i] = $counts[$i] > 0 ? (int) round($total / $counts[$i]) : 0;
        }

        return [
            'frequency' => $counts,
            'missing' => $missing,
            'avg_missing' => $avgMissing,
            'max_consecutive' => $maxConsecutive,
        ];
    }
}
