<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tournament\StoreTournamentRequest;
use App\Http\Requests\Tournament\UpdateTournamentRequest;
use App\Models\Game;
use App\Models\Prediction;
use App\Models\PredictionBlock;
use App\Models\PredictionSwap;
use App\Models\RankingSnapshot;
use App\Models\Tournament;
use App\Models\TournamentLastPlacePrediction;
use App\Models\TournamentLoserPrediction;
use App\Models\TournamentTopScorerPrediction;
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
            'loserTeam',
        ]);

        $tournament->loadCount('members');

        $isMember = $tournament->isMember($user);
        $isAdmin = $tournament->isAdmin($user);

        // Pronostic vainqueur de l'utilisateur connecté
        $userWinnerPrediction = $user->winnerPredictions()
            ->where('tournament_id', $tournament->id)
            ->with(['firstChoiceTeam', 'secondChoiceTeam', 'thirdChoiceTeam'])
            ->first();

        $userLoserPrediction = $user->loserPredictions()
            ->where('tournament_id', $tournament->id)
            ->with('team')
            ->first();

        $userTopScorerPrediction = $user->topScorerPredictions()
            ->where('tournament_id', $tournament->id)
            ->first();

        $userLastPlacePrediction = $user->lastPlacePredictions()
            ->where('tournament_id', $tournament->id)
            ->with('predictedUser')
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

        // Statistiques : évolution des points et du classement par joueur
        // (données issues des snapshots quotidiens, table ranking_snapshots)
        $statsData = $this->buildStatsData($tournament);

        return Inertia::render('Tournaments/Show', [
            'tournament' => $tournament,
            'isOwner' => auth()->id() === $tournament->user_id,
            'isMember' => $isMember,
            'isAdmin' => $isAdmin,
            'predefinedTeams' => config('teams'),
            'userWinnerPrediction' => $userWinnerPrediction,
            'userLoserPrediction' => $userLoserPrediction,
            'userTopScorerPrediction' => $userTopScorerPrediction,
            'userLastPlacePrediction' => $userLastPlacePrediction,
            'userPredictions' => $userPredictions,
            'membersWinnerPredictions' => $membersWinnerPredictions,
            'myTournaments' => $myTournaments,
            'statsData' => $statsData,
        ]);
    }

    /**
     * Construit les séries d'évolution (points + classement) pour le graphique
     * de statistiques, à partir des snapshots quotidiens (ranking_snapshots).
     * Lecture seule : ne modifie jamais la base.
     */
    private function buildStatsData(Tournament $tournament): array
    {
        $snapshots = RankingSnapshot::where('tournament_id', $tournament->id)
            ->orderBy('football_day')
            ->get(['user_id', 'position', 'total_points', 'football_day']);

        if ($snapshots->isEmpty()) {
            return ['days' => [], 'players' => []];
        }

        $days = $snapshots
            ->map(fn ($s) => $s->football_day->toDateString())
            ->unique()
            ->sort()
            ->values();

        $memberNames = $tournament->members->pluck('name', 'id');

        // Index [user_id][football_day] => snapshot
        $byUserDay = [];
        foreach ($snapshots as $snap) {
            $byUserDay[$snap->user_id][$snap->football_day->toDateString()] = $snap;
        }

        $players = [];
        foreach ($byUserDay as $userId => $dayMap) {
            // On n'affiche que les membres actuels (nom disponible)
            if (!isset($memberNames[$userId])) {
                continue;
            }

            $points = [];
            $positions = [];
            $lastPoints = null;
            $lastPosition = null;

            foreach ($days as $day) {
                if (isset($dayMap[$day])) {
                    $lastPoints = $dayMap[$day]->total_points;
                    $lastPosition = $dayMap[$day]->position;
                }
                // Report de la dernière valeur connue pour des courbes continues
                $points[] = $lastPoints;
                $positions[] = $lastPosition;
            }

            $players[] = [
                'id' => $userId,
                'name' => $memberNames[$userId],
                'points' => $points,
                'positions' => $positions,
            ];
        }

        // Tri par total de points actuel décroissant (meilleur en premier)
        usort($players, fn ($a, $b) => (end($b['points']) ?? 0) <=> (end($a['points']) ?? 0));

        return [
            'days' => $days->all(),
            'players' => $players,
        ];
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
        $user = auth()->user();
        if (!$tournament->isAdmin($user) && !$user->is_admin) {
            abort(403);
        }

        $wasOpen = $tournament->predictions_open;
        $tournament->update(['predictions_open' => !$tournament->predictions_open]);

        // Si on ferme les pronostics pour la première fois, verrouiller les prédictions de vainqueur
        if ($wasOpen && !$tournament->predictions_open && !$tournament->winner_predictions_locked) {
            $tournament->update(['winner_predictions_locked' => true]);
        }

        $message = $tournament->predictions_open
            ? 'Pronostics ouverts. Les joueurs peuvent faire leurs pronostics de matchs.'
            : 'Pronostics fermés.';

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

    public function specialPronos(Tournament $tournament): Response
    {
        $user = auth()->user();
        abort_if(!$tournament->isMember($user), 403);

        $tournament->load(['tournamentGroups', 'members']);

        // Matchs de poule uniquement
        $groupMatches = Game::where('tournament_id', $tournament->id)
            ->where('round', 'group')
            ->with(['homeTeam', 'awayTeam', 'tournamentGroup'])
            ->orderBy('scheduled_at')
            ->get();

        $memberIds = $tournament->members->pluck('id');

        // Pronostics de l'utilisateur pour les matchs de poule
        $myPredictions = Prediction::where('user_id', $user->id)
            ->whereIn('match_id', $groupMatches->pluck('id'))
            ->get()
            ->keyBy('match_id');

        // Pronos doublés
        $doubleStats = [
            'used' => Prediction::getDoubledCount($user->id, $tournament->id),
            'max' => Prediction::MAX_DOUBLED_PER_TOURNAMENT,
            'remaining' => Prediction::MAX_DOUBLED_PER_TOURNAMENT - Prediction::getDoubledCount($user->id, $tournament->id),
        ];

        // Blocs posés par l'utilisateur (keyed by target_user_id)
        $myBlocks = PredictionBlock::where('blocker_user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->with('targetMatch.homeTeam', 'targetMatch.awayTeam')
            ->get()
            ->keyBy('target_user_id');

        // Échanges initiés par l'utilisateur (keyed by target_user_id)
        $mySwaps = PredictionSwap::where('initiator_user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->with('initiatorMatch.homeTeam', 'initiatorMatch.awayTeam', 'targetMatch.homeTeam', 'targetMatch.awayTeam')
            ->get()
            ->keyBy('target_user_id');

        // Échanges reçus par l'utilisateur (keyed by initiator_user_id)
        $receivedSwaps = PredictionSwap::where('target_user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->with('initiatorMatch.homeTeam', 'initiatorMatch.awayTeam', 'targetMatch.homeTeam', 'targetMatch.awayTeam', 'initiator')
            ->get()
            ->keyBy('initiator_user_id');

        // Blocs reçus par l'utilisateur (keyed by blocker_user_id)
        $receivedBlocks = PredictionBlock::where('target_user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->with('targetMatch.homeTeam', 'targetMatch.awayTeam', 'blocker')
            ->get()
            ->keyBy('blocker_user_id');

        // Autres membres (adversaires)
        $opponents = $tournament->members->where('id', '!=', $user->id)->values();

        return Inertia::render('Tournaments/SpecialPronos', [
            'tournament' => $tournament,
            'groupMatches' => $groupMatches,
            'myPredictions' => $myPredictions,
            'doubleStats' => $doubleStats,
            'myBlocks' => $myBlocks,
            'mySwaps' => $mySwaps,
            'receivedSwaps' => $receivedSwaps,
            'receivedBlocks' => $receivedBlocks,
            'opponents' => $opponents,
            'predictionsOpen' => $tournament->predictions_open,
        ]);
    }

    public function allBonusPredictions(Tournament $tournament): Response
    {
        abort_if(!$tournament->isMember(auth()->user()), 403);

        $tournament->load(['teams', 'winnerTeam', 'loserTeam', 'lastPlaceUser', 'members']);

        $memberIds = $tournament->members->pluck('id');
        $userId = auth()->id();

        if ($tournament->winner_predictions_locked) {
            $winnerPredictions = TournamentWinnerPrediction::where('tournament_id', $tournament->id)
                ->whereIn('user_id', $memberIds)
                ->with(['firstChoiceTeam', 'secondChoiceTeam', 'thirdChoiceTeam'])
                ->get()
                ->keyBy('user_id');

            $loserPredictions = TournamentLoserPrediction::where('tournament_id', $tournament->id)
                ->whereIn('user_id', $memberIds)
                ->with('team')
                ->get()
                ->keyBy('user_id');

            $topScorerPredictions = TournamentTopScorerPrediction::where('tournament_id', $tournament->id)
                ->whereIn('user_id', $memberIds)
                ->get()
                ->keyBy('user_id');

            $lastPlacePredictions = TournamentLastPlacePrediction::where('tournament_id', $tournament->id)
                ->whereIn('user_id', $memberIds)
                ->with('predictedUser')
                ->get()
                ->keyBy('user_id');
        } else {
            $winnerPredictions = TournamentWinnerPrediction::where('tournament_id', $tournament->id)
                ->where('user_id', $userId)
                ->with(['firstChoiceTeam', 'secondChoiceTeam', 'thirdChoiceTeam'])
                ->get()
                ->keyBy('user_id');

            $loserPredictions = TournamentLoserPrediction::where('tournament_id', $tournament->id)
                ->where('user_id', $userId)
                ->with('team')
                ->get()
                ->keyBy('user_id');

            $topScorerPredictions = TournamentTopScorerPrediction::where('tournament_id', $tournament->id)
                ->where('user_id', $userId)
                ->get()
                ->keyBy('user_id');

            $lastPlacePredictions = TournamentLastPlacePrediction::where('tournament_id', $tournament->id)
                ->where('user_id', $userId)
                ->with('predictedUser')
                ->get()
                ->keyBy('user_id');
        }

        return Inertia::render('Tournaments/AllBonusPredictions', [
            'tournament' => $tournament,
            'members' => $tournament->members,
            'winnerPredictions' => $winnerPredictions,
            'loserPredictions' => $loserPredictions,
            'topScorerPredictions' => $topScorerPredictions,
            'lastPlacePredictions' => $lastPlacePredictions,
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

        // Pronos doublés par match/user
        $allDoubles = [];
        foreach ($allPredictions->flatten() as $pred) {
            if ($pred->is_doubled) {
                $allDoubles[$pred->match_id][$pred->user_id] = true;
            }
        }

        return Inertia::render('Tournaments/AllPredictions', [
            'tournament' => $tournament,
            'matchesByGroup' => $matchesByGroup,
            'knockoutMatches' => $knockoutMatches,
            'allPredictions' => $allPredictions,
            'allDoubles' => $allDoubles,
            'members' => $tournament->members,
            'predictionsOpen' => $tournament->predictions_open,
        ]);
    }
}
