<?php

namespace App\Http\Controllers;

use App\Http\Requests\Prediction\StorePredictionRequest;
use App\Models\Game;
use App\Models\Prediction;
use App\Models\PredictionBlock;
use App\Models\PredictionSwap;
use Illuminate\Http\RedirectResponse;
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

            // Pronos doublés par match/user
            $allDoubles = [];
            foreach ($allPredictions->flatten() as $pred) {
                if ($pred->is_doubled) {
                    $allDoubles[$pred->match_id][$pred->user_id] = true;
                }
            }

            $doubleStats = [
                'used'      => Prediction::getDoubledCount($user->id, $tournament->id),
                'max'       => Prediction::MAX_DOUBLED_PER_TOURNAMENT,
                'remaining' => Prediction::MAX_DOUBLED_PER_TOURNAMENT - Prediction::getDoubledCount($user->id, $tournament->id),
            ];

            $myBlocks = PredictionBlock::where('blocker_user_id', $user->id)
                ->where('tournament_id', $tournament->id)
                ->get()
                ->keyBy('target_user_id')
                ->map(fn($b) => [
                    'id'              => $b->id,
                    'target_user_id'  => $b->target_user_id,
                    'target_match_id' => $b->target_match_id,
                ]);

            $mySwaps = PredictionSwap::where('initiator_user_id', $user->id)
                ->where('tournament_id', $tournament->id)
                ->get()
                ->keyBy('target_user_id')
                ->map(fn($s) => [
                    'id'                 => $s->id,
                    'target_user_id'     => $s->target_user_id,
                    'initiator_match_id' => $s->initiator_match_id,
                    'target_match_id'    => $s->target_match_id,
                ]);

            // Blocs posés par d'autres joueurs (pour désactiver les boutons déjà pris)
            // Clé : "targetUserId_matchId" → true
            $takenBlocks = PredictionBlock::where('tournament_id', $tournament->id)
                ->where('blocker_user_id', '!=', $user->id)
                ->get()
                ->mapWithKeys(fn($b) => ["{$b->target_user_id}_{$b->target_match_id}" => true]);

            // Échanges posés par d'autres joueurs
            $takenSwaps = PredictionSwap::where('tournament_id', $tournament->id)
                ->where('initiator_user_id', '!=', $user->id)
                ->get()
                ->mapWithKeys(fn($s) => ["{$s->target_user_id}_{$s->initiator_match_id}" => true]);

            // Tous les échanges actifs du tournoi (pour affichage visuel dans la grille)
            $allSwaps = PredictionSwap::where('tournament_id', $tournament->id)
                ->get()
                ->map(fn($s) => [
                    'initiator_user_id'  => $s->initiator_user_id,
                    'target_user_id'     => $s->target_user_id,
                    'initiator_match_id' => $s->initiator_match_id,
                    'target_match_id'    => $s->target_match_id,
                ])
                ->values();

            // Tous les blocs actifs du tournoi (pour affichage "bloqué par X")
            $allBlocks = PredictionBlock::where('tournament_id', $tournament->id)
                ->get()
                ->map(fn($b) => [
                    'blocker_user_id' => $b->blocker_user_id,
                    'target_user_id'  => $b->target_user_id,
                    'target_match_id' => $b->target_match_id,
                ])
                ->values();

            return [
                'tournament'      => $tournament,
                'matchesByGroup'  => $matchesByGroup,
                'knockoutMatches' => $knockoutMatches,
                'allPredictions'  => $allPredictions,
                'allDoubles'      => $allDoubles,
                'members'         => $tournament->members,
                'predictionsOpen' => $tournament->predictions_open,
                'doubleStats'     => $doubleStats,
                'myBlocks'        => $myBlocks,
                'mySwaps'         => $mySwaps,
                'takenBlocks'     => $takenBlocks,
                'takenSwaps'      => $takenSwaps,
                'allSwaps'        => $allSwaps,
                'allBlocks'       => $allBlocks,
                'opponents'       => $tournament->members->filter(fn($m) => $m->id !== $user->id)->values(),
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

        $hasDouble = $prediction ? (bool) $prediction->is_doubled : false;
        $doubleStats = [
            'used' => Prediction::getDoubledCount($user->id, $game->tournament_id),
            'max' => Prediction::MAX_DOUBLED_PER_TOURNAMENT,
            'remaining' => Prediction::MAX_DOUBLED_PER_TOURNAMENT - Prediction::getDoubledCount($user->id, $game->tournament_id),
        ];

        $canManageScore = $game->tournament->isAdmin($user) || $user->is_admin;

        return Inertia::render('Predictions/Match', [
            'match' => $game,
            'prediction' => $prediction,
            'canPredict' => $game->canPredict(),
            'hasDouble' => $hasDouble,
            'doubleStats' => $doubleStats,
            'canManageScore' => $canManageScore,
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
