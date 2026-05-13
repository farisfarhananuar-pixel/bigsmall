<?php

namespace Database\Seeders;

use App\Models\GameRound;
use Illuminate\Database\Seeder;

class GameRoundSeeder extends Seeder
{
    public function run(): void
    {
        // Skip if data already exists
        if (GameRound::count() > 0) {
            $this->command->info('GameRound data already exists, skipping seeder.');
            return;
        }

        for ($i = 100; $i >= 1; $i--) {
            $num = rand(0, 9);
            $type = $num >= 5 ? 'big' : 'small';

            GameRound::create([
                'period' => now()->subMinutes($i * 3)->format('Ymd') . '100' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'result_number' => $num,
                'result_type' => $type,
                'is_open' => false,
                'created_at' => now()->subMinutes($i * 3),
                'updated_at' => now()->subMinutes($i * 3),
            ]);
        }

        GameRound::create([
            'period' => now()->format('Ymd') . '100' . str_pad(101, 6, '0', STR_PAD_LEFT),
            'is_open' => true,
        ]);
    }
}
