<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Prediction;
use App\Models\PredictionBlock;
use App\Models\PredictionSwap;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentLastPlacePrediction;
use App\Models\TournamentLoserPrediction;
use App\Models\TournamentTopScorerPrediction;
use App\Models\TournamentWinnerPrediction;

class PredictionScoringService
{
    public const POINTS_EXACT_SCORE = 6;
    public const POINTS_CORRECT_RESULT = 2;
    public const POINTS_WRONG = 0;

    public function calculatePoints(Prediction $prediction, Game $match): array
    {
        if (!$match->isCompleted()) {
            return ['points' => null, 'result_type' => null];
        }

        $predictedHome = $prediction->home_score;
        $predictedAway = $prediction->away_score;
        $actualHome = $match->home_score;
        $actualAway = $match->away_score;

        if ($predictedHome === $actualHome && $predictedAway === $actualAway) {
            return [
                'points' => self::POINTS_EXACT_SCORE,
                'result_type' => 'exact',
            ];
        }

        $predictedResult = $this->getResult($predictedHome, $predictedAway);
        $actualResult = $this->getResult($actualHome, $actualAway);

        if ($predictedResult === $actualResult) {
            return [
                'points' => self::POINTS_CORRECT_RESULT,
                'result_type' => 'correct_winner',
            ];
        }

        return [
            'points' => self::POINTS_WRONG,
            'result_type' => 'wrong',
        ];
    }

    private function getResult(int $home, int $away): string
    {
        if ($home > $away) {
            return 'home';
        }
        if ($away > $home) {
            return 'away';
        }

        return 'draw';
    }

    public function processMatchResults(Game $match): void
    {
        $predictions = $match->predictions()->get();

        foreach ($predictions as $prediction) {
            $result = $this->calculatePoints($prediction, $match);
            $points = $result['points'];

            if ($points !== null && $points > 0) {
                // Prono doublé (phase de groupes, max 3 par tournoi)
                if ($prediction->is_doubled) {
                    $points *= 2;
                }
            }

            // Prono bloqué : force les points à 0 quoi qu'il arrive
            if (PredictionBlock::isBlocked($prediction->user_id, $match->id)) {
                $points = 0;
            }

            $prediction->update([
                'points_earned' => $points,
                'result_type' => $result['result_type'],
            ]);
        }

        $this->updateGroupRankings($match->tournament_id);
    }

    public function updateGroupRankings(int $tournamentId): void
    {
        $tournament = Tournament::find($tournamentId);
        if (!$tournament) {
            return;
        }

        $this->updateTournamentMemberStats($tournament);
    }

