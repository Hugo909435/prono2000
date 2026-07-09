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

    $myTournaments = $user->joinedTournaments()
        ->with(['tournamentGroups.teams', 'teams', 'winnerTeam', 'members' => function($q) {
            $q->orderByDesc('tournament_user.total_points')
              ->orderByDesc('tournament_user.exact_scores');
        }])
        ->withCount('members')
        ->orderByDesc('tournaments.created_at')
        ->get();

    $tournamentIds = $myTournaments->pluck('id')->unique();

    $userWinnerPredictions = $user->winnerPredictions()
        ->whereIn('tournament_id', $tournamentIds)
        ->with(['firstChoiceTeam', 'secondChoiceTeam', 'thirdChoiceTeam'])
        ->get()
        ->keyBy('tournament_id');

    // 1 requête groupée au lieu de N requêtes (une par tournoi verrouillé)
    $membersWinnerPredictions = [];
    $lockedIds = $myTournaments->where('winner_predictions_locked', true)->pluck('id');
    if ($lockedIds->isNotEmpty()) {
        $allMemberIds = $myTournaments->flatMap(fn($t) => $t->members->pluck('id'))->unique();
        $grouped = \App\Models\TournamentWinnerPrediction::whereIn('tournament_id', $lockedIds)
            ->whereIn('user_id', $allMemberIds)
            ->with(['firstChoiceTeam', 'secondChoiceTeam', 'thirdChoiceTeam'])
            ->get()
            ->groupBy('tournament_id');
        foreach ($lockedIds as $tid) {
            $membersWinnerPredictions[$tid] = ($grouped[$tid] ?? collect())->keyBy('user_id');
        }
    }

    $selectedDate = request('date', now()->toDateString());

    $matchesOfDay = \App\Models\Game::with(['tournament', 'homeTeam', 'awayTeam'])
        ->whereHas('tournament', fn($q) => $q->where('status', 'active'))
        ->whereIn('tournament_id', $tournamentIds)
        ->whereDate('scheduled_at', $selectedDate)
        ->orderBy('scheduled_at')
        ->get();

    // Batch load predictions pour tous les matchs du jour (1 requête au lieu de N)
    if ($matchesOfDay->isNotEmpty()) {
        $openMatchIds  = $matchesOfDay->filter(fn($m) => $m->tournament->predictions_open)->pluck('id');
        $closedMatchIds = $matchesOfDay->filter(fn($m) => !$m->tournament->predictions_open)->pluck('id');

        $batchedPredictions = \App\Models\Prediction::with('user')
            ->where(function ($q) use ($openMatchIds, $closedMatchIds, $user) {
                if ($openMatchIds->isNotEmpty()) {
                    $q->orWhere(fn($s) => $s->whereIn('match_id', $openMatchIds)->where('user_id', $user->id));
                }
                if ($closedMatchIds->isNotEmpty()) {
                    $q->orWhereIn('match_id', $closedMatchIds);
                }
            })
            ->get()
            ->groupBy('match_id');

        foreach ($matchesOfDay as $match) {
            $match->setRelation('predictions', $batchedPredictions->get($match->id, collect()));
        }
    }

    $availableDates = \App\Models\Game::whereHas('tournament', fn($q) => $q->where('status', 'active'))
        ->whereIn('tournament_id', $tournamentIds)
        ->whereNotNull('scheduled_at')
        ->selectRaw('DATE(scheduled_at) as date')
        ->distinct()
        ->orderBy('date')
        ->pluck('date')
        ->toArray();

    $userPredictions = $user->predictions()
        ->whereIn('match_id', $matchesOfDay->pluck('id'))
        ->get()
        ->keyBy('match_id');

    // Blocks et swaps : 2 requêtes groupées au lieu de 6N requêtes
    $allBlocksRaw = $tournamentIds->isNotEmpty()
        ? \App\Models\PredictionBlock::whereIn('tournament_id', $tournamentIds)->get()->groupBy('tournament_id')
        : collect();
    $allSwapsRaw = $tournamentIds->isNotEmpty()
        ? \App\Models\PredictionSwap::whereIn('tournament_id', $tournamentIds)->get()->groupBy('tournament_id')
        : collect();

    $doubleStatsPerTournament = [];
    $myBlocksPerTournament    = [];
    $mySwapsPerTournament     = [];
    $takenBlocksPerTournament = [];
    $takenSwapsPerTournament  = [];
    $allSwapsPerTournament    = [];
    $allBlocksPerTournament   = [];

    foreach ($myTournaments as $tournament) {
        $tid    = $tournament->id;
        $blocks = $allBlocksRaw[$tid] ?? collect();
        $swaps  = $allSwapsRaw[$tid]  ?? collect();

        $used = \App\Models\Prediction::getDoubledCount($user->id, $tid);
        $max  = \App\Models\Prediction::MAX_DOUBLED_PER_TOURNAMENT;
        $doubleStatsPerTournament[$tid] = ['used' => $used, 'max' => $max, 'remaining' => $max - $used];

        $myBlocksPerTournament[$tid] = $blocks->where('blocker_user_id', $user->id)
            ->keyBy('target_user_id')
            ->map(fn($b) => ['id' => $b->id, 'target_user_id' => $b->target_user_id, 'target_match_id' => $b->target_match_id]);

        $mySwapsPerTournament[$tid] = $swaps->where('initiator_user_id', $user->id)
            ->keyBy('target_user_id')
            ->map(fn($s) => ['id' => $s->id, 'target_user_id' => $s->target_user_id, 'initiator_match_id' => $s->initiator_match_id, 'target_match_id' => $s->target_match_id]);

        $takenBlocksPerTournament[$tid] = $blocks->where('blocker_user_id', '!=', $user->id)
            ->mapWithKeys(fn($b) => ["{$b->target_user_id}_{$b->target_match_id}" => true]);

        $takenSwapsPerTournament[$tid] = $swaps->where('initiator_user_id', '!=', $user->id)
            ->mapWithKeys(fn($s) => ["{$s->target_user_id}_{$s->initiator_match_id}" => true]);

        $allSwapsPerTournament[$tid] = $swaps->map(fn($s) => [
            'initiator_user_id'  => $s->initiator_user_id,
            'target_user_id'     => $s->target_user_id,
            'initiator_match_id' => $s->initiator_match_id,
            'target_match_id'    => $s->target_match_id,
        ])->values();

        $allBlocksPerTournament[$tid] = $blocks->map(fn($b) => [
            'blocker_user_id' => $b->blocker_user_id,
            'target_user_id'  => $b->target_user_id,
            'target_match_id' => $b->target_match_id,
        ])->values();
    }

    // ── Recap du jour ────────────────────────────────────────────────────────────
    // Lecture d'un récap FIGÉ publié manuellement par l'admin (table recaps).
    // Aucune dépendance à l'heure : on prend le dernier récap publié parmi les
    // tournois du joueur. Seules les données personnelles (mon prono / mes points)
    // sont enrichies en direct à partir des match_ids figés dans le récap.
    $recapData = null;
    if ($myTournaments->isNotEmpty()) {
        $latestRecap = \App\Models\Recap::whereIn('tournament_id', $myTournaments->pluck('id'))
            ->where('is_baseline', false)
            ->orderByDesc('published_at')
            ->first();

        if ($latestRecap) {
            $p = $latestRecap->payload;
            $matchIds = $p['matchIds'] ?? [];

            $myPreds = $user->predictions()
                ->whereIn('match_id', $matchIds)
                ->get()
                ->keyBy('match_id');

            $rankings = collect($p['rankings'] ?? [])->map(fn ($r) => array_merge($r, [
                'isMe' => ($r['user_id'] ?? null) === $user->id,
            ]))->values();

            $me = collect($p['rankings'] ?? [])->firstWhere('user_id', $user->id);

            $matchesPlayed = collect($p['matchesPlayed'] ?? [])->map(function ($m) use ($myPreds) {
                $pred = $myPreds->get($m['id'] ?? null);
                return array_merge($m, [
                    'myPrediction' => $pred ? [
                        'home_score'    => $pred->home_score,
                        'away_score'    => $pred->away_score,
                        'points_earned' => $pred->points_earned,
                        'result_type'   => $pred->result_type,
                    ] : null,
                ]);
            })->values();

            $recapData = [
                'recapId'            => $latestRecap->id,
                'footballDay'        => $p['footballDay'] ?? optional($latestRecap->published_at)->toDateString(),
                'matchesPlayed'      => $matchesPlayed,
                // Points du jour échanges compris (le ×2 du joueur suit sur le prono pris)
                'myPointsEarned'     => isset($p['tournamentId'])
                    ? app(\App\Services\PredictionScoringService::class)
                        ->effectiveMatchPoints($user->id, (int) $p['tournamentId'], $matchIds)
                    : $myPreds->sum('points_earned'),
                'myCurrentPosition'  => $me['currentPosition'] ?? null,
                'myPreviousPosition' => $me['previousPosition'] ?? null,
                'tournamentName'     => $p['tournamentName'] ?? null,
                'firstPlace'         => $p['firstPlace'] ?? null,
                'lastPlace'          => $p['lastPlace'] ?? null,
                'rankings'           => $rankings,
                'hasData'            => true,
            ];
        }
    }

    return Inertia::render('Dashboard', [
        'myTournaments'            => $myTournaments,
        'matchesOfDay'             => $matchesOfDay,
        'selectedDate'             => $selectedDate,
        'availableDates'           => $availableDates,
        'userPredictions'          => $userPredictions,
        'userWinnerPredictions'    => $userWinnerPredictions,
        'membersWinnerPredictions' => $membersWinnerPredictions,
        'doubleStatsPerTournament' => $doubleStatsPerTournament,
        'myBlocksPerTournament'    => $myBlocksPerTournament,
        'mySwapsPerTournament'     => $mySwapsPerTournament,
        'takenBlocksPerTournament' => $takenBlocksPerTournament,
        'takenSwapsPerTournament'  => $takenSwapsPerTournament,
        'allSwapsPerTournament'    => $allSwapsPerTournament,
        'allBlocksPerTournament'   => $allBlocksPerTournament,
        'recapData'                => $recapData,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', \App\Http\Middleware\AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [\App\Http\Controllers\AdminController::class, 'index'])->name('users');
    Route::post('/users', [\App\Http\Controllers\AdminController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}/toggle-admin', [\App\Http\Controllers\AdminController::class, 'toggleAdmin'])->name('users.toggleAdmin');
    Route::patch('/users/{user}/password', [\App\Http\Controllers\AdminController::class, 'updatePassword'])->name('users.updatePassword');
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
    Route::post('/tournaments/{tournament}/toggle-bonuses', [TournamentController::class, 'toggleBonuses'])->name('tournaments.toggleBonuses');
    Route::post('/tournaments/{tournament}/snapshot', [TournamentController::class, 'snapshotNow'])->name('tournaments.snapshot');
    Route::post('/tournaments/{tournament}/backfill-stats', [TournamentController::class, 'backfillStats'])->name('tournaments.backfillStats');
    Route::post('/tournaments/{tournament}/recalculate-standings', [TournamentController::class, 'recalculateStandings'])->name('tournaments.recalculateStandings');
    Route::post('/tournaments/{tournament}/publish-recap', [TournamentController::class, 'publishRecap'])->name('tournaments.publishRecap');
    Route::post('/tournaments/{tournament}/init-recap', [TournamentController::class, 'initRecapBaseline'])->name('tournaments.initRecap');
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
        Route::delete('matches/{match}/result', [MatchController::class, 'cancelResult'])->name('matches.result.cancel');
        Route::patch('matches/{match}/schedule', [MatchController::class, 'updateSchedule'])->name('matches.schedule');
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
