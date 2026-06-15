<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Recap;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecapFootballDayTest extends TestCase
{
    use RefreshDatabase;

    private function makeTournament(): Tournament
    {
        return Tournament::create([
            'user_id'     => User::factory()->create()->id,
            'name'        => 'Test Tournoi',
            'format'      => 'elimination',
            'team_count'  => 4,
            'status'      => 'active',
            'access_code' => 'ABCD1234',
        ]);
    }

    private function makeMatch(Tournament $t, string $scheduledAt, string $status = 'completed', ?string $recappedAt = null): Game
    {
        return Game::create([
            'tournament_id' => $t->id,
            'round'         => 'group',
            'scheduled_at'  => $scheduledAt,
            'home_score'    => 1,
            'away_score'    => 0,
            'status'        => $status,
            'recapped_at'   => $recappedAt,
        ]);
    }

    public function test_recap_includes_only_current_football_day_window(): void
    {
        // On clique le matin (09h) : journée foot = la veille, fenêtre = veille 12h → maintenant.
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00'));

        $admin = User::factory()->create();
        $tournament = $this->makeTournament();
        $tournament->addMember($admin, 'admin');

        // Dans la fenêtre [2026-06-14 12:00 → 2026-06-15 09:00]
        $eveningBefore = $this->makeMatch($tournament, '2026-06-14 21:00:00'); // soir de la veille → inclus
        $earlyMorning  = $this->makeMatch($tournament, '2026-06-15 02:00:00'); // nuit (même journée foot) → inclus

        // Hors fenêtre
        $beforeNoon    = $this->makeMatch($tournament, '2026-06-14 10:00:00'); // avant midi → journée foot précédente → exclu
        $oldMatch      = $this->makeMatch($tournament, '2026-06-11 20:00:00'); // il y a 3 jours → exclu
        $notCompleted  = $this->makeMatch($tournament, '2026-06-14 22:00:00', 'scheduled'); // pas terminé → exclu

        $this->actingAs($admin)
            ->post(route('tournaments.publishRecap', $tournament->id))
            ->assertRedirect();

        $recap = Recap::where('tournament_id', $tournament->id)->where('is_baseline', false)->firstOrFail();

        $includedIds = collect($recap->payload['matchIds'])->sort()->values()->all();

        $this->assertEquals(
            collect([$eveningBefore->id, $earlyMorning->id])->sort()->values()->all(),
            $includedIds,
            'Le récap doit inclure uniquement les matchs de la journée foot en cours (midi la veille → maintenant).'
        );

        // La journée foot stockée = la veille, pas la date calendaire du clic.
        $this->assertEquals('2026-06-14', $recap->football_day->toDateString());

        // Les matchs hors fenêtre ne sont pas marqués comme récappés.
        $this->assertNull($beforeNoon->fresh()->recapped_at);
        $this->assertNull($oldMatch->fresh()->recapped_at);
        $this->assertNull($notCompleted->fresh()->recapped_at);

        // Les matchs inclus sont bien marqués.
        $this->assertNotNull($eveningBefore->fresh()->recapped_at);
        $this->assertNotNull($earlyMorning->fresh()->recapped_at);

        Carbon::setTestNow();
    }

    public function test_recap_window_starts_at_noon_same_day_when_clicked_in_evening(): void
    {
        // On clique le soir (22h) : journée foot = aujourd'hui, fenêtre = aujourd'hui 12h → maintenant.
        Carbon::setTestNow(Carbon::parse('2026-06-15 22:00:00'));

        $admin = User::factory()->create();
        $tournament = $this->makeTournament();
        $tournament->addMember($admin, 'admin');

        $today      = $this->makeMatch($tournament, '2026-06-15 20:00:00'); // ce soir → inclus
        $thisMorning = $this->makeMatch($tournament, '2026-06-15 09:00:00'); // ce matin (<12h → journée d'hier) → exclu

        $this->actingAs($admin)
            ->post(route('tournaments.publishRecap', $tournament->id))
            ->assertRedirect();

        $recap = Recap::where('tournament_id', $tournament->id)->where('is_baseline', false)->firstOrFail();

        $this->assertEquals([$today->id], $recap->payload['matchIds']);
        $this->assertEquals('2026-06-15', $recap->football_day->toDateString());

        Carbon::setTestNow();
    }
}
