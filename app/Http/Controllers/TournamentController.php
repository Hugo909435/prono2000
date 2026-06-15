<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tournament\StoreTournamentRequest;
use App\Http\Requests\Tournament\UpdateTournamentRequest;
use App\Models\Game;
use App\Models\Prediction;
use App\Models\PredictionBlock;
use App\Models\PredictionSwap;
use App\Models\RankingSnapshot;
use App\Models\Recap;
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

    /**
     * Prend un snapshot du classement de CE tournoi pour la journée foot en cours.
     * Réservé aux admins. Utile sur hébergement mutualisé sans accès SSH pour
     * créer le premier point de statistiques sans attendre le cron de 12h.
     * Sûr : updateOrCreate (n'efface jamais de données existantes).
     */
    public function snapshotNow(Tournament $tournament): RedirectResponse
    {
        $user = auth()->user();
        if (!$tournament->isAdmin($user) && !$user->is_admin) {
            abort(403);
        }

        $footballDay = RankingSnapshot::currentFootballDay();

        // Membres déjà triés par total_points DESC (orderByPivot dans la relation)
        $members = $tournament->members;

        foreach ($members as $position => $member) {
            RankingSnapshot::updateOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'user_id'       => $member->id,
                    'football_day'  => $footballDay,
                ],
                [
                    'position'        => $position + 1,
                    'total_points'    => $member->pivot->total_points ?? 0,
                    'exact_scores'    => $member->pivot->exact_scores ?? 0,
                    'correct_results' => $member->pivot->correct_results ?? 0,
                ]
            );
        }

        return back()->with('success', 'Snapshot du classement enregistré ('.$members->count().' joueur(s)). Les statistiques sont à jour.');
    }

    /**
     * Reconstruit l'historique des statistiques (points + classement) depuis le
     * début du tournoi, à partir des matchs déjà terminés. Réservé aux admins.
     * Sûr : ne réécrit jamais les snapshots existants (firstOrCreate).
     */
    public function backfillStats(Tournament $tournament, \App\Services\PredictionScoringService $scoring): RedirectResponse
    {
        $user = auth()->user();
        if (!$tournament->isAdmin($user) && !$user->is_admin) {
            abort(403);
        }

        $created = $scoring->buildHistory($tournament);

        return back()->with('success', $created > 0
            ? "Historique reconstruit : {$created} relevé(s) ajouté(s)."
            : 'Aucun nouveau relevé à reconstruire (historique déjà complet ou aucun match terminé).');
    }

    /**
     * Publie le "récap du jour" d'un tournoi. Déclenché manuellement par l'admin
     * APRÈS la saisie des scores. Le récap est figé en JSON (table recaps) : la vue
     * lit des données figées, donc aucun recalcul ni dépendance à l'heure.
     *
     * Flèches d'évolution : on chaîne les récaps. Le previousPosition de ce récap =
     * le currentPosition du récap précédent (donc l'évolution depuis le dernier récap).
     * Le tout premier récap d'un tournoi n'a pas de flèches (pas de point de comparaison).
     */
    public function publishRecap(Tournament $tournament): RedirectResponse
    {
        $user = auth()->user();
        if (!$tournament->isAdmin($user) && !$user->is_admin) {
            abort(403);
        }

        // Borne de la "journée foot" en cours : une journée va de 12h00 à 11h59 le
        // lendemain. On part du dernier midi : si on clique le matin, le début est
        // midi LA VEILLE (donc les matchs du soir d'hier sont inclus) ; si on clique
        // l'après-midi/le soir, le début est midi le jour même.
        $footballDay = RankingSnapshot::currentFootballDay();
        $footballDayStart = \Carbon\Carbon::parse($footballDay)->setTime(12, 0, 0);
        $now = now();

        // Matchs terminés de la journée foot en cours, pas encore inclus dans un récap.
        // On borne par scheduled_at (heure du match) sur la fenêtre [midi → maintenant]
        // pour ne prendre QUE la journée foot et exclure d'éventuels matchs hors fenêtre.
        $matches = Game::with(['homeTeam', 'awayTeam'])
            ->where('tournament_id', $tournament->id)
            ->where('status', 'completed')
            ->whereNull('recapped_at')
            ->whereBetween('scheduled_at', [$footballDayStart, $now])
            ->orderBy('scheduled_at')
            ->get();

        if ($matches->isEmpty()) {
            return back()->with('error', "Aucun nouveau match terminé à inclure. Saisis d'abord les scores du jour, puis publie le récap.");
        }

        // Classement actuel (déjà trié par points DESC dans la relation members)
        $members = $tournament->members;

        // Récap précédent → base des flèches (previousPosition)
        $previousRecap = Recap::where('tournament_id', $tournament->id)
            ->orderByDesc('published_at')
            ->first();

        $prevPositions = [];
        foreach (($previousRecap->payload['rankings'] ?? []) as $r) {
            $prevPositions[$r['user_id']] = $r['currentPosition'];
        }

        $rankings = $members->values()->map(function ($member, $idx) use ($prevPositions) {
            return [
                'user_id'          => $member->id,
                'name'             => $member->name,
                'currentPosition'  => $idx + 1,
                'previousPosition' => $prevPositions[$member->id] ?? null,
                'totalPoints'      => $member->pivot->total_points ?? 0,
            ];
        })->values();

        $firstMember = $members->first();
        $lastMember  = $members->last();

        // Streak = nb de récaps consécutifs (celui-ci inclus) où le joueur tient la place
        $streakFor = function (?int $userId, string $place) use ($tournament) {
            if (!$userId) {
                return 1;
            }
            $streak = 1;
            $recaps = Recap::where('tournament_id', $tournament->id)
                ->orderByDesc('published_at')
                ->limit(365)
                ->get(['payload']);
            foreach ($recaps as $r) {
                if (($r->payload[$place]['user_id'] ?? null) === $userId) {
                    $streak++;
                } else {
                    break;
                }
            }
            return $streak;
        };

        $firstStreak = $streakFor($firstMember?->id, 'firstPlace');
        $lastStreak  = $streakFor($lastMember?->id, 'lastPlace');

        $matchesSummary = $matches->map(function ($match) {
            return [
                'id'        => $match->id,
                'homeTeam'  => $match->homeTeam?->short_name ?? $match->homeTeam?->name ?? '?',
                'awayTeam'  => $match->awayTeam?->short_name ?? $match->awayTeam?->name ?? '?',
                'homeFlag'  => $match->homeTeam?->flag,
                'awayFlag'  => $match->awayTeam?->flag,
                'homeScore' => $match->home_score,
                'awayScore' => $match->away_score,
            ];
        })->values();

        $payload = [
            'footballDay'    => $footballDay,
            'tournamentId'   => $tournament->id,
            'tournamentName' => $tournament->name,
            'matchIds'       => $matches->pluck('id')->all(),
            'matchesPlayed'  => $matchesSummary,
            'rankings'       => $rankings,
            'firstPlace'     => $firstMember ? [
                'user_id' => $firstMember->id,
                'name'    => $firstMember->name,
                'points'  => $firstMember->pivot->total_points ?? 0,
                'streak'  => $firstStreak,
            ] : null,
            'lastPlace'      => ($lastMember && $lastMember->id !== $firstMember?->id) ? [
                'user_id' => $lastMember->id,
                'name'    => $lastMember->name,
                'points'  => $lastMember->pivot->total_points ?? 0,
                'streak'  => $lastStreak,
            ] : null,
        ];

        \Illuminate\Support\Facades\DB::transaction(function () use ($tournament, $user, $now, $footballDay, $payload, $matches) {
            Recap::create([
                'tournament_id' => $tournament->id,
                'published_by'  => $user->id,
                'football_day'  => $footballDay,
                'payload'       => $payload,
                'is_baseline'   => false,
                'published_at'  => $now,
            ]);

            Game::whereIn('id', $matches->pluck('id'))->update(['recapped_at' => $now]);
        });

        $count = $matches->count();

        return back()->with('success', "Récap publié ! {$count} match".($count > 1 ? 's' : '').' inclus. Les joueurs le verront à leur prochaine visite.');
    }

    /**
     * Initialise une "baseline" : marque tous les matchs déjà terminés comme déjà
     * recappés (sans rien afficher) et fige les positions de départ. À utiliser une
     * seule fois sur un tournoi qui a déjà de l'historique, pour que les futurs récaps
     * ne contiennent que les NOUVEAUX matchs et que le premier vrai récap ait ses flèches.
     * Sûr : ne touche qu'à la colonne additive recapped_at (NULL → date) et crée un récap baseline.
     */
    public function initRecapBaseline(Tournament $tournament): RedirectResponse
    {
        $user = auth()->user();
        if (!$tournament->isAdmin($user) && !$user->is_admin) {
            abort(403);
        }

        $members = $tournament->members;
        $now = now();

        $rankings = $members->values()->map(function ($member, $idx) {
            return [
                'user_id'          => $member->id,
                'name'             => $member->name,
                'currentPosition'  => $idx + 1,
                'previousPosition' => null,
                'totalPoints'      => $member->pivot->total_points ?? 0,
            ];
        })->values();

        \Illuminate\Support\Facades\DB::transaction(function () use ($tournament, $user, $now, $rankings) {
            Recap::create([
                'tournament_id' => $tournament->id,
                'published_by'  => $user->id,
                'football_day'  => $now->toDateString(),
                'payload'       => ['rankings' => $rankings],
                'is_baseline'   => true,
                'published_at'  => $now,
            ]);

            Game::where('tournament_id', $tournament->id)
                ->where('status', 'completed')
                ->whereNull('recapped_at')
                ->update(['recapped_at' => $now]);
        });

        return back()->with('success', "Baseline initialisée. Les prochains récaps ne contiendront que les nouveaux matchs, et le premier aura déjà ses flèches.");
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