    private function updateTournamentMemberStats(Tournament $tournament): void
    {
        $matchIds = Game::where('tournament_id', $tournament->id)
            ->where('status', 'completed')
            ->pluck('id');

        foreach ($tournament->members as $member) {
            $stats = Prediction::where('user_id', $member->id)
                ->whereIn('match_id', $matchIds)
                ->selectRaw('
                    COALESCE(SUM(points_earned), 0) as total_points,
                    SUM(CASE WHEN result_type = "exact" THEN 1 ELSE 0 END) as exact_scores,
                    SUM(CASE WHEN result_type = "correct_winner" THEN 1 ELSE 0 END) as correct_results,
                    SUM(CASE WHEN result_type = "wrong" THEN 1 ELSE 0 END) as wrong_predictions
                ')
                ->first();

            // Prono échangé : ajustement des points ET des compteurs (exact/bon/raté)
            // selon les échanges actifs. Le ×2 appartient au joueur : s'il avait doublé
            // le prono donné, son ×2 le suit sur le prono pris (il ne va jamais à
            // l'adversaire). Les compteurs suivent le prono réellement détenu après échange.
            $swap = $this->swapDeltas($member->id, $tournament->id, $matchIds);

            $totalPoints = max(0, (int) ($stats->total_points ?? 0) + $swap['points']);
            $exactScores      = max(0, (int) ($stats->exact_scores ?? 0) + $swap['exact']);
            $correctResults   = max(0, (int) ($stats->correct_results ?? 0) + $swap['correct']);
            $wrongPredictions = max(0, (int) ($stats->wrong_predictions ?? 0) + $swap['wrong']);

            $winnerPredictionPoints = TournamentWinnerPrediction::where('user_id', $member->id)
                ->where('tournament_id', $tournament->id)
                ->value('points_earned') ?? 0;

            $loserPredictionPoints = TournamentLoserPrediction::where('user_id', $member->id)
                ->where('tournament_id', $tournament->id)
                ->value('points_earned') ?? 0;

            $topScorerPredictionPoints = TournamentTopScorerPrediction::where('user_id', $member->id)
                ->where('tournament_id', $tournament->id)
                ->value('points_earned') ?? 0;

            $lastPlacePredictionPoints = TournamentLastPlacePrediction::where('user_id', $member->id)
                ->where('tournament_id', $tournament->id)
                ->value('points_earned') ?? 0;

            $totalPoints += $winnerPredictionPoints + $loserPredictionPoints + $topScorerPredictionPoints + $lastPlacePredictionPoints;

            $tournament->members()->updateExistingPivot($member->id, [
                'total_points' => $totalPoints,
                'exact_scores' => $exactScores,
                'correct_results' => $correctResults,
                'wrong_predictions' => $wrongPredictions,
            ]);
        }
    }

    /**
     * Reconstruit l'historique des snapshots (points + classement) jour par jour
     * pour un tournoi, à partir des matchs déjà terminés.
     *
     * - Date de référence d'un match : scheduled_at, sinon updated_at (saisie du résultat).
     * - N'inclut PAS les points bonus spéciaux (vainqueur/buteur/…) car ils n'ont pas
     *   de date : ils n'apparaîtront que via les snapshots quotidiens normaux.
     * - Utilise firstOrCreate : ne réécrit JAMAIS un snapshot existant (données préservées).
     *
     * @return int Nombre de lignes de snapshot créées.
     */
    public function buildHistory(Tournament $tournament): int
    {
        $members = $tournament->members;
        if ($members->isEmpty()) {
            return 0;
        }

        $matches = Game::where('tournament_id', $tournament->id)
            ->where('status', 'completed')
            ->get(['id', 'scheduled_at', 'updated_at']);

        if ($matches->isEmpty()) {
            return 0;
        }

        // football_day de référence pour chaque match terminé
        $matchDay = [];
        foreach ($matches as $m) {
            $dt = $m->scheduled_at ?? $m->updated_at;
            if (!$dt) {
                continue;
            }
            $matchDay[$m->id] = \App\Models\RankingSnapshot::footballDayFor($dt->copy());
        }

        if (empty($matchDay)) {
            return 0;
        }

        $days = collect($matchDay)->unique()->sort()->values();
        $created = 0;

        foreach ($days as $day) {
            // Matchs terminés dont la journée foot est <= au jour courant
            $matchIdsUpTo = collect($matchDay)
                ->filter(fn ($d) => $d <= $day)
                ->keys()
                ->values();

            $rows = [];
            foreach ($members as $member) {
                $stats = Prediction::where('user_id', $member->id)
                    ->whereIn('match_id', $matchIdsUpTo)
                    ->selectRaw('
                        COALESCE(SUM(points_earned), 0) as total_points,
                        SUM(CASE WHEN result_type = "exact" THEN 1 ELSE 0 END) as exact_scores,
                        SUM(CASE WHEN result_type = "correct_winner" THEN 1 ELSE 0 END) as correct_results
                    ')
                    ->first();

                $swap = $this->swapDeltas($member->id, $tournament->id, $matchIdsUpTo);

                $rows[] = [
                    'user_id'         => $member->id,
                    'total_points'    => max(0, (int) ($stats->total_points ?? 0) + $swap['points']),
                    'exact_scores'    => max(0, (int) ($stats->exact_scores ?? 0) + $swap['exact']),
                    'correct_results' => max(0, (int) ($stats->correct_results ?? 0) + $swap['correct']),
                ];
            }

            // Classement du jour : tri par points décroissants
            usort($rows, fn ($a, $b) => $b['total_points'] <=> $a['total_points']);

            foreach ($rows as $i => $row) {
                $snap = \App\Models\RankingSnapshot::firstOrCreate(
                    [
                        'tournament_id' => $tournament->id,
                        'user_id'       => $row['user_id'],
                        'football_day'  => $day,
                    ],
                    [
                        'position'        => $i + 1,
                        'total_points'    => $row['total_points'],
                        'exact_scores'    => $row['exact_scores'],
                        'correct_results' => $row['correct_results'],
                    ]
                );

                if ($snap->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Calcule l'impact des échanges actifs sur le total de points ET les compteurs
     * (exact / bon résultat / raté) d'un membre, pour les matchs déjà terminés.
     *
     * Règles (validées avec le produit) :
     *  - Le doublement (×2) appartient au JOUEUR, pas au prono. S'il avait doublé le
     *    prono qu'il DONNE, son ×2 le suit et se réapplique sur le prono qu'il PREND.
     *    Exemple : il donne un prono doublé, il prend le score exact de l'autre →
     *    il marque 6 × 2 = 12. L'autre joueur, lui, reçoit le prono SANS ×2 (sauf
     *    s'il a activé le sien) : le ×2 ne voyage jamais vers l'adversaire.
     *  - Le prono donné quitte entièrement le joueur (base + son éventuel ×2).
     *  - Les compteurs suivent le prono réellement détenu après échange : on retire
     *    le résultat du prono donné et on ajoute celui du prono pris.
     *
     * @param  int|\Illuminate\Support\Collection  $completedMatchIds
     * @return array{points:int, exact:int, correct:int, wrong:int}
     */
    private function swapDeltas(int $userId, int $tournamentId, $completedMatchIds): array
    {
        $delta = ['points' => 0, 'exact' => 0, 'correct' => 0, 'wrong' => 0];

        $trade = function (?Prediction $given, ?Prediction $taken) use (&$delta): void {
            // Le ×2 appartient au joueur : s'il l'avait activé sur le prono qu'il donne,
            // il le réapplique sur le prono qu'il prend.
            $doubled = $given && $given->is_doubled;

            // Le prono donné quitte entièrement le joueur : on retire TOUT (base + ×2).
            if ($given) {
                $delta['points'] -= (int) ($given->points_earned ?? 0);
                $this->bumpCounter($delta, $given->result_type, -1);
            }
            // On prend la BASE du prono pris (jamais le ×2 de l'autre joueur) et on y
            // réapplique NOTRE ×2 si on l'avait activé sur le prono donné.
            if ($taken) {
                $base = $this->basePoints($taken);
                $delta['points'] += $doubled ? $base * 2 : $base;
                $this->bumpCounter($delta, $taken->result_type, +1);
            }
        };

        // Échanges initiés : on donne initiator_match, on prend le prono de la cible (target_match)
        $outgoingSwaps = PredictionSwap::where('initiator_user_id', $userId)
            ->where('tournament_id', $tournamentId)
            ->whereIn('initiator_match_id', $completedMatchIds)
            ->whereIn('target_match_id', $completedMatchIds)
            ->get();

        foreach ($outgoingSwaps as $swap) {
            $given = Prediction::where('user_id', $userId)->where('match_id', $swap->initiator_match_id)->first();
            $taken = Prediction::where('user_id', $swap->target_user_id)->where('match_id', $swap->target_match_id)->first();
            $trade($given, $taken);
        }

        // Échanges reçus : on donne notre prono (target_match), on prend celui de l'initiateur (initiator_match)
        $incomingSwaps = PredictionSwap::where('target_user_id', $userId)
            ->where('tournament_id', $tournamentId)
            ->whereIn('initiator_match_id', $completedMatchIds)
            ->whereIn('target_match_id', $completedMatchIds)
            ->get();

        foreach ($incomingSwaps as $swap) {
            $given = Prediction::where('user_id', $userId)->where('match_id', $swap->target_match_id)->first();
            $taken = Prediction::where('user_id', $swap->initiator_user_id)->where('match_id', $swap->initiator_match_id)->first();
            $trade($given, $taken);
        }

        return $delta;
    }

    /**
     * Total effectif de points d'un joueur sur un ensemble de matchs, échanges
     * compris (mêmes règles que le classement : seules les bases sont troquées,
     * le ×2 reste au joueur). Un échange n'est pris en compte que si ses DEUX
     * matchs font partie de l'ensemble fourni.
     *
     * @param  array|\Illuminate\Support\Collection  $matchIds
     */
    public function effectiveMatchPoints(int $userId, int $tournamentId, $matchIds): int
    {
        $base = (int) Prediction::where('user_id', $userId)
            ->whereIn('match_id', $matchIds)
            ->sum('points_earned');

        $delta = $this->swapDeltas($userId, $tournamentId, $matchIds);

        return max(0, $base + $delta['points']);
    }

    /**
     * Points de BASE d'un prono : les points gagnés SANS le bonus de doublement.
     * Le doublement est un ×2 exact, donc la base est la moitié des points doublés.
     */
    public function basePoints(Prediction $prediction): int
    {
        $points = (int) ($prediction->points_earned ?? 0);

        return $prediction->is_doubled ? intdiv($points, 2) : $points;
    }

    private function bumpCounter(array &$delta, ?string $resultType, int $sign): void
    {
        if ($resultType === 'exact') {
            $delta['exact'] += $sign;
        } elseif ($resultType === 'correct_winner') {
            $delta['correct'] += $sign;
        } elseif ($resultType === 'wrong') {
            $delta['wrong'] += $sign;
        }
    }

    /**
     * Met à jour les statistiques des équipes dans un groupe de tournoi (poule)
     * après qu'un match de phase de poules est terminé.
     */
    public function updateGroupTeamStats(Game $match): void
    {
        // Ne mettre à jour que pour les matchs de phase de poules
        if ($match->round !== 'group' || !$match->tournament_group_id) {
            return;
        }

        $group = TournamentGroup::find($match->tournament_group_id);
        if (!$group) {
            return;
        }

        // Recalculer les stats pour toutes les équipes du groupe
        $this->recalculateGroupStats($group);
    }

    /**
     * Recalcule toutes les statistiques des équipes d'un groupe
     * basé sur les matchs terminés.
     */
    private function recalculateGroupStats(TournamentGroup $group): void
    {
        // Récupérer tous les matchs terminés de ce groupe
        $completedMatches = Game::where('tournament_group_id', $group->id)
            ->where('status', 'completed')
            ->get();

        // Initialiser les stats pour chaque équipe
        $stats = [];
        foreach ($group->teams as $team) {
            $stats[$team->id] = [
                'points' => 0,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'goal_difference' => 0,
            ];
        }

        // Calculer les stats à partir des matchs terminés
        foreach ($completedMatches as $match) {
            $homeId = $match->home_team_id;
            $awayId = $match->away_team_id;
            $homeScore = $match->home_score;
            $awayScore = $match->away_score;

            if (!isset($stats[$homeId]) || !isset($stats[$awayId])) {
                continue;
            }

            // Matchs joués
            $stats[$homeId]['played']++;
            $stats[$awayId]['played']++;

            // Buts
            $stats[$homeId]['goals_for'] += $homeScore;
            $stats[$homeId]['goals_against'] += $awayScore;
            $stats[$awayId]['goals_for'] += $awayScore;
            $stats[$awayId]['goals_against'] += $homeScore;

            // Résultat et points
            if ($homeScore > $awayScore) {
                // Victoire domicile
                $stats[$homeId]['won']++;
                $stats[$homeId]['points'] += 3;
                $stats[$awayId]['lost']++;
            } elseif ($homeScore < $awayScore) {
                // Victoire extérieur
                $stats[$awayId]['won']++;
                $stats[$awayId]['points'] += 3;
                $stats[$homeId]['lost']++;
            } else {
                // Match nul
                $stats[$homeId]['drawn']++;
                $stats[$awayId]['drawn']++;
                $stats[$homeId]['points'] += 1;
                $stats[$awayId]['points'] += 1;
            }
        }

        // Mettre à jour la table pivot pour chaque équipe
        foreach ($stats as $teamId => $teamStats) {
            $teamStats['goal_difference'] = $teamStats['goals_for'] - $teamStats['goals_against'];
            $group->teams()->updateExistingPivot($teamId, $teamStats);
        }
    }
}
