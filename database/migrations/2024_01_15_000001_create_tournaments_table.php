<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('format', ['elimination', 'groups_elimination']);
            $table->unsignedTinyInteger('team_count');
            $table->unsignedTinyInteger('group_count')->nullable();
            $table->unsignedTinyInteger('teams_per_group')->nullable();
            $table->unsignedTinyInteger('qualified_per_group')->nullable();
            $table->enum('status', ['draft', 'active', 'completed'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
