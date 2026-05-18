<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('match_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('home_score');
            $table->unsignedTinyInteger('away_score');
            $table->unsignedTinyInteger('points_earned')->nullable();
            $table->enum('result_type', ['exact', 'correct_winner', 'wrong'])->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'match_id']);
            $table->index(['match_id', 'points_earned']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
