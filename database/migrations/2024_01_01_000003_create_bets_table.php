<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_round_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('bet_type', 10); // big or small
            $table->string('status', 10)->default('pending'); // pending, win, lose
            $table->decimal('payout', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bets');
    }
};
