<?php

namespace App\Http\Controllers;

use App\Http\Requests\Prediction\StorePredictionRequest;
use App\Models\Game;
use App\Models\PointBooster;
use App\Models\Prediction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PredictionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $tournaments = $user->joinedTournaments()
            ->orderByDesc('tournaments.created_at')
            ->get();

        $tournamentsData = $tournaments->map(function ($tournament) use ($user) {
            $tournament->load(['tournamentGroups', 'members']);

            $matches = $tournament->matches()
                ->with(['homeTeam', 'awayTeam', 'tournamentGroup'])
                ->orderBy('scheduled_at')
                ->get();

            $memberIds = $tournament->members->pluck('id');

            if ($tournament->predictions_open) {
                $allPredictions = Prediction::whereIn('match_id', $matches->pluck('id'))
                    ->where('user_id', $user->id)
                    ->get()
                    ->groupBy('match_id');
            } else {
                $allPredictions = Prediction::whereIn('match_id', $matches->pluck('id'))
                    ->whereIn('user_id', $memberIds)
                    ->get()
                    ->groupBy('match_id');
            }

            $matchesByGroup = [];
            $knockoutMatches = [];

            foreach ($matches as $match) {
                if ($match->round === 'group' && $match->tournamentGroup) {
                    $groupName = $match->tournamentGroup->name;
                    if (!isset($matchesByGroup[$groupName])) {
                        $matchesByGroup[$groupName] = [];
                    }
                    $matchesByGroup[$groupName][] = $match;
                } else {
                    $knockoutMatches[] = $match;
                }
            }

            ksort($matchesByGroup);

            $boosters = \App\Models\PointBooster::where('tournament_id', $tournament->id)
                ->get(['user_id', 'match_id']);

            $allBoosters = [];
            foreach ($boosters as $booster) {
                $allBoosters[$booster->match_id][$booster->user_id] = true;
            }

            return [
                'tournament'     => $tournament,
                'matchesByGroup' => $matchesByGroup,
                'knockoutMatches' => $knockoutMatches,
                'allPredictions' => $allPredictions,
                'allBoosters'    => $allBoosters,
                'members'        => $tournament->members,
                'predictionsOpen' => $tournament->predictions_open,
            ];
        });

        return Inertia::render('Predictions/Index', [
            'tournamentsData' => $tournamentsData,
        ]);
    }

    public function show(int $match): Response
    {
        $game = Game::with(['homeTeam', 'awayTeam', 'tournament'])->findOrFail($match);
        $user = auth()->user();

        if (!$game->tournament->isMember($user)) {
            return redirect()->route('tournaments.index')->with('error', 'Vous n\'êtes pas membre de ce tournoi.');
        }

        $prediction = $user
            ->predictions()
            ->where('match_id', $game->id)
            ->first();

        $hasBooster = PointBooster::hasBoosterForMatch($user->id, $game->id);
        $boosterStats = [
            'remaining' => PointBooster::getRemainingBoostersCount($user->id, $game->tournament_id),
            'max' => PointBooster::MAX_BOOSTERS_PER_TOURNAMENT,
        ];

        return Inertia::render('Predictions/Match', [
            'match' => $game,
            'prediction' => $prediction,
            'canPredict' => $game->canPredict(),
            'hasBooster' => $hasBooster,
            'boosterStats' => $boosterStats,
        ]);
    }

    public function store(StorePredictionRequest $request, int $match): RedirectResponse
    {
        $game = Game::findOrFail($match);

        if (!$game->canPredict()) {
            return back()->with('error', 'Les pronostics sont clos pour ce match.');
        }

        $existingPrediction = $request->user()
            ->predictions()
            ->where('match_id', $game->id)
            ->first();

        if ($existingPrediction) {
            $existingPrediction->update($request->validated());
            $message = 'Pronostic mis à jour avec succès.';
        } else {
            $request->user()->predictions()->create([
                'match_id' => $game->id,
                ...$request->validated(),
            ]);
            $message = 'Pronostic enregistré avec succès.';
        }

        return redirect()->route('tournaments.show', [
            'tournament' => $game->tournament_id,
            'tab' => 'matches'
        ])->with('success', $message);
    }

    public function update(StorePredictionRequest $request, Prediction $prediction): RedirectResponse
    {
        if ($prediction->user_id !== auth()->id()) {
            abort(403);
        }

        if (!$prediction->game->canPredict()) {
            return back()->with('error', 'Les pronostics sont clos pour ce match.');
        }

        $prediction->update($request->validated());

        return redirect()->route('tournaments.show', [
            'tournament' => $prediction->game->tournament_id,
            'tab' => 'matches'
        ])->with('success', 'Pronostic mis à jour avec succès.');
    }
}
