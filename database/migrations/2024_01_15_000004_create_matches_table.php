<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('tournament_group_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->string('round');
            $table->unsignedTinyInteger('match_number')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('deadline_at')->nullable();
            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();
            $table->unsignedTinyInteger('home_score_penalties')->nullable();
            $table->unsignedTinyInteger('away_score_penalties')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->string('placeholder_home')->nullable();
            $table->string('placeholder_away')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'round']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
