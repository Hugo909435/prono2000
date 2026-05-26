<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('position');
            $table->integer('total_points')->default(0);
            $table->integer('exact_scores')->default(0);
            $table->integer('correct_results')->default(0);
            $table->date('football_day');
            $table->timestamps();
            $table->unique(['tournament_id', 'user_id', 'football_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_snapshots');
    }
};
