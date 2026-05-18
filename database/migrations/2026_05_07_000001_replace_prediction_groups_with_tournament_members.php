<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter access_code aux tournaments
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('access_code', 8)->unique()->after('status');
        });

        // Créer la table pivot tournament_user
        Schema::create('tournament_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role')->default('member');
            $table->integer('total_points')->default(0);
            $table->integer('exact_scores')->default(0);
            $table->integer('correct_results')->default(0);
            $table->integer('wrong_predictions')->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['tournament_id', 'user_id']);
        });

        // Supprimer les anciennes tables
        Schema::dropIfExists('prediction_group_user');
        Schema::dropIfExists('prediction_groups');
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_user');

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('access_code');
        });

        Schema::create('prediction_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('access_code', 8)->unique();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->index('access_code');
        });

        Schema::create('prediction_group_user', function (Blueprint $table) {
            $table->foreignId('prediction_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedSmallInteger('exact_scores')->default(0);
            $table->unsignedSmallInteger('correct_results')->default(0);
            $table->enum('role', ['member', 'admin'])->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->primary(['prediction_group_id', 'user_id']);
        });
    }
};
