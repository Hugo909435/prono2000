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

            $totalPoints = (int) ($stats->total_points ?? 0);

            // Prono échangé : ajustement des points selon les échanges actifs
            $totalPoints = $this->applySwapsToTotal($member->id, $tournament->id, $totalPoints, $matchIds);

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
                'exact_scores' => $stats->exact_scores ?? 0,
                'correct_results' => $stats->correct_results ?? 0,
                'wrong_predictions' => $stats->wrong_predictions ?? 0,
            ]);
        }
    }

    private function applySwapsToTotal(int $userId, int $tournamentId, int $currentTotal, $completedMatchIds): int
    {
        // Échanges où l'utilisateur est l'initiateur : il donne son prono et prend celui de l'adversaire
        $outgoingSwaps = PredictionSwap::where('initiator_user_id', $userId)
            ->where('tournament_id', $tournamentId)
            ->whereIn('initiator_match_id', $completedMatchIds)
            ->whereIn('target_match_id', $completedMatchIds)
            ->get();

        foreach ($outgoingSwaps as $swap) {
            $ownPoints = Prediction::where('user_id', $userId)
                ->where('match_id', $swap->initiator_match_id)
                ->value('points_earned') ?? 0;

            $opponentPoints = Prediction::where('user_id', $swap->target_user_id)
                ->where('match_id', $swap->target_match_id)
                ->value('points_earned') ?? 0;

            $currentTotal -= $ownPoints;
            $currentTotal += $opponentPoints;
        }

        // Échanges où l'utilisateur est la cible : l'adversaire a pris son prono
        $incomingSwaps = PredictionSwap::where('target_user_id', $userId)
            ->where('tournament_id', $tournamentId)
            ->whereIn('initiator_match_id', $completedMatchIds)
            ->whereIn('target_match_id', $completedMatchIds)
            ->get();

        foreach ($incomingSwaps as $swap) {
            $ownPoints = Prediction::where('user_id', $userId)
                ->where('match_id', $swap->target_match_id)
                ->value('points_earned') ?? 0;

            $initiatorPoints = Prediction::where('user_id', $swap->initiator_user_id)
                ->where('match_id', $swap->initiator_match_id)
                ->value('points_earned') ?? 0;

            $currentTotal -= $ownPoints;
            $currentTotal += $initiatorPoints;
        }

        return max(0, $currentTotal);
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
