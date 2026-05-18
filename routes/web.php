<?php

use App\Http\Controllers\MatchController;
use App\Http\Controllers\PointBoosterController;
use App\Http\Controllers\PredictionController;
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

    // Boosters de l'utilisateur pour les matchs du jour
    $userBoosters = $user->pointBoosters()
        ->whereIn('match_id', $matchesOfDay->pluck('id'))
        ->pluck('match_id')
        ->flip()
        ->map(fn() => true)
        ->toArray();

    // Statistiques de boosters par tournoi
    $allTournamentIds = $tournamentIds->merge($matchesOfDay->pluck('tournament_id'))->unique();
    $boosterStats = [];
    foreach ($allTournamentIds as $tournamentId) {
        $boosterStats[$tournamentId] = [
            'remaining' => \App\Models\PointBooster::getRemainingBoostersCount($user->id, $tournamentId),
            'max' => \App\Models\PointBooster::MAX_BOOSTERS_PER_TOURNAMENT,
        ];
    }

    return Inertia::render('Dashboard', [
        'myTournaments' => $myTournaments,
        'matchesOfDay' => $matchesOfDay,
        'selectedDate' => $selectedDate,
        'availableDates' => $availableDates,
        'userPredictions' => $userPredictions,
        'userWinnerPredictions' => $userWinnerPredictions,
        'membersWinnerPredictions' => $membersWinnerPredictions,
        'userBoosters' => $userBoosters,
        'boosterStats' => $boosterStats,
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

    // Point Boosters
    Route::post('/boosters/match/{match}/toggle', [PointBoosterController::class, 'toggle'])->name('boosters.toggle');
    Route::get('/boosters/tournament/{tournament}/status', [PointBoosterController::class, 'status'])->name('boosters.status');
});

require __DIR__.'/auth.php';
