<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('phase', 32)->default('waiting_players');
            $table->unsignedTinyInteger('current_turn_slot')->nullable();
            $table->json('hand1')->nullable();
            $table->json('hand2')->nullable();
            $table->string('card1', 16)->nullable();
            $table->string('card2', 16)->nullable();
            $table->string('winner', 16)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
