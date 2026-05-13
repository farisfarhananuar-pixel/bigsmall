<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Big Small Game</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Noto Sans', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            max-width: 480px;
            margin: 0 auto;
            font-size: 14px;
        }

        /* TOP HEADER */
        .header {
            background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
            color: white;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .header-title { font-size: 17px; font-weight: 700; letter-spacing: 0.5px; }
        .header-balance {
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .balance-label { font-size: 10px; opacity: 0.85; }
        .balance-amount { font-size: 15px; font-weight: 700; }

        /* PERIOD & TIMER */
        .period-bar {
            background: white;
            padding: 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
        }
        .period-info { font-size: 12px; color: #666; }
        .period-num { font-weight: 700; color: #333; font-size: 13px; }
        .timer-badge {
            background: #fff3e0;
            border: 1px solid #ff9800;
            color: #e65100;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        #countdown { font-size: 16px; font-weight: 800; }

        /* RESULT DISPLAY */
        .result-display {
            background: white;
            text-align: center;
            padding: 16px;
            border-bottom: 1px solid #eee;
        }
        .last-result-label { font-size: 11px; color: #999; margin-bottom: 6px; }
        .result-number-big {
            width: 70px; height: 70px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; font-weight: 800;
            margin: 0 auto 6px;
            transition: all 0.4s ease;
            border: 3px solid;
        }
        .result-number-big.red { background: #fff5f5; color: #e53935; border-color: #e53935; }
        .result-number-big.blue { background: #f3f8ff; color: #1565c0; border-color: #1565c0; }
        .result-number-big.green { background: #f0fff4; color: #2e7d32; border-color: #2e7d32; }
        .result-number-big.gray { background: #f5f5f5; color: #aaa; border-color: #ddd; }
        .result-type-badge {
            display: inline-block;
            padding: 3px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            color: white;
        }
        .result-type-badge.big { background: #ff9800; }
        .result-type-badge.small { background: #2196f3; }
        .result-type-badge.pending { background: #9e9e9e; }

        /* BET RESULT TOAST */
        .bet-result-toast {
            display: none;
            margin: 8px 16px;
            padding: 10px 16px;
            border-radius: 10px;
            text-align: center;
            font-weight: 700;
            font-size: 14px;
        }
        .bet-result-toast.win { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .bet-result-toast.lose { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }

        /* MULTIPLIER SELECTOR */
        .multiplier-section {
            background: white;
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
        }
        .multiplier-label { font-size: 11px; color: #999; margin-bottom: 8px; }
        .multiplier-row { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .mult-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1.5px solid #ddd;
            background: white;
            font-size: 13px;
            font-weight: 600;
            color: #555;
            cursor: pointer;
            transition: all 0.15s;
        }
        .mult-btn:hover { border-color: #e53935; color: #e53935; }
        .mult-btn.active { background: #e53935; color: white; border-color: #e53935; }
        .mult-btn.random { color: #e53935; border-color: #e53935; }

        /* BET INPUT */
        .bet-input-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }
        .bet-input-wrap {
            flex: 1;
            position: relative;
        }
        .bet-input-wrap span {
            position: absolute;
            left: 10px; top: 50%;
            transform: translateY(-50%);
            color: #999; font-size: 13px;
        }
        .bet-input {
            width: 100%;
            padding: 8px 10px 8px 28px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
        .bet-input:focus { outline: none; border-color: #e53935; }

        /* BIG / SMALL BUTTONS */
        .bigsmall-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            margin: 12px 16px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .big-btn, .small-btn {
            padding: 16px;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 1px;
            cursor: pointer;
            border: none;
            transition: all 0.15s;
            color: white;
        }
        .big-btn { background: linear-gradient(135deg, #ff9800, #f57c00); }
        .small-btn { background: linear-gradient(135deg, #42a5f5, #1565c0); }
        .big-btn:hover { background: linear-gradient(135deg, #ffa726, #e65100); transform: scale(1.02); }
        .small-btn:hover { background: linear-gradient(135deg, #64b5f6, #0d47a1); transform: scale(1.02); }
        .big-btn:disabled, .small-btn:disabled {
            opacity: 0.5; cursor: not-allowed; transform: none;
        }
        .current-bet-display {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin: -6px 16px 8px;
        }
        .current-bet-display span { font-weight: 700; color: #e53935; }

        /* TOP UP */
        .topup-btn {
            display: block;
            width: calc(100% - 32px);
            margin: 0 16px 12px;
            padding: 11px;
            background: linear-gradient(135deg, #43a047, #2e7d32);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
        }
        .topup-btn:hover { background: linear-gradient(135deg, #66bb6a, #388e3c); }

        /* TABS */
        .tabs {
            display: flex;
            background: white;
            border-bottom: 2px solid #eee;
            margin-top: 4px;
        }
        .tab-btn {
            flex: 1;
            padding: 12px;
            border: none;
            background: none;
            font-size: 13px;
            font-weight: 600;
            color: #999;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .tab-btn.active { color: #e53935; border-bottom-color: #e53935; }

        /* TABLE */
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .table-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #e53935;
            color: white;
            padding: 10px 16px;
            font-weight: 700;
            font-size: 13px;
        }
        .table-header span:last-child { text-align: right; }

        .stats-section {
            background: white;
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
        }
        .stats-label { font-size: 12px; color: #999; margin-bottom: 8px; font-weight: 600; }
        .stats-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .stats-row-label { font-size: 12px; color: #555; width: 110px; }
        .stats-nums { display: flex; gap: 4px; }
        .num-circle {
            width: 24px; height: 24px;
            border-radius: 50%;
            border: 1.5px solid;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 700;
            flex-shrink: 0;
        }
        .num-circle.c0, .num-circle.c5 { color: #2e7d32; border-color: #2e7d32; background: #f0fff4; }
        .num-circle.c1, .num-circle.c3, .num-circle.c7, .num-circle.c9 { color: #e53935; border-color: #e53935; background: #fff5f5; }
        .num-circle.c2, .num-circle.c4, .num-circle.c6, .num-circle.c8 { color: #1565c0; border-color: #1565c0; background: #f3f8ff; }
        .stat-val { font-size: 11px; color: #666; width: 24px; text-align: center; }

        /* HISTORY ROWS */
        .history-row {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            border-bottom: 1px solid #f5f5f5;
            background: white;
            gap: 8px;
        }
        .history-period { font-size: 12px; color: #666; min-width: 155px; }
        .history-nums { display: flex; gap: 3px; flex: 1; }
        .hist-num {
            width: 22px; height: 22px;
            border-radius: 50%;
            border: 1.5px solid;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 700;
        }
        .hist-type {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 10px;
        }
        .hist-type.big { background: #fff3e0; color: #e65100; }
        .hist-type.small { background: #e3f2fd; color: #1565c0; }

        /* MY HISTORY */
        .my-history-row {
            padding: 10px 16px;
            border-bottom: 1px solid #f5f5f5;
            background: white;
        }
        .my-history-top { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .my-history-period { font-size: 11px; color: #999; }
        .my-history-status { font-size: 12px; font-weight: 700; }
        .my-history-status.win { color: #2e7d32; }
        .my-history-status.lose { color: #e53935; }
        .my-history-status.pending { color: #ff9800; }
        .my-history-detail { font-size: 12px; color: #666; }

        /* CHART / GRAPH */
        .chart-section {
            background: white;
            padding: 12px 16px;
        }
        .chart-label { font-size: 12px; color: #999; margin-bottom: 8px; font-weight: 600; }
        #resultChart {
            width: 100%;
            height: 140px;
        }

        /* ROLLING ANIMATION */
        @keyframes rollIn {
            0% { transform: scale(0.5) rotate(-180deg); opacity: 0; }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
        }
        .rolling { animation: rollIn 0.5s ease; }

        /* TOAST NOTIFICATION */
        .toast {
            position: fixed;
            top: 70px; left: 50%; transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 10px 24px;
            border-radius: 24px;
            font-size: 13px;
            font-weight: 600;
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s;
            white-space: nowrap;
            pointer-events: none;
            max-width: 90%;
        }
        .toast.show { opacity: 1; }
        .toast.win-toast { background: #2e7d32; }
        .toast.lose-toast { background: #c62828; }

        /* LOADING */
        .spin-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 200;
            align-items: center; justify-content: center;
        }
        .spin-overlay.show { display: flex; }
        .spin-box {
            background: white;
            border-radius: 16px;
            padding: 24px 32px;
            text-align: center;
        }
        .spinner {
            width: 50px; height: 50px;
            border: 5px solid #eee;
            border-top-color: #e53935;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin: 0 auto 12px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="header-title">🎮 Big Small</div>
    <div class="header-balance">
        <div class="balance-label">Baki Kredit</div>
        <div class="balance-amount" id="balanceDisplay">RM {{ number_format($player->balance, 2) }}</div>
    </div>
</div>

<!-- PERIOD BAR -->
<div class="period-bar">
    <div>
        <div class="period-label" style="font-size:11px;color:#999">Pusingan Semasa</div>
        <div class="period-num" id="currentPeriod">{{ $currentRound->period }}</div>
    </div>
    <div class="timer-badge">
        Tutup: <span id="countdown">30</span>s
    </div>
</div>

<!-- RESULT DISPLAY -->
<div class="result-display">
    <div class="last-result-label">Keputusan Terakhir</div>
    @php
        $lastRound = $history->first();
        $lastNum = $lastRound ? $lastRound->result_number : null;
        $lastType = $lastRound ? $lastRound->result_type : null;
        $colorMap = [0=>'green',1=>'red',2=>'blue',3=>'red',4=>'blue',5=>'green',6=>'blue',7=>'red',8=>'blue',9=>'red'];
        $lastColor = $lastNum !== null ? $colorMap[$lastNum] : 'gray';
    @endphp
    <div class="result-number-big {{ $lastColor }}" id="lastResultNum">
        {{ $lastNum !== null ? $lastNum : '?' }}
    </div>
    <span class="result-type-badge {{ $lastType ?? 'pending' }}" id="lastResultType">
        {{ $lastType ? strtoupper($lastType) : 'TUNGGU...' }}
    </span>
</div>

<!-- BET RESULT TOAST AREA -->
<div class="bet-result-toast" id="betResultToast"></div>

<!-- MULTIPLIER / AMOUNT -->
<div class="multiplier-section">
    <div class="multiplier-label">Pilih Jumlah Bet (RM)</div>
    <div class="multiplier-row">
        <button class="mult-btn random" onclick="setRandom()">Rawak</button>
        <button class="mult-btn active" data-amt="1" onclick="setAmount(1, this)">RM1</button>
        <button class="mult-btn" data-amt="5" onclick="setAmount(5, this)">RM5</button>
        <button class="mult-btn" data-amt="10" onclick="setAmount(10, this)">RM10</button>
        <button class="mult-btn" data-amt="20" onclick="setAmount(20, this)">RM20</button>
        <button class="mult-btn" data-amt="50" onclick="setAmount(50, this)">RM50</button>
        <button class="mult-btn" data-amt="100" onclick="setAmount(100, this)">RM100</button>
    </div>
    <div class="bet-input-row">
        <div class="bet-input-wrap">
            <span>RM</span>
            <input type="number" class="bet-input" id="betAmount" value="1" min="1" max="10000">
        </div>
    </div>
</div>

<!-- BIG / SMALL BUTTONS -->
<div class="bigsmall-row">
    <button class="big-btn" id="bigBtn" onclick="placeBet('big')">BIG</button>
    <button class="small-btn" id="smallBtn" onclick="placeBet('small')">SMALL</button>
</div>

<div class="current-bet-display" id="currentBetDisplay">
    @if($currentBet)
        Bet semasa: <span>RM{{ number_format($currentBet->amount, 2) }} - {{ strtoupper($currentBet->bet_type) }}</span>
    @else
        Pilih BIG (5-9) atau SMALL (0-4)
    @endif
</div>

<!-- TOP UP -->
<button class="topup-btn" onclick="topUp()">💰 Isi Kredit +RM500</button>

<!-- TABS -->
<div class="tabs">
    <button class="tab-btn" onclick="switchTab('history', this)">Sejarah Game</button>
    <button class="tab-btn active" onclick="switchTab('chart', this)">Carta</button>
    <button class="tab-btn" onclick="switchTab('myhistory', this)">Sejarah Saya</button>
</div>

<!-- TAB: GAME HISTORY -->
<div id="tab-history" class="tab-content">
    <div class="table-header">
        <span>Pusingan</span>
        <span>Nombor</span>
    </div>
    <div id="historyList">
        @foreach($history as $round)
        @php
            $nums = range(0, 9);
        @endphp
        <div class="history-row">
            <span class="history-period">{{ $round->period }}</span>
            <div class="history-nums">
                @foreach($nums as $n)
                <div class="hist-num {{ $n == $round->result_number ? ($colorMap[$n] == 'green' ? 'num-circle c'.($n) : ($colorMap[$n] == 'red' ? 'num-circle c'.($n) : 'num-circle c'.($n))) : '' }}"
                     style="{{ $n == $round->result_number ? ('background:'.($colorMap[$n]=='green'?'#2e7d32':($colorMap[$n]=='red'?'#e53935':'#1565c0')).';color:white;border-color:transparent') : 'background:#f5f5f5;color:#ccc;border-color:#eee' }}">
                    {{ $n }}
                </div>
                @endforeach
            </div>
            <span class="hist-type {{ $round->result_type }}">{{ strtoupper($round->result_type) }}</span>
        </div>
        @endforeach
    </div>
</div>

<!-- TAB: CHART -->
<div id="tab-chart" class="tab-content active">
    <div class="stats-section">
        <div class="stats-label">Statistik (100 Pusingan Terakhir)</div>

        <!-- Winning numbers row -->
        <div class="stats-row">
            <span class="stats-row-label">Nombor</span>
            <div class="stats-nums">
                @for($i = 0; $i < 10; $i++)
                <div class="num-circle c{{ $i }}">{{ $i }}</div>
                @endfor
            </div>
        </div>

        <div class="stats-row">
            <span class="stats-row-label">Ketiadaan</span>
            <div class="stats-nums">
                @for($i = 0; $i < 10; $i++)
                <div class="stat-val">{{ $stats['missing'][$i] }}</div>
                @endfor
            </div>
        </div>

        <div class="stats-row">
            <span class="stats-row-label">Purata Ketiadaan</span>
            <div class="stats-nums">
                @for($i = 0; $i < 10; $i++)
                <div class="stat-val">{{ $stats['avg_missing'][$i] }}</div>
                @endfor
            </div>
        </div>

        <div class="stats-row">
            <span class="stats-row-label">Kekerapan</span>
            <div class="stats-nums">
                @for($i = 0; $i < 10; $i++)
                <div class="stat-val">{{ $stats['frequency'][$i] }}</div>
                @endfor
            </div>
        </div>

        <div class="stats-row">
            <span class="stats-row-label">Maks Berturut</span>
            <div class="stats-nums">
                @for($i = 0; $i < 10; $i++)
                <div class="stat-val">{{ $stats['max_consecutive'][$i] }}</div>
                @endfor
            </div>
        </div>
    </div>

    <!-- Recent results with line chart visual -->
    <div class="chart-section">
        <div class="chart-label">Graf Keputusan Terkini</div>
        <canvas id="resultChart"></canvas>
    </div>

    <!-- Recent rounds in history table -->
    <div class="table-header">
        <span>Pusingan</span>
        <span>Nombor</span>
    </div>
    <div id="chartHistoryList">
        @foreach($history->take(20) as $round)
        @php $nums = range(0, 9); @endphp
        <div class="history-row">
            <span class="history-period">{{ $round->period }}</span>
            <div class="history-nums">
                @foreach($nums as $n)
                <div class="hist-num"
                     style="{{ $n == $round->result_number ? ('background:'.($colorMap[$n]=='green'?'#2e7d32':($colorMap[$n]=='red'?'#e53935':'#1565c0')).';color:white;border-color:transparent') : 'background:#f5f5f5;color:#ccc;border-color:#eee' }}">
                    {{ $n }}
                </div>
                @endforeach
            </div>
            <span class="hist-type {{ $round->result_type }}">{{ strtoupper($round->result_type) }}</span>
        </div>
        @endforeach
    </div>
</div>

<!-- TAB: MY HISTORY -->
<div id="tab-myhistory" class="tab-content">
    <div id="myHistoryList">
        @forelse($myHistory as $bet)
        <div class="my-history-row">
            <div class="my-history-top">
                <span class="my-history-period">{{ $bet->gameRound->period ?? '-' }}</span>
                <span class="my-history-status {{ $bet->status }}">
                    @if($bet->status == 'win') ✅ MENANG +RM{{ number_format($bet->payout, 2) }}
                    @elseif($bet->status == 'lose') ❌ KALAH -RM{{ number_format($bet->amount, 2) }}
                    @else ⏳ Menunggu...
                    @endif
                </span>
            </div>
            <div class="my-history-detail">
                Bet RM{{ number_format($bet->amount, 2) }} pada {{ strtoupper($bet->bet_type) }}
                @if($bet->gameRound->result_number !== null)
                · Keputusan: {{ $bet->gameRound->result_number }} ({{ strtoupper($bet->gameRound->result_type) }})
                @endif
            </div>
        </div>
        @empty
        <div style="text-align:center;padding:30px;color:#999">Tiada sejarah pertaruhan lagi</div>
        @endforelse
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<!-- SPIN OVERLAY -->
<div class="spin-overlay" id="spinOverlay">
    <div class="spin-box">
        <div class="spinner"></div>
        <div style="font-weight:700;color:#333">Mengundi keputusan...</div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let countdownVal = 30;
let countdownTimer = null;
let currentPeriodId = {{ $currentRound->id }};
let balance = {{ $player->balance }};
let hasBet = {{ $currentBet ? 'true' : 'false' }};
let isSpinning = false;
let chartData = @json($history->take(20)->pluck('result_number')->reverse()->values());

// ---- COUNTDOWN ----
function startCountdown(seconds = 30) {
    clearInterval(countdownTimer);
    countdownVal = seconds;
    document.getElementById('countdown').textContent = countdownVal;
    countdownTimer = setInterval(() => {
        countdownVal--;
        document.getElementById('countdown').textContent = countdownVal;
        if (countdownVal <= 0) {
            clearInterval(countdownTimer);
            triggerSpin();
        }
    }, 1000);
}

// ---- SPIN / RESOLVE ----
async function triggerSpin() {
    if (isSpinning) return;
    isSpinning = true;
    document.getElementById('spinOverlay').classList.add('show');

    try {
        const res = await fetch('/spin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
        });
        const data = await res.json();

        document.getElementById('spinOverlay').classList.remove('show');

        if (data.action === 'result') {
            // Show result
            showResult(data.number, data.type);

            // Update balance
            if (data.balance !== undefined) {
                balance = data.balance;
                updateBalanceDisplay();
            }

            // Show win/lose toast if player had a bet
            if (data.player_bet) {
                const bet = data.player_bet;
                if (bet.status === 'win') {
                    showToast(`🎉 MENANG! +RM${parseFloat(bet.payout).toFixed(2)}`, 'win-toast');
                    showBetResult(`🎉 MENANG! Dapat RM${parseFloat(bet.payout).toFixed(2)}`, 'win');
                } else if (bet.status === 'lose') {
                    showToast(`😢 KALAH! -RM${parseFloat(bet.amount).toFixed(2)}`, 'lose-toast');
                    showBetResult(`😢 KALAH! Hilang RM${parseFloat(bet.amount).toFixed(2)}`, 'lose');
                }
            }

            // Update period
            if (data.new_period) {
                document.getElementById('currentPeriod').textContent = data.new_period;
                currentPeriodId = data.new_round_id;
            }

            // Reset bet state
            hasBet = false;
            document.getElementById('currentBetDisplay').innerHTML = 'Pilih <b>BIG</b> (5-9) atau <b>SMALL</b> (0-4)';
            enableBetButtons();

            // Reload history section
            refreshHistory();

            // Restart countdown
            setTimeout(() => { startCountdown(30); }, 1000);
        }
    } catch(e) {
        document.getElementById('spinOverlay').classList.remove('show');
        startCountdown(30);
    }
    isSpinning = false;
}

function showResult(num, type) {
    const colorMap = {0:'green',1:'red',2:'blue',3:'red',4:'blue',5:'green',6:'blue',7:'red',8:'blue',9:'red'};
    const el = document.getElementById('lastResultNum');
    el.className = `result-number-big ${colorMap[num]} rolling`;
    el.textContent = num;
    setTimeout(() => el.classList.remove('rolling'), 500);

    const typeEl = document.getElementById('lastResultType');
    typeEl.className = `result-type-badge ${type}`;
    typeEl.textContent = type === 'big' ? 'BIG' : 'SMALL';
}

function showBetResult(msg, type) {
    const el = document.getElementById('betResultToast');
    el.className = `bet-result-toast ${type}`;
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}

// ---- BET ----
async function placeBet(type) {
    if (hasBet) { showToast('Dah letak bet untuk pusingan ini!'); return; }
    if (countdownVal <= 3) { showToast('Masa dah terlalu singkat! Tunggu pusingan baru.'); return; }

    const amount = parseFloat(document.getElementById('betAmount').value);
    if (!amount || amount < 1) { showToast('Masukkan jumlah bet yang sah!'); return; }

    try {
        const res = await fetch('/bet', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ amount, bet_type: type })
        });
        const data = await res.json();

        if (data.success) {
            hasBet = true;
            balance = data.balance;
            updateBalanceDisplay();
            disableBetButtons();
            document.getElementById('currentBetDisplay').innerHTML =
                `Bet semasa: <span>RM${amount.toFixed(2)} - ${type.toUpperCase()}</span>`;
            showToast(data.message);
        } else {
            showToast(data.message);
        }
    } catch(e) {
        showToast('Ralat! Cuba lagi.');
    }
}

function disableBetButtons() {
    document.getElementById('bigBtn').disabled = true;
    document.getElementById('smallBtn').disabled = true;
}
function enableBetButtons() {
    document.getElementById('bigBtn').disabled = false;
    document.getElementById('smallBtn').disabled = false;
}

// ---- TOP UP ----
async function topUp() {
    const res = await fetch('/topup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
    });
    const data = await res.json();
    if (data.success) {
        balance = data.balance;
        updateBalanceDisplay();
        showToast('💰 ' + data.message);
    }
}

// ---- AMOUNT SELECTOR ----
function setAmount(amt, btn) {
    document.getElementById('betAmount').value = amt;
    document.querySelectorAll('.mult-btn[data-amt]').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function setRandom() {
    const options = [1, 5, 10, 20, 50, 100];
    const amt = options[Math.floor(Math.random() * options.length)];
    document.getElementById('betAmount').value = amt;
    document.querySelectorAll('.mult-btn[data-amt]').forEach(b => {
        b.classList.toggle('active', parseInt(b.dataset.amt) === amt);
    });
}

// ---- BALANCE ----
function updateBalanceDisplay() {
    document.getElementById('balanceDisplay').textContent = 'RM ' + balance.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// ---- TABS ----
function switchTab(name, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

// ---- TOAST ----
let toastTimer;
function showToast(msg, cls = '') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show ' + cls;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.className = 'toast', 3000);
}

// ---- REFRESH HISTORY ----
async function refreshHistory() {
    try {
        const res = await fetch('/status');
        const data = await res.json();
        if (data.history && data.history.length) {
            renderHistory(data.history);
        }
    } catch(e) {}
}

function renderHistory(history) {
    const colorMap = {0:'green',1:'red',2:'blue',3:'red',4:'blue',5:'green',6:'blue',7:'red',8:'blue',9:'red'};
    const bgMap = {green:'#2e7d32', red:'#e53935', blue:'#1565c0'};

    let html = '';
    history.forEach(r => {
        let numsHtml = '';
        for (let n = 0; n <= 9; n++) {
            const isResult = n == r.result_number;
            const style = isResult
                ? `background:${bgMap[colorMap[n]]};color:white;border-color:transparent`
                : `background:#f5f5f5;color:#ccc;border-color:#eee`;
            numsHtml += `<div class="hist-num" style="${style}">${n}</div>`;
        }
        const typeClass = r.result_type || 'small';
        html += `<div class="history-row">
            <span class="history-period">${r.period}</span>
            <div class="history-nums">${numsHtml}</div>
            <span class="hist-type ${typeClass}">${(r.result_type||'').toUpperCase()}</span>
        </div>`;
    });

    const list1 = document.getElementById('historyList');
    const list2 = document.getElementById('chartHistoryList');
    if (list1) list1.innerHTML = html;
    if (list2) list2.innerHTML = html;

    // Update chart data
    const nums = history.map(r => r.result_number).reverse();
    chartData = nums;
    drawChart();
}

// ---- CANVAS CHART ----
function drawChart() {
    const canvas = document.getElementById('resultChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.offsetWidth;
    const H = 140;
    canvas.width = W * window.devicePixelRatio;
    canvas.height = H * window.devicePixelRatio;
    ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

    ctx.clearRect(0, 0, W, H);

    const data = chartData.filter(v => v !== null && v !== undefined);
    if (data.length < 2) return;

    const padL = 20, padR = 10, padT = 10, padB = 20;
    const chartW = W - padL - padR;
    const chartH = H - padT - padB;

    // Grid lines
    ctx.strokeStyle = '#f0f0f0';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 9; i++) {
        const y = padT + chartH - (i / 9) * chartH;
        ctx.beginPath();
        ctx.moveTo(padL, y);
        ctx.lineTo(padL + chartW, y);
        ctx.stroke();
    }

    // Y axis labels
    ctx.fillStyle = '#bbb';
    ctx.font = '9px sans-serif';
    ctx.textAlign = 'right';
    [0, 3, 5, 7, 9].forEach(n => {
        const y = padT + chartH - (n / 9) * chartH;
        ctx.fillText(n, padL - 2, y + 3);
    });

    // Line
    const step = chartW / (data.length - 1);
    const colorMap = {0:'#2e7d32',1:'#e53935',2:'#1565c0',3:'#e53935',4:'#1565c0',5:'#2e7d32',6:'#1565c0',7:'#e53935',8:'#1565c0',9:'#e53935'};

    // Draw line with gradient
    ctx.beginPath();
    data.forEach((val, i) => {
        const x = padL + i * step;
        const y = padT + chartH - (val / 9) * chartH;
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.strokeStyle = '#e53935';
    ctx.lineWidth = 2;
    ctx.stroke();

    // Dots
    data.forEach((val, i) => {
        const x = padL + i * step;
        const y = padT + chartH - (val / 9) * chartH;
        ctx.beginPath();
        ctx.arc(x, y, 3, 0, Math.PI * 2);
        ctx.fillStyle = colorMap[val] || '#e53935';
        ctx.fill();
        ctx.strokeStyle = 'white';
        ctx.lineWidth = 1;
        ctx.stroke();
    });
}

// ---- INIT ----
window.addEventListener('load', () => {
    drawChart();
    startCountdown(30);
    @if($currentBet)
    disableBetButtons();
    @endif
});

window.addEventListener('resize', drawChart);
</script>
</body>
</html>
