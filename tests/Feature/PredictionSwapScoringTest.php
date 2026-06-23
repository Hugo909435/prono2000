<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Prediction;
use App\Models\PredictionSwap;
use App\Models\Tournament;
use App\Models\User;
use App\Services\PredictionScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionSwapScoringTest extends TestCase
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

    private function makeMatch(Tournament $t, int $home, int $away): Game
    {
        return Game::create([
            'tournament_id' => $t->id,
            'round'         => 'group',
            'scheduled_at'  => '2026-06-15 20:00:00',
            'home_score'    => $home,
            'away_score'    => $away,
            'status'        => 'completed',
        ]);
    }

    private function pred(User $u, Game $g, int $home, int $away, bool $doubled = false): Prediction
    {
        return Prediction::create([
            'user_id'     => $u->id,
            'match_id'    => $g->id,
            'home_score'  => $home,
            'away_score'  => $away,
            'is_doubled'  => $doubled,
        ]);
    }

    private function pivot(Tournament $t, User $u): object
    {
        return $t->members()->where('users.id', $u->id)->first()->pivot;
    }

    public function test_swap_double_follows_player_onto_taken_prono_and_counters_follow(): void
    {
        $t = $this->makeTournament();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $t->addMember($a, 'admin');
        $t->addMember($b);

        // Match X : 2-0 réel | Match Y : 1-1 réel
        $matchX = $this->makeMatch($t, 2, 0);
        $matchY = $this->makeMatch($t, 1, 1);

        // A : X = 2-0 exact DOUBLÉ (12 pts, base 6) ; Y = 0-2 raté (0)
        $this->pred($a, $matchX, 2, 0, true);
        $this->pred($a, $matchY, 0, 2);

        // B : X = 1-0 bon résultat (2 pts) ; Y = 2-2 bon résultat (2 pts)
        $this->pred($b, $matchX, 1, 0);
        $this->pred($b, $matchY, 2, 2);

        // Échange : A (initiateur) donne X, prend le prono de B sur Y
        PredictionSwap::create([
            'tournament_id'      => $t->id,
            'initiator_user_id'  => $a->id,
            'target_user_id'     => $b->id,
            'initiator_match_id' => $matchX->id,
            'target_match_id'    => $matchY->id,
        ]);

        $scoring = app(PredictionScoringService::class);
        $scoring->processMatchResults($matchX->fresh());
        $scoring->processMatchResults($matchY->fresh());

        // A donne son prono doublé (X) : son ×2 le suit sur le prono pris (B sur Y,
        // bon résultat base 2) → 2 × 2 = 4. Le prono donné quitte A entièrement.
        $pa = $this->pivot($t, $a);
        $this->assertSame(4, (int) $pa->total_points, 'Total A');
        $this->assertSame(0, (int) $pa->exact_scores, 'A : exact (donné) retiré');
        $this->assertSame(1, (int) $pa->correct_results, 'A : bon résultat (pris de B) ajouté');
        $this->assertSame(1, (int) $pa->wrong_predictions, 'A : raté inchangé');

        // B : son X (2) + prend la base de A sur X (6, SANS le ×2 de A) = 8
        $pb = $this->pivot($t, $b);
        $this->assertSame(8, (int) $pb->total_points, 'Total B');
        $this->assertSame(1, (int) $pb->exact_scores, 'B : exact (pris de A) ajouté');
        $this->assertSame(1, (int) $pb->correct_results, 'B : un bon résultat donné, un gardé');
        $this->assertSame(0, (int) $pb->wrong_predictions, 'B : raté inchangé');
    }

    public function test_recalculate_standings_endpoint_refreshes_pivot(): void
    {
        $t = $this->makeTournament();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $t->addMember($a, 'admin');
        $t->addMember($b);

        $matchX = $this->makeMatch($t, 2, 0);
        $matchY = $this->makeMatch($t, 1, 1);
        $this->pred($a, $matchX, 2, 0, true);
        $this->pred($a, $matchY, 0, 2);
        $this->pred($b, $matchX, 1, 0);
        $this->pred($b, $matchY, 2, 2);

        PredictionSwap::create([
            'tournament_id'      => $t->id,
            'initiator_user_id'  => $a->id,
            'target_user_id'     => $b->id,
            'initiator_match_id' => $matchX->id,
            'target_match_id'    => $matchY->id,
        ]);

        // Pivot jamais calculé (aucune saisie de score passée par le contrôleur)
        $this->actingAs($a)
            ->post(route('tournaments.recalculateStandings', $t->id))
            ->assertRedirect();

        $this->assertSame(4, (int) $this->pivot($t, $a)->total_points);
        $this->assertSame(8, (int) $this->pivot($t, $b)->total_points);
    }

    public function test_recalculate_standings_is_admin_only(): void
    {
        $t = $this->makeTournament();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $t->addMember($a, 'admin');
        $t->addMember($b);

        $this->actingAs($b)
            ->post(route('tournaments.recalculateStandings', $t->id))
            ->assertForbidden();
    }

    public function test_no_swap_baseline_is_unchanged(): void
    {
        $t = $this->makeTournament();
        $a = User::factory()->create();
        $t->addMember($a, 'admin');

        $matchX = $this->makeMatch($t, 2, 0);
        $this->pred($a, $matchX, 2, 0, true); // exact doublé = 12

        $scoring = app(PredictionScoringService::class);
        $scoring->processMatchResults($matchX->fresh());

        $pa = $this->pivot($t, $a);
        $this->assertSame(12, (int) $pa->total_points);
        $this->assertSame(1, (int) $pa->exact_scores);
    }

    public function test_doubler_takes_exact_prono_scores_it_doubled_other_gets_no_double(): void
    {
        // Cas décrit par le produit : "Si A a doublé et prend le score exact de
        // l'autre, il prend 12 ; l'autre n'a pas de ×2 s'il ne l'a pas mis."
        $t = $this->makeTournament();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $t->addMember($a, 'admin');
        $t->addMember($b);

        // Match X : 2-0 réel | Match Y : 1-1 réel
        $matchX = $this->makeMatch($t, 2, 0);
        $matchY = $this->makeMatch($t, 1, 1);

        // A : X = 2-0 exact DOUBLÉ (12) ; Y = 2-0 raté (0, car Y réel = 1-1 nul)
        $this->pred($a, $matchX, 2, 0, true);
        $this->pred($a, $matchY, 2, 0);

        // B : X = 0-1 raté (0) ; Y = 1-1 EXACT non doublé (6)
        $this->pred($b, $matchX, 0, 1);
        $this->pred($b, $matchY, 1, 1);

        // Échange : A donne X (doublé), prend le prono EXACT de B sur Y
        PredictionSwap::create([
            'tournament_id'      => $t->id,
            'initiator_user_id'  => $a->id,
            'target_user_id'     => $b->id,
            'initiator_match_id' => $matchX->id,
            'target_match_id'    => $matchY->id,
        ]);

        $scoring = app(PredictionScoringService::class);
        $scoring->processMatchResults($matchX->fresh());
        $scoring->processMatchResults($matchY->fresh());

        // A : son ×2 le suit sur le prono pris (exact, base 6) → 6 × 2 = 12
        $pa = $this->pivot($t, $a);
        $this->assertSame(12, (int) $pa->total_points, 'A prend le prono exact, doublé → 12');
        $this->assertSame(1, (int) $pa->exact_scores, 'A : exact (pris de B)');

        // B : prend le prono exact de A sur X (base 6, SANS le ×2 de A), perd son
        // propre exact donné sur Y → 6
        $pb = $this->pivot($t, $b);
        $this->assertSame(6, (int) $pb->total_points, 'B prend la base de A (6), sans ×2');
        $this->assertSame(1, (int) $pb->exact_scores, 'B : exact pris de A sur X');
    }
}
