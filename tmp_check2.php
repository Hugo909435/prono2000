<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check tournament 4 details
$t = \App\Models\Tournament::with('members')->find(4);
echo "Tournament: {$t->name}\n";
echo "predictions_open: " . ($t->predictions_open ? 'YES' : 'NO') . "\n";
echo "Status: {$t->status}\n\n";

// Matches for tournament 4
$matches = \App\Models\Game::where('tournament_id', 4)->with('homeTeam','awayTeam')->get();
foreach ($matches as $m) {
    $home = $m->homeTeam?->name ?? 'N/A';
    $away = $m->awayTeam?->name ?? 'N/A';
    echo "Match:{$m->id} Round:{$m->round} Status:{$m->status} Deadline:{$m->deadline_at} - {$home} vs {$away}\n";
}

// Predictions
$preds = \App\Models\Prediction::with('user','game')->whereHas('game', fn($q) => $q->where('tournament_id', 4))->get();
echo "\nPredictions:\n";
foreach ($preds as $p) {
    echo "  User:{$p->user->name} Match:{$p->match_id} Score:{$p->home_score}-{$p->away_score} Doubled:{$p->is_doubled}\n";
}
