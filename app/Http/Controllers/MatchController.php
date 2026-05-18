<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Tournament;
use App\Services\PredictionScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MatchController extends Controller
{
    public function index(Tournament $tournament): Response
    {
        $matches = $tournament->matches()
            ->with(['homeTeam', 'awayTeam', 'tournamentGroup'])
            ->orderBy('scheduled_at')
            ->orderBy('round')
            ->get();

        return Inertia::render('Tournaments/Matches/Index', [
            'tournament' => $tournament,
            'matches' => $matches,
        ]);
    }

    public function create(Tournament $tournament): Response
    {
        $teams = $tournament->teams()->orderBy('name')->get();
        $groups = $tournament->tournamentGroups()->get();

        return Inertia::render('Tournaments/Matches/Create', [
            'tournament' => $tournament,
            'teams' => $teams,
            'groups' => $groups,
        ]);
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $validated = $request->validate([
            'home_team_id' => ['nullable', 'exists:teams,id'],
            'away_team_id' => ['nullable', 'exists:teams,id'],
            'tournament_group_id' => ['nullable', 'exists:tournament_groups,id'],
            'round' => ['required', 'string'],
            'match_number' => ['nullable', 'integer', 'min:1'],
            'scheduled_at' => ['nullable', 'date'],
            'deadline_at' => ['nullable', 'date'],
            'placeholder_home' => ['nullable', 'string', 'max:255'],
            'placeholder_away' => ['nullable', 'string', 'max:255'],
        ], [
            'round.required' => 'Le tour est obligatoire.',
        ]);

        $tournament->matches()->create($validated);

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Match créé avec succès.');
    }

    public function show(Tournament $tournament, int $match): Response
    {
        $game = Game::with(['homeTeam', 'awayTeam', 'tournamentGroup', 'predictions.user'])->findOrFail($match);

        return Inertia::render('Tournaments/Matches/Show', [
            'tournament' => $tournament,
            'match' => $game,
        ]);
    }

    public function edit(Tournament $tournament, int $match): Response
    {
        $game = Game::findOrFail($match);
        $teams = $tournament->teams()->orderBy('name')->get();
        $groups = $tournament->tournamentGroups()->get();

        return Inertia::render('Tournaments/Matches/Edit', [
            'tournament' => $tournament,
            'match' => $game,
            'teams' => $teams,
            'groups' => $groups,
        ]);
    }

    public function update(Request $request, Tournament $tournament, int $match): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $game = Game::findOrFail($match);

        $validated = $request->validate([
            'home_team_id' => ['nullable', 'exists:teams,id'],
            'away_team_id' => ['nullable', 'exists:teams,id'],
            'tournament_group_id' => ['nullable', 'exists:tournament_groups,id'],
            'round' => ['required', 'string'],
            'match_number' => ['nullable', 'integer', 'min:1'],
            'scheduled_at' => ['nullable', 'date'],
            'deadline_at' => ['nullable', 'date'],
            'placeholder_home' => ['nullable', 'string', 'max:255'],
            'placeholder_away' => ['nullable', 'string', 'max:255'],
        ]);

        $game->update($validated);

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Match mis à jour avec succès.');
    }

    public function destroy(Tournament $tournament, int $match): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $game = Game::findOrFail($match);
        $game->delete();

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Match supprimé avec succès.');
    }

    public function updateResult(Request $request, Tournament $tournament, int $match, PredictionScoringService $scoringService): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $game = Game::findOrFail($match);
        $validated = $request->validate([
            'home_score' => ['required', 'integer', 'min:0', 'max:99'],
            'away_score' => ['required', 'integer', 'min:0', 'max:99'],
            'home_score_penalties' => ['nullable', 'integer', 'min:0', 'max:99'],
            'away_score_penalties' => ['nullable', 'integer', 'min:0', 'max:99'],
        ], [
            'home_score.required' => 'Le score domicile est obligatoire.',
            'away_score.required' => 'Le score extérieur est obligatoire.',
        ]);

        $game->update([
            ...$validated,
            'status' => 'completed',
        ]);

        // Calculer les points des pronostics et mettre à jour le classement des parieurs
        $scoringService->processMatchResults($game);

        // Mettre à jour le classement des équipes dans la poule (si match de phase de groupes)
        $scoringService->updateGroupTeamStats($game);

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Résultat enregistré et classements mis à jour.');
    }

    public function generate(Request $request, Tournament $tournament): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $validated = $request->validate([
            'round' => ['required', 'string'],
        ]);

        $teams = $tournament->teams()->get();
        $round = $validated['round'];

        if ($round === 'group' && $tournament->hasGroups()) {
            foreach ($tournament->tournamentGroups as $group) {
                $groupTeams = $group->teams;
                $matchNumber = 1;

                for ($i = 0; $i < count($groupTeams); $i++) {
                    for ($j = $i + 1; $j < count($groupTeams); $j++) {
                        $tournament->matches()->create([
                            'tournament_group_id' => $group->id,
                            'home_team_id' => $groupTeams[$i]->id,
                            'away_team_id' => $groupTeams[$j]->id,
                            'round' => 'group',
                            'match_number' => $matchNumber++,
                        ]);
                    }
                }
            }
        }

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Matchs générés avec succès.');
    }
}
