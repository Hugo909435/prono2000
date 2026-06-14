<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table des récaps publiés manuellement par l'admin (récap "figé" en JSON).
        if (!Schema::hasTable('recaps')) {
            Schema::create('recaps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('football_day');
                $table->json('payload');
                // Récap "baseline" : sert de point de départ aux flèches, jamais affiché aux joueurs.
                $table->boolean('is_baseline')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->index(['tournament_id', 'published_at']);
            });
        }

        // Marque les matchs déjà inclus dans un récap (additif, non destructif).
        if (Schema::hasTable('matches') && !Schema::hasColumn('matches', 'recapped_at')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->timestamp('recapped_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('matches') && Schema::hasColumn('matches', 'recapped_at')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->dropColumn('recapped_at');
            });
        }

        Schema::dropIfExists('recaps');
    }
};
