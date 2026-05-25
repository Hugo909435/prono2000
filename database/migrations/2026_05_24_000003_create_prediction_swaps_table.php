<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediction_swaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('initiator_match_id')->references('id')->on('matches')->cascadeOnDelete();
            $table->foreignId('target_match_id')->references('id')->on('matches')->cascadeOnDelete();
            $table->timestamps();

            // 1 swap per opponent per tournament (as initiator)
            $table->unique(['initiator_user_id', 'target_user_id', 'tournament_id'], 'ps_initiator_target_tournament_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_swaps');
    }
};
