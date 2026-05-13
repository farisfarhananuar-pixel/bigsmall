<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bet extends Model
{
    protected $fillable = [
        'player_id',
        'game_round_id',
        'amount',
        'bet_type',
        'status',
        'payout',
    ];

    protected $casts = [
        'amount' => 'float',
        'payout' => 'float',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function gameRound(): BelongsTo
    {
        return $this->belongsTo(GameRound::class);
    }
}
