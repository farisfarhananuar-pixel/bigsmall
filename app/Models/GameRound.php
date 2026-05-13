<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameRound extends Model
{
    protected $fillable = [
        'period',
        'result_number',
        'result_type',
        'is_open',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'result_number' => 'integer',
    ];

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }

    public static function generatePeriod(): string
    {
        return now()->format('Ymd') . '100' . str_pad(
            (self::whereDate('created_at', today())->count() + 1),
            6, '0', STR_PAD_LEFT
        );
    }

    public static function getCurrentOrCreate(): self
    {
        $round = self::where('is_open', true)->latest()->first();

        if (!$round) {
            $round = self::create([
                'period' => self::generatePeriod(),
                'is_open' => true,
            ]);
        }

        return $round;
    }

    public function getResultColorAttribute(): string
    {
        if ($this->result_number === null) return 'gray';
        if ($this->result_number === 0 || $this->result_number === 5) return 'green';
        return in_array($this->result_number, [1, 3, 7, 9]) ? 'red' : 'blue';
    }
}
