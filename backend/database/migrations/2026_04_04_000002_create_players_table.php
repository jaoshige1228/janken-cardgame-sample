<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->string('token', 64)->unique();
            $table->timestamps();

            $table->unique(['room_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
