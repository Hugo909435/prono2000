<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Ouvrir les pronostics du tournoi 4
\App\Models\Tournament::where('id', 4)->update(['predictions_open' => true]);

// Mettre des deadlines futures sur les matchs scheduled du tournoi 4
$future = now()->addDays(7);
\App\Models\Game::where('tournament_id', 4)
    ->where('status', 'scheduled')
    ->whereNull('deadline_at')
    ->update(['deadline_at' => $future, 'scheduled_at' => $future->copy()->addHours(2)]);

// S'assurer que Test User a des pronos pour quelques matchs
$testUser = \App\Models\User::find(1);
$predictions = [52 => [2,0], 53 => [1,1], 54 => [0,2]];
foreach ($predictions as $matchId => $score) {
    \App\Models\Prediction::updateOrCreate(
        ['user_id' => $testUser->id, 'match_id' => $matchId],
        ['home_score' => $score[0], 'away_score' => $score[1]]
    );
}

// S'assurer que Hugo a aussi des pronos
$hugo = \App\Models\User::find(2);
$predictions2 = [52 => [1,2], 53 => [2,0], 54 => [1,1]];
foreach ($predictions2 as $matchId => $score) {
    \App\Models\Prediction::updateOrCreate(
        ['user_id' => $hugo->id, 'match_id' => $matchId],
        ['home_score' => $score[0], 'away_score' => $score[1]]
    );
}

echo "Setup done!\n";
echo "Tournament 4 predictions_open: " . (\App\Models\Tournament::find(4)->predictions_open ? 'YES' : 'NO') . "\n";
echo "Matches with future deadline: " . \App\Models\Game::where('tournament_id', 4)->whereNotNull('deadline_at')->count() . "\n";
echo "Test User predictions: " . \App\Models\Prediction::where('user_id', 1)->whereIn('match_id', [52,53,54])->count() . "\n";
echo "Hugo predictions: " . \App\Models\Prediction::where('user_id', 2)->whereIn('match_id', [52,53,54])->count() . "\n";
