<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TournamentGroupController extends Controller
{
    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $tournament->tournamentGroups()->create($validated);

        return back()->with('success', 'Groupe créé avec succès.');
    }

    public function storeMultiple(Request $request, Tournament $tournament): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $validated = $request->validate([
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['required', 'string', 'max:50'],
        ]);

        $created = 0;
        foreach ($validated['groups'] as $name) {
            $exists = $tournament->tournamentGroups()->where('name', $name)->exists();
            if (!$exists) {
                $tournament->tournamentGroups()->create(['name' => $name]);
                $created++;
            }
        }

        if ($created === 0) {
            return back()->with('info', 'Tous les groupes existent déjà.');
        }

        return back()->with('success', $created . ' groupe(s) créé(s) avec succès.');
    }

    public function destroy(Tournament $tournament, TournamentGroup $group): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $group->delete();

        return back()->with('success', 'Groupe supprimé avec succès.');
    }

    public function addTeam(Request $request, Tournament $tournament, TournamentGroup $group): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $validated = $request->validate([
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['required', 'exists:teams,id'],
        ]);

        $added = 0;
        $skipped = 0;

        foreach ($validated['team_ids'] as $teamId) {
            // Check if team is already in another group
            $existingGroup = $tournament->tournamentGroups()
                ->whereHas('teams', fn($q) => $q->where('teams.id', $teamId))
                ->first();

            if ($existingGroup) {
                $skipped++;
                continue;
            }

            $group->teams()->attach($teamId, [
                'points' => 0,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'goal_difference' => 0,
            ]);
            $added++;
        }

        if ($added === 0) {
            return back()->with('error', 'Toutes les équipes sont déjà assignées à des groupes.');
        }

        $message = $added . ' équipe(s) ajoutée(s) au groupe.';
        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' équipe(s) ignorée(s) car déjà assignée(s).';
        }

        return back()->with('success', $message);
    }

    public function removeTeam(Tournament $tournament, TournamentGroup $group, int $teamId): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $group->teams()->detach($teamId);

        return back()->with('success', 'Équipe retirée du groupe.');
    }

    public function generateMatches(Request $request, Tournament $tournament): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $groups = $tournament->tournamentGroups()->with('teams')->get();

        if ($groups->isEmpty()) {
            return back()->with('error', 'Aucun groupe défini.');
        }

        $validated = $request->validate([
            'deadline_offset_hours' => ['nullable', 'integer', 'min:0'],
        ]);

        $deadlineOffset = $validated['deadline_offset_hours'] ?? 1;
        $matchesCreated = 0;

        foreach ($groups as $group) {
            $teams = $group->teams;

            if ($teams->count() < 2) {
                continue;
            }

            // Generate round-robin matches (each team plays every other team once)
            for ($i = 0; $i < $teams->count(); $i++) {
                for ($j = $i + 1; $j < $teams->count(); $j++) {
                    // Check if match already exists
                    $existingMatch = $tournament->matches()
                        ->where('tournament_group_id', $group->id)
                        ->where(function ($q) use ($teams, $i, $j) {
                            $q->where(function ($q2) use ($teams, $i, $j) {
                                $q2->where('home_team_id', $teams[$i]->id)
                                   ->where('away_team_id', $teams[$j]->id);
                            })->orWhere(function ($q2) use ($teams, $i, $j) {
                                $q2->where('home_team_id', $teams[$j]->id)
                                   ->where('away_team_id', $teams[$i]->id);
                            });
                        })
                        ->exists();

                    if (!$existingMatch) {
                        $tournament->matches()->create([
                            'tournament_group_id' => $group->id,
                            'home_team_id' => $teams[$i]->id,
                            'away_team_id' => $teams[$j]->id,
                            'round' => 'group',
                            'status' => 'scheduled',
                        ]);
                        $matchesCreated++;
                    }
                }
            }
        }

        if ($matchesCreated === 0) {
            return back()->with('info', 'Tous les matchs de poule existent déjà.');
        }

        return back()->with('success', $matchesCreated . ' match(s) de poule généré(s) avec succès.');
    }
}
