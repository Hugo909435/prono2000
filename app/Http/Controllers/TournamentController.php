<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tournament\StoreTournamentRequest;
use App\Http\Requests\Tournament\UpdateTournamentRequest;
use App\Models\Game;
use App\Models\Prediction;
use App\Models\Tournament;
use App\Models\TournamentWinnerPrediction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TournamentController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $first = auth()->user()->joinedTournaments()
            ->orderByDesc('tournaments.created_at')
            ->first();

        if ($first) {
            return redirect()->route('tournaments.show', $first);
        }

        return Inertia::render('Tournaments/Index', [
            'tournaments' => collect(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tournaments/Create');
    }

    public function store(StoreTournamentRequest $request): RedirectResponse
    {
        $tournament = $request->user()->tournaments()->create($request->validated());
        $tournament->addMember($request->user(), 'admin');

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Tournoi créé avec succès.');
    }

    public function show(Tournament $tournament): Response
    {
        $user = auth()->user();

        $tournament->load([
            'creator',
            'teams',
            'tournamentGroups.teams',
            'matches.homeTeam',
            'matches.awayTeam',
            'matches.tournamentGroup',
            'members',
            'winnerTeam',
        ]);

        $tournament->loadCount('members');

        $isMember = $tournament->isMember($user);
        $isAdmin = $tournament->isAdmin($user);

        // Pronostic vainqueur de l'utilisateur connecté
        $userWinnerPrediction = $user->winnerPredictions()
            ->where('tournament_id', $tournament->id)
            ->with(['firstChoiceTeam', 'secondChoiceTeam', 'thirdChoiceTeam'])
            ->first();

        // Pronostics de l'utilisateur pour tous les matchs du tournoi
        $userPredictions = $user->predictions()
            ->whereIn('match_id', $tournament->matches->pluck('id'))
            ->get()
            ->keyBy('match_id');

        // Pronostics vainqueur des membres (si verrouillés)
        $membersWinnerPredictions = [];
        if ($tournament->winner_predictions_locked && $isMember) {
            $memberIds = $tournament->members->pluck('id');
            $membersWinnerPredictions = TournamentWinnerPrediction::where('tournament_id', $tournament->id)
                ->whereIn('user_id', $memberIds)
                ->with(['firstChoiceTeam', 'secondChoiceTeam', 'thirdChoiceTeam'])
                ->get()
                ->keyBy('user_id');
        }

        $myTournaments = auth()->user()->joinedTournaments()
            ->select('tournaments.id', 'tournaments.name')
            ->orderByDesc('tournaments.created_at')
            ->get();

        return Inertia::render('Tournaments/Show', [
            'tournament' => $tournament,
            'isOwner' => auth()->id() === $tournament->user_id,
            'isMember' => $isMember,
            'isAdmin' => $isAdmin,
            'predefinedTeams' => config('teams'),
            'userWinnerPrediction' => $userWinnerPrediction,
            'userPredictions' => $userPredictions,
            'membersWinnerPredictions' => $membersWinnerPredictions,
            'myTournaments' => $myTournaments,
        ]);
    }

    public function edit(Tournament $tournament): Response
    {
        if (!$tournament->isAdmin(auth()->user())) {
            abort(403);
        }

        return Inertia::render('Tournaments/Edit', [
            'tournament' => $tournament,
        ]);
    }

    public function update(UpdateTournamentRequest $request, Tournament $tournament): RedirectResponse
    {
        $tournament->update($request->validated());

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Tournoi mis à jour avec succès.');
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        if (!$tournament->isAdmin(auth()->user())) {
            abort(403);
        }

        $tournament->delete();

        return redirect()
            ->route('tournaments.index')
            ->with('success', 'Tournoi supprimé avec succès.');
    }

    public function bracket(Tournament $tournament): Response
    {
        $tournament->load([
            'teams',
            'matches' => function ($query) {
                $query->where('round', '!=', 'group')
                    ->orderBy('round')
                    ->orderBy('match_number');
            },
            'matches.homeTeam',
            'matches.awayTeam',
        ]);

        return Inertia::render('Tournaments/Bracket', [
            'tournament' => $tournament,
        ]);
    }

    public function activate(Tournament $tournament): RedirectResponse
    {
        if (!$tournament->isAdmin(auth()->user())) {
            abort(403);
        }

        if ($tournament->teams()->count() < 2) {
            return back()->with('error', 'Le tournoi doit avoir au moins 2 équipes.');
        }

        $tournament->update(['status' => 'active']);

        return back()->with('success', 'Tournoi activé avec succès.');
    }

    public function togglePredictions(Tournament $tournament): RedirectResponse
    {
        if (!$tournament->isAdmin(auth()->user())) {
            abort(403);
        }

        if ($tournament->winner_predictions_locked) {
            return back()->with('error', 'Les pronostics sont définitivement verrouillés et ne peuvent plus être réouverts.');
        }

        $wasOpen = $tournament->predictions_open;
        $tournament->update(['predictions_open' => !$tournament->predictions_open]);

        // Si on ferme les pronostics, verrouiller définitivement les prédictions de vainqueur
        if ($wasOpen && !$tournament->predictions_open) {
            $tournament->update(['winner_predictions_locked' => true]);
        }

        $message = $tournament->predictions_open
            ? 'Pronostics ouverts. Les joueurs peuvent faire leurs pronostics de matchs.'
            : 'Pronostics fermés. Les pronostics de vainqueur sont définitivement verrouillés.';

        return back()->with('success', $message);
    }

    public function join(Request $request): RedirectResponse
    {
        $request->validate(['access_code' => ['required', 'string', 'size:8']]);

        $tournament = Tournament::where('access_code', strtoupper($request->access_code))->first();

        if (!$tournament) {
            return back()->with('error', 'Code invalide.');
        }

        if (!$tournament->isActive()) {
            return back()->with('error', 'Ce tournoi n\'est pas actif.');
        }

        if ($tournament->isMember($request->user())) {
            return redirect()->route('tournaments.show', $tournament)->with('info', 'Vous êtes déjà membre de ce tournoi.');
        }

        $tournament->addMember($request->user());

        return redirect()->route('tournaments.show', $tournament)->with('success', 'Vous avez rejoint le tournoi !');
    }

    public function leave(Tournament $tournament): RedirectResponse
    {
        $user = auth()->user();

        if ($tournament->isAdmin($user) && $tournament->members()->count() === 1) {
            return back()->with('error', 'Vous êtes le seul membre, vous ne pouvez pas quitter.');
        }

        if (auth()->id() === $tournament->user_id) {
            return back()->with('error', 'Le créateur ne peut pas quitter son tournoi.');
        }

        $tournament->removeMember($user);

        return redirect()->route('tournaments.index')->with('success', 'Vous avez quitté le tournoi.');
    }

    public function allWinnerPredictions(Tournament $tournament): Response
    {
        abort_if(!$tournament->isMember(auth()->user()), 403);

        $tournament->load(['teams', 'winnerTeam', 'members']);

        $memberIds = $tournament->members->pluck('id');

        if ($tournament->winner_predictions_locked) {
            $winnerPredictions = TournamentWinnerPrediction::where('tournament_id', $tournament->id)
                ->whereIn('user_id', $memberIds)
                ->with(['firstChoiceTeam', 'secondChoiceTeam', 'thirdChoiceTeam'])
                ->get()
                ->keyBy('user_id');
        } else {
            $winnerPredictions = TournamentWinnerPrediction::where('tournament_id', $tournament->id)
                ->where('user_id', auth()->id())
                ->with(['firstChoiceTeam', 'secondChoiceTeam', 'thirdChoiceTeam'])
                ->get()
                ->keyBy('user_id');
        }

        return Inertia::render('Tournaments/AllWinnerPredictions', [
            'tournament' => $tournament,
            'members' => $tournament->members,
            'winnerPredictions' => $winnerPredictions,
            'predictionsLocked' => $tournament->winner_predictions_locked,
        ]);
    }

    public function allPredictions(Tournament $tournament): Response
    {
        abort_if(!$tournament->isMember(auth()->user()), 403);

        $tournament->load(['tournamentGroups', 'winnerTeam', 'members']);

        $matches = $tournament->matches()
            ->with(['homeTeam', 'awayTeam', 'tournamentGroup'])
            ->orderBy('scheduled_at')
            ->get();

        $memberIds = $tournament->members->pluck('id');

        if ($tournament->predictions_open) {
            $allPredictions = Prediction::whereIn('match_id', $matches->pluck('id'))
                ->where('user_id', auth()->id())
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

        return Inertia::render('Tournaments/AllPredictions', [
            'tournament' => $tournament,
            'matchesByGroup' => $matchesByGroup,
            'knockoutMatches' => $knockoutMatches,
            'allPredictions' => $allPredictions,
            'allBoosters' => $allBoosters,
            'members' => $tournament->members,
            'predictionsOpen' => $tournament->predictions_open,
        ]);
    }
}
