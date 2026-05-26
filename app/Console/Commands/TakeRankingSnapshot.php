<?php

namespace App\Console\Commands;

use App\Models\RankingSnapshot;
use App\Models\Tournament;
use Illuminate\Console\Command;

class TakeRankingSnapshot extends Command
{
    protected $signature = 'rankings:snapshot';

    protected $description = 'Prend un snapshot du classement de chaque tournoi actif (appelé chaque jour à 12h00)';

    public function handle(): int
    {
        $footballDay = RankingSnapshot::currentFootballDay();

        $this->info("Prise de snapshot pour la journée foot : {$footballDay}");

        $tournaments = Tournament::where('status', 'active')
            ->with(['members'])
            ->get();

        $count = 0;

        foreach ($tournaments as $tournament) {
            // Les membres sont déjà triés par total_points DESC (orderByPivot dans la relation)
            $members = $tournament->members;

            foreach ($members as $position => $member) {
                RankingSnapshot::updateOrCreate(
                    [
                        'tournament_id' => $tournament->id,
                        'user_id'       => $member->id,
                        'football_day'  => $footballDay,
                    ],
                    [
                        'position'       => $position + 1,
                        'total_points'   => $member->pivot->total_points ?? 0,
                        'exact_scores'   => $member->pivot->exact_scores ?? 0,
                        'correct_results'=> $member->pivot->correct_results ?? 0,
                    ]
                );

                $count++;
            }
        }

        $this->info("Snapshot terminé : {$count} entrée(s) enregistrée(s).");

        return self::SUCCESS;
    }
}
