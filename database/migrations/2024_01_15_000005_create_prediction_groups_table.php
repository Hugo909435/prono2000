<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('prediction_group_user');
        Schema::dropIfExists('prediction_groups');
    }
};
