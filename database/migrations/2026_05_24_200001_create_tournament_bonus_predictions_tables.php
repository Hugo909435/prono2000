<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_loser_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->unsignedSmallInteger('points_earned')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'tournament_id']);
        });

        Schema::create('tournament_top_scorer_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->string('player_name');
            $table->unsignedSmallInteger('points_earned')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'tournament_id']);
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreignId('loser_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->string('top_scorer_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['loser_team_id']);
            $table->dropColumn(['loser_team_id', 'top_scorer_name']);
        });
        Schema::dropIfExists('tournament_top_scorer_predictions');
        Schema::dropIfExists('tournament_loser_predictions');
    }
};
