<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentWinnerPrediction;
use App\Services\PredictionScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WinnerPredictionController extends Controller
{
    /**
     * Enregistrer ou mettre à jour le pronostic de vainqueur
     */
    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        // Vérifier que les pronostics de vainqueur ne sont pas verrouillés définitivement
        if ($tournament->winner_predictions_locked) {
            return back()->with('error', 'Les pronostics de vainqueur sont définitivement verrouillés.');
        }

        // Vérifier que les pronostics sont ouverts
        if (!$tournament->predictions_open) {
            return back()->with('error', 'Les pronostics sont fermés.');
        }

        $validated = $request->validate([
            'first_choice_team_id' => ['required', 'exists:teams,id'],
            'second_choice_team_id' => ['required', 'exists:teams,id', 'different:first_choice_team_id'],
            'third_choice_team_id' => ['required', 'exists:teams,id', 'different:first_choice_team_id', 'different:second_choice_team_id'],
        ], [
            'first_choice_team_id.required' => 'Le 1er choix est obligatoire.',
            'second_choice_team_id.required' => 'Le 2ème choix est obligatoire.',
            'third_choice_team_id.required' => 'Le 3ème choix est obligatoire.',
            'second_choice_team_id.different' => 'Le 2ème choix doit être différent du 1er.',
            'third_choice_team_id.different' => 'Le 3ème choix doit être différent des autres.',
        ]);

        TournamentWinnerPrediction::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'tournament_id' => $tournament->id,
            ],
            $validated
        );

        return back()->with('success', 'Pronostic vainqueur enregistré !');
    }

    /**
     * Définir le vainqueur du tournoi (admin seulement)
     */
    public function setWinner(Request $request, Tournament $tournament, PredictionScoringService $scoringService): RedirectResponse
    {
        // Vérifier que l'utilisateur est le créateur du tournoi
        if (!$tournament->isAdmin(auth()->user())) {
            abort(403);
        }

        $validated = $request->validate([
            'winner_team_id' => ['required', 'exists:teams,id'],
        ], [
            'winner_team_id.required' => 'Le vainqueur est obligatoire.',
        ]);

        $tournament->update($validated);

        // Calculer les points pour tous les pronostics de vainqueur
        $this->calculateWinnerPoints($tournament);

        // Mettre à jour les classements des groupes de pronostics
        $scoringService->updateGroupRankings($tournament->id);

        return back()->with('success', 'Vainqueur enregistré et points calculés !');
    }

    /**
     * Calculer les points bonus pour les pronostics de vainqueur
     */
    private function calculateWinnerPoints(Tournament $tournament): void
    {
        $winnerPredictions = $tournament->winnerPredictions()->get();

        foreach ($winnerPredictions as $prediction) {
            $points = $prediction->calculatePoints($tournament->winner_team_id);
            $prediction->update(['points_earned' => $points]);
        }
    }
}
