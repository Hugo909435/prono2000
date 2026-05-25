<?php

use App\Http\Controllers\MatchController;
use App\Http\Controllers\PredictionBlockController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\PredictionDoubleController;
use App\Http\Controllers\PredictionSwapController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TournamentController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    $user = auth()->user();

    // Tournois rejoints par l'utilisateur avec classement
    $myTournaments = $user->joinedTournaments()
        ->with(['tournamentGroups.teams', 'teams', 'winnerTeam', 'members' => function($q) {
            $q->orderByDesc('tournament_user.total_points')
              ->orderByDesc('tournament_user.exact_scores');
        }])
        ->withCount('members')
        ->orderByDesc('tournaments.created_at')
        ->get();

    // IDs des tournois rejoints
    $tournamentIds = $myTournaments->pluck('id')->unique();

    // Pronostics vainqueur de l'utilisateur pour chaque tournoi
    $userWinnerPredictions = $user->winnerPredictions()
        ->whereIn('tournament_id', $tournamentIds)
        ->with(['firstChoiceTeam', 'secondChoiceTeam', 'thirdChoiceTeam'])
        ->get()
        ->keyBy('tournament_id');

    // Pronostics vainqueur de tous les membres des tournois (si pronostics verrouillés)
    // Keyed by tournament_id
    $membersWinnerPredictions = [];
    foreach ($myTournaments as $tournament) {
        if ($tournament->winner_predictions_locked) {
            $memberIds = $tournament->members->pluck('id');
            $membersWinnerPredictions[$tournament->id] = \App\Models\TournamentWinnerPrediction::where('tournament_id', $tournament->id)
                ->whereIn('user_id', $memberIds)
                ->with(['firstChoiceTeam', 'secondChoiceTeam', 'thirdChoiceTeam'])
                ->get()
                ->keyBy('user_id');
        }
    }

    // Date selectionnee (par defaut aujourd'hui)
    $selectedDate = request('date', now()->toDateString());

    // Matchs du jour selectionne (seulement les tournois rejoints par l'user)
    $matchesOfDay = \App\Models\Game::with(['tournament', 'homeTeam', 'awayTeam'])
        ->whereHas('tournament', fn($q) => $q->where('status', 'active'))
        ->whereIn('tournament_id', $tournamentIds)
        ->whereDate('scheduled_at', $selectedDate)
        ->orderBy('scheduled_at')
        ->get();

    // Charger les pronostics seulement si les pronostics du tournoi sont fermes
    $matchesOfDay->each(function ($match) use ($user) {
        if ($match->tournament->predictions_open) {
            // Pronostics ouverts : on ne charge que le pronostic de l'utilisateur
            $match->setRelation('predictions', $match->predictions()
                ->where('user_id', $user->id)
                ->with('user')
                ->get());
        } else {
            // Pronostics fermes : on charge tous les pronostics
            $match->load('predictions.user');
        }
    });

    // Dates avec des matchs pour la navigation (seulement pour les tournois rejoints)
    $availableDates = \App\Models\Game::whereHas('tournament', fn($q) => $q->where('status', 'active'))
        ->whereIn('tournament_id', $tournamentIds)
        ->whereNotNull('scheduled_at')
        ->selectRaw('DATE(scheduled_at) as date')
        ->distinct()
        ->orderBy('date')
        ->pluck('date')
        ->toArray();

    // Pronostics de l'utilisateur pour les matchs du jour
    $userPredictions = $user->predictions()
        ->whereIn('match_id', $matchesOfDay->pluck('id'))
        ->get()
        ->keyBy('match_id');

    // Stats doublés par tournoi
    $doubleStatsPerTournament = [];
    foreach ($myTournaments as $tournament) {
        $used = \App\Models\Prediction::getDoubledCount($user->id, $tournament->id);
        $max = \App\Models\Prediction::MAX_DOUBLED_PER_TOURNAMENT;
        $doubleStatsPerTournament[$tournament->id] = [
            'used' => $used,
            'max' => $max,
            'remaining' => $max - $used,
        ];
    }

    // Blocs posés par l'utilisateur par tournoi (keyed by target_user_id)
    $myBlocksPerTournament = [];
    foreach ($myTournaments as $tournament) {
        $myBlocksPerTournament[$tournament->id] = \App\Models\PredictionBlock::where('blocker_user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->get()
            ->keyBy('target_user_id')
            ->map(fn($b) => [
                'id' => $b->id,
                'target_user_id' => $b->target_user_id,
                'target_match_id' => $b->target_match_id,
            ]);
    }

    // Échanges posés par l'utilisateur par tournoi (keyed by target_user_id)
    $mySwapsPerTournament = [];
    foreach ($myTournaments as $tournament) {
        $mySwapsPerTournament[$tournament->id] = \App\Models\PredictionSwap::where('initiator_user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->get()
            ->keyBy('target_user_id')
            ->map(fn($s) => [
                'id' => $s->id,
                'target_user_id' => $s->target_user_id,
                'initiator_match_id' => $s->initiator_match_id,
                'target_match_id' => $s->target_match_id,
            ]);
    }

    // Blocs/échanges posés par d'AUTRES joueurs (pour désactiver les slots déjà pris)
    $takenBlocksPerTournament = [];
    $takenSwapsPerTournament = [];
    foreach ($myTournaments as $tournament) {
        $takenBlocksPerTournament[$tournament->id] = \App\Models\PredictionBlock::where('tournament_id', $tournament->id)
            ->where('blocker_user_id', '!=', $user->id)
            ->get()
            ->mapWithKeys(fn($b) => ["{$b->target_user_id}_{$b->target_match_id}" => true]);
        $takenSwapsPerTournament[$tournament->id] = \App\Models\PredictionSwap::where('tournament_id', $tournament->id)
            ->where('initiator_user_id', '!=', $user->id)
            ->get()
            ->mapWithKeys(fn($s) => ["{$s->target_user_id}_{$s->initiator_match_id}" => true]);
    }

    // Tous les échanges et blocs actifs (pour affichage visuel dans la grille)
    $allSwapsPerTournament = [];
    $allBlocksPerTournament = [];
    foreach ($myTournaments as $tournament) {
        $allSwapsPerTournament[$tournament->id] = \App\Models\PredictionSwap::where('tournament_id', $tournament->id)
            ->get()
            ->map(fn($s) => [
                'initiator_user_id'  => $s->initiator_user_id,
                'target_user_id'     => $s->target_user_id,
                'initiator_match_id' => $s->initiator_match_id,
                'target_match_id'    => $s->target_match_id,
            ])
            ->values();
        $allBlocksPerTournament[$tournament->id] = \App\Models\PredictionBlock::where('tournament_id', $tournament->id)
            ->get()
            ->map(fn($b) => [
                'blocker_user_id' => $b->blocker_user_id,
                'target_user_id'  => $b->target_user_id,
                'target_match_id' => $b->target_match_id,
            ])
            ->values();
    }

    return Inertia::render('Dashboard', [
        'myTournaments' => $myTournaments,
        'matchesOfDay' => $matchesOfDay,
        'selectedDate' => $selectedDate,
        'availableDates' => $availableDates,
        'userPredictions' => $userPredictions,
        'userWinnerPredictions' => $userWinnerPredictions,
        'membersWinnerPredictions' => $membersWinnerPredictions,
        'doubleStatsPerTournament' => $doubleStatsPerTournament,
        'myBlocksPerTournament' => $myBlocksPerTournament,
        'mySwapsPerTournament' => $mySwapsPerTournament,
        'takenBlocksPerTournament' => $takenBlocksPerTournament,
        'takenSwapsPerTournament' => $takenSwapsPerTournament,
        'allSwapsPerTournament' => $allSwapsPerTournament,
        'allBlocksPerTournament' => $allBlocksPerTournament,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', \App\Http\Middleware\AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [\App\Http\Controllers\AdminController::class, 'index'])->name('users');
    Route::post('/users', [\App\Http\Controllers\AdminController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}/toggle-admin', [\App\Http\Controllers\AdminController::class, 'toggleAdmin'])->name('users.toggleAdmin');
    Route::delete('/users/{user}', [\App\Http\Controllers\AdminController::class, 'destroy'])->name('users.destroy');
});

Route::middleware('auth')->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Tournaments
    Route::post('/tournaments/join', [TournamentController::class, 'join'])->name('tournaments.join');
    Route::resource('tournaments', TournamentController::class);
    Route::post('/tournaments/{tournament}/activate', [TournamentController::class, 'activate'])->name('tournaments.activate');
    Route::post('/tournaments/{tournament}/toggle-predictions', [TournamentController::class, 'togglePredictions'])->name('tournaments.togglePredictions');
    Route::get('/tournaments/{tournament}/bracket', [TournamentController::class, 'bracket'])->name('tournaments.bracket');
    Route::delete('/tournaments/{tournament}/leave', [TournamentController::class, 'leave'])->name('tournaments.leave');
    Route::get('/tournaments/{tournament}/all-predictions', [TournamentController::class, 'allPredictions'])->name('tournaments.allPredictions');
    Route::get('/tournaments/{tournament}/all-winner-predictions', [TournamentController::class, 'allWinnerPredictions'])->name('tournaments.allWinnerPredictions');
    Route::get('/tournaments/{tournament}/all-bonus-predictions', [TournamentController::class, 'allBonusPredictions'])->name('tournaments.allBonusPredictions');
    Route::get('/tournaments/{tournament}/special-pronos', [TournamentController::class, 'specialPronos'])->name('tournaments.specialPronos');

    // Teams
    Route::prefix('tournaments/{tournament}')->name('tournaments.')->group(function () {
        Route::resource('teams', TeamController::class)->except(['show']);
        Route::post('teams/import', [TeamController::class, 'import'])->name('teams.import');
    });

    // Matches
    Route::prefix('tournaments/{tournament}')->name('tournaments.')->group(function () {
        Route::resource('matches', MatchController::class);
        Route::post('matches/{match}/result', [MatchController::class, 'updateResult'])->name('matches.result');
        Route::post('matches/generate', [MatchController::class, 'generate'])->name('matches.generate');
    });

    // Tournament Groups (Poules)
    Route::prefix('tournaments/{tournament}')->name('tournaments.')->group(function () {
        Route::post('groups', [\App\Http\Controllers\TournamentGroupController::class, 'store'])->name('groups.store');
        Route::post('groups/multiple', [\App\Http\Controllers\TournamentGroupController::class, 'storeMultiple'])->name('groups.storeMultiple');
        Route::delete('groups/{group}', [\App\Http\Controllers\TournamentGroupController::class, 'destroy'])->name('groups.destroy');
        Route::post('groups/{group}/teams', [\App\Http\Controllers\TournamentGroupController::class, 'addTeam'])->name('groups.addTeam');
        Route::delete('groups/{group}/teams/{teamId}', [\App\Http\Controllers\TournamentGroupController::class, 'removeTeam'])->name('groups.removeTeam');
        Route::post('groups/generate-matches', [\App\Http\Controllers\TournamentGroupController::class, 'generateMatches'])->name('groups.generateMatches');
    });

    // Classement
    Route::get('/classement', function () {
        $user = auth()->user();
        $tournaments = $user->joinedTournaments()
            ->with(['members' => function ($q) {
                $q->orderByDesc('tournament_user.total_points')
                  ->orderByDesc('tournament_user.exact_scores');
            }])
            ->orderByDesc('tournaments.created_at')
            ->get();

        // Ajouter avatar_url sur chaque membre
        $tournaments->each(function ($tournament) {
            $tournament->members->each(function ($member) {
                $member->avatar_url = $member->avatar
                    ? asset('storage/' . $member->avatar)
                    : null;
            });
        });

        return Inertia::render('Classement', [
            'tournaments' => $tournaments,
        ]);
    })->name('classement');

    // Predictions
    Route::get('/predictions', [PredictionController::class, 'index'])->name('predictions.index');
    Route::get('/predictions/match/{match}', [PredictionController::class, 'show'])->name('predictions.show');
    Route::post('/predictions/match/{match}', [PredictionController::class, 'store'])->name('predictions.store');
    Route::put('/predictions/{prediction}', [PredictionController::class, 'update'])->name('predictions.update');

    // Winner Predictions
    Route::post('/tournaments/{tournament}/winner-prediction', [\App\Http\Controllers\WinnerPredictionController::class, 'store'])->name('tournaments.winner-prediction.store');
    Route::post('/tournaments/{tournament}/set-winner', [\App\Http\Controllers\WinnerPredictionController::class, 'setWinner'])->name('tournaments.set-winner');

    // Bonus Predictions (Loser + Top Scorer)
    Route::post('/tournaments/{tournament}/loser-prediction', [\App\Http\Controllers\BonusPredictionController::class, 'storeLoser'])->name('tournaments.loser-prediction.store');
    Route::post('/tournaments/{tournament}/top-scorer-prediction', [\App\Http\Controllers\BonusPredictionController::class, 'storeTopScorer'])->name('tournaments.top-scorer-prediction.store');
    Route::post('/tournaments/{tournament}/set-loser', [\App\Http\Controllers\BonusPredictionController::class, 'setLoser'])->name('tournaments.set-loser');
    Route::post('/tournaments/{tournament}/set-top-scorer', [\App\Http\Controllers\BonusPredictionController::class, 'setTopScorer'])->name('tournaments.set-top-scorer');
    Route::post('/tournaments/{tournament}/last-place-prediction', [\App\Http\Controllers\BonusPredictionController::class, 'storeLastPlace'])->name('tournaments.last-place-prediction.store');
    Route::post('/tournaments/{tournament}/set-last-place', [\App\Http\Controllers\BonusPredictionController::class, 'setLastPlace'])->name('tournaments.set-last-place');

    // Pronos Spéciaux
    Route::post('/doubles/match/{match}/toggle', [PredictionDoubleController::class, 'toggle'])->name('doubles.toggle');
    Route::get('/doubles/tournament/{tournament}/stats', [PredictionDoubleController::class, 'stats'])->name('doubles.stats');
    Route::post('/blocks', [PredictionBlockController::class, 'store'])->name('blocks.store');
    Route::delete('/blocks/{block}', [PredictionBlockController::class, 'destroy'])->name('blocks.destroy');
    Route::post('/swaps', [PredictionSwapController::class, 'store'])->name('swaps.store');
    Route::delete('/swaps/{swap}', [PredictionSwapController::class, 'destroy'])->name('swaps.destroy');
});

require __DIR__.'/auth.php';
