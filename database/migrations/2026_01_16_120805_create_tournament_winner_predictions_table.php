<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_winner_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('first_choice_team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('second_choice_team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('third_choice_team_id')->constrained('teams')->onDelete('cascade');
            $table->unsignedSmallInteger('points_earned')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'tournament_id']);
        });

        // Ajouter le vainqueur réel au tournoi
        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreignId('winner_team_id')->nullable()->constrained('teams')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['winner_team_id']);
            $table->dropColumn('winner_team_id');
        });

        Schema::dropIfExists('tournament_winner_predictions');
    }
};
