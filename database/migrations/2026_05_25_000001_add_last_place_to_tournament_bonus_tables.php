<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_last_place_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('predicted_user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedSmallInteger('points_earned')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'tournament_id']);
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreignId('last_place_user_id')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['last_place_user_id']);
            $table->dropColumn('last_place_user_id');
        });
        Schema::dropIfExists('tournament_last_place_predictions');
    }
};
