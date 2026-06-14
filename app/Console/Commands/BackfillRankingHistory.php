<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\PredictionScoringService;
use Illuminate\Console\Command;

class BackfillRankingHistory extends Command
{
    protected $signature = 'rankings:backfill {tournament? : ID du tournoi (sinon tous les tournois actifs)}';

    protected $description = 'Reconstruit l\'historique des snapshots (points + classement) à partir des matchs terminés. Ne réécrit jamais les snapshots existants.';

    public function handle(PredictionScoringService $scoring): int
    {
        $query = Tournament::query();

        if ($id = $this->argument('tournament')) {
            $query->where('id', $id);
        } else {
            $query->where('status', 'active');
        }

        $tournaments = $query->with('members')->get();

        if ($tournaments->isEmpty()) {
            $this->warn('Aucun tournoi à traiter.');

            return self::SUCCESS;
        }

        $total = 0;
        foreach ($tournaments as $tournament) {
            $created = $scoring->buildHistory($tournament);
            $total += $created;
            $this->info("Tournoi #{$tournament->id} ({$tournament->name}) : {$created} relevé(s) ajouté(s).");
        }

        $this->info("Terminé : {$total} relevé(s) créé(s) au total.");

        return self::SUCCESS;
    }
}
