<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentLastPlacePrediction;
use App\Models\TournamentLoserPrediction;
use App\Models\TournamentTopScorerPrediction;
use App\Services\PredictionScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BonusPredictionController extends Controller
{
    public function storeLoser(Request $request, Tournament $tournament): RedirectResponse
    {
        if ($tournament->winner_predictions_locked) {
            return back()->with('error', 'Les pronostics bonus sont verrouillés.');
        }

        if (!$tournament->predictions_open) {
            return back()->with('error', 'Les pronostics sont fermés.');
        }

        $validated = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
        ], [
            'team_id.required' => "L'équipe est obligatoire.",
        ]);

        TournamentLoserPrediction::updateOrCreate(
            ['user_id' => auth()->id(), 'tournament_id' => $tournament->id],
            $validated
        );

        return back()->with('success', 'Pronostic "Gros Loser" enregistré !');
    }

    public function storeTopScorer(Request $request, Tournament $tournament): RedirectResponse
    {
        if ($tournament->winner_predictions_locked) {
            return back()->with('error', 'Les pronostics bonus sont verrouillés.');
        }

        if (!$tournament->predictions_open) {
            return back()->with('error', 'Les pronostics sont fermés.');
        }

        $validated = $request->validate([
            'player_name' => ['required', 'string', 'max:100'],
        ], [
            'player_name.required' => 'Le nom du joueur est obligatoire.',
        ]);

        TournamentTopScorerPrediction::updateOrCreate(
            ['user_id' => auth()->id(), 'tournament_id' => $tournament->id],
            $validated
        );

        return back()->with('success', 'Pronostic "Star de la compétition" enregistré !');
    }

    public function setLoser(Request $request, Tournament $tournament, PredictionScoringService $scoringService): RedirectResponse
    {
        $user = auth()->user();
        if (!$tournament->isAdmin($user) && !$user->is_admin) {
            abort(403);
        }

        $validated = $request->validate([
            'loser_team_id' => ['required', 'exists:teams,id'],
        ]);

        $tournament->update($validated);
        $this->calculateLoserPoints($tournament);
        $scoringService->updateGroupRankings($tournament->id);

        return back()->with('success', 'Gros loser enregistré et points calculés !');
    }

    public function setTopScorer(Request $request, Tournament $tournament, PredictionScoringService $scoringService): RedirectResponse
    {
        $user = auth()->user();
        if (!$tournament->isAdmin($user) && !$user->is_admin) {
            abort(403);
        }

        $validated = $request->validate([
            'top_scorer_name' => ['required', 'string', 'max:100'],
        ]);

        $tournament->update($validated);
        $this->calculateTopScorerPoints($tournament);
        $scoringService->updateGroupRankings($tournament->id);

        return back()->with('success', 'Star de la compétition enregistrée et points calculés !');
    }

    public function storeLastPlace(Request $request, Tournament $tournament): RedirectResponse
    {
        if ($tournament->winner_predictions_locked) {
            return back()->with('error', 'Les pronostics bonus sont verrouillés.');
        }

        if (!$tournament->predictions_open) {
            return back()->with('error', 'Les pronostics sont fermés.');
        }

        $memberIds = $tournament->members()->pluck('users.id')->toArray();

        $validated = $request->validate([
            'predicted_user_id' => ['required', 'exists:users,id', 'in:' . implode(',', $memberIds)],
        ], [
            'predicted_user_id.required' => 'Le joueur est obligatoire.',
            'predicted_user_id.in' => 'Ce joueur ne fait pas partie du tournoi.',
        ]);

        TournamentLastPlacePrediction::updateOrCreate(
            ['user_id' => auth()->id(), 'tournament_id' => $tournament->id],
            $validated
        );

        return back()->with('success', 'Pronostic "Vrai Gros Loser" enregistré !');
    }

    public function setLastPlace(Request $request, Tournament $tournament, PredictionScoringService $scoringService): RedirectResponse
    {
        $user = auth()->user();
        if (!$tournament->isAdmin($user) && !$user->is_admin) {
            abort(403);
        }

        $memberIds = $tournament->members()->pluck('users.id')->toArray();

        $validated = $request->validate([
            'last_place_user_id' => ['required', 'exists:users,id', 'in:' . implode(',', $memberIds)],
        ]);

        $tournament->update($validated);
        $this->calculateLastPlacePoints($tournament);
        $scoringService->updateGroupRankings($tournament->id);

        return back()->with('success', 'Vrai Gros Loser enregistré et points calculés !');
    }

    private function calculateLastPlacePoints(Tournament $tournament): void
    {
        foreach ($tournament->lastPlacePredictions()->get() as $prediction) {
            $prediction->update(['points_earned' => $prediction->calculatePoints($tournament->last_place_user_id)]);
        }
    }

    private function calculateLoserPoints(Tournament $tournament): void
    {
        foreach ($tournament->loserPredictions()->get() as $prediction) {
            $prediction->update(['points_earned' => $prediction->calculatePoints($tournament->loser_team_id)]);
        }
    }

    private function calculateTopScorerPoints(Tournament $tournament): void
    {
        foreach ($tournament->topScorerPredictions()->get() as $prediction) {
            $prediction->update(['points_earned' => $prediction->calculatePoints($tournament->top_scorer_name)]);
        }
    }
}
