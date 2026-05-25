<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = \App\Models\User::all();
foreach ($users as $u) {
    echo "ID:{$u->id} Email:{$u->email} Name:{$u->name}\n";
}

$tournaments = \App\Models\Tournament::with('members')->get();
foreach ($tournaments as $t) {
    echo "Tournament:{$t->id} Name:{$t->name} Status:{$t->status} Members:" . $t->members->pluck('name')->implode(',') . "\n";
}

$matches = \App\Models\Game::where('round','group')->where('status','scheduled')->with('homeTeam','awayTeam')->take(3)->get();
foreach ($matches as $m) {
    echo "Match:{$m->id} {$m->home_team?->name} vs {$m->away_team?->name} Round:{$m->round}\n";
}
