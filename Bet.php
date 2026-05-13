<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    protected $fillable = [
        'session_id',
        'nickname',
        'balance',
    ];

    protected $casts = [
        'balance' => 'float',
    ];

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }

    public static function getOrCreate(string $sessionId): self
    {
        return self::firstOrCreate(
            ['session_id' => $sessionId],
            [
                'nickname' => 'Player' . rand(1000, 9999),
                'balance' => 1000.00,
            ]
        );
    }

    public function addBalance(float $amount): void
    {
        $this->increment('balance', $amount);
    }

    public function deductBalance(float $amount): bool
    {
        if ($this->balance < $amount) return false;
        $this->decrement('balance', $amount);
        return true;
    }
}
