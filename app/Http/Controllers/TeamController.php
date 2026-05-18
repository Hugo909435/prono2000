<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Tournament $tournament): Response
    {
        $teams = $tournament->teams()->orderBy('name')->get();
        $predefinedTeams = config('teams');

        return Inertia::render('Tournaments/Teams/Index', [
            'tournament' => $tournament,
            'teams' => $teams,
            'predefinedTeams' => $predefinedTeams,
        ]);
    }

    public function create(Tournament $tournament): Response
    {
        $predefinedTeams = config('teams');

        return Inertia::render('Tournaments/Teams/Create', [
            'tournament' => $tournament,
            'predefinedTeams' => $predefinedTeams,
        ]);
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:5'],
            'flag' => ['nullable', 'string', 'max:500'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'seed' => ['nullable', 'integer', 'min:1'],
        ], [
            'name.required' => 'Le nom de l\'équipe est obligatoire.',
        ]);

        $tournament->teams()->create($validated);

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Équipe ajoutée avec succès.');
    }

    public function edit(Tournament $tournament, Team $team): Response
    {
        return Inertia::render('Tournaments/Teams/Edit', [
            'tournament' => $tournament,
            'team' => $team,
        ]);
    }

    public function update(Request $request, Tournament $tournament, Team $team): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:5'],
            'flag' => ['nullable', 'string', 'max:500'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'seed' => ['nullable', 'integer', 'min:1'],
        ]);

        $team->update($validated);

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Équipe mise à jour avec succès.');
    }

    public function destroy(Tournament $tournament, Team $team): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $team->delete();

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Équipe supprimée avec succès.');
    }

    public function import(Request $request, Tournament $tournament): RedirectResponse
    {
        abort_if(!$tournament->isAdmin(auth()->user()), 403);

        $validated = $request->validate([
            'teams' => ['required', 'array', 'min:1'],
            'teams.*.name' => ['required', 'string', 'max:255'],
            'teams.*.short_name' => ['nullable', 'string', 'max:5'],
            'teams.*.flag' => ['nullable', 'string', 'max:500'],
        ]);

        $existingTeams = $tournament->teams()->pluck('name')->toArray();
        $imported = 0;

        foreach ($validated['teams'] as $teamData) {
            if (!in_array($teamData['name'], $existingTeams)) {
                $tournament->teams()->create($teamData);
                $imported++;
            }
        }

        if ($imported === 0) {
            return redirect()
                ->route('tournaments.show', $tournament)
                ->with('info', 'Toutes les équipes sélectionnées sont déjà dans le tournoi.');
        }

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', $imported . ' équipe(s) importée(s) avec succès.');
    }
}
