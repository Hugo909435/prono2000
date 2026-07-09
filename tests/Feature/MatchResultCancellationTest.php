<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Prediction;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchResultCancellationTest extends TestCase
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

    private function pivot(Tournament $t, User $u): object
    {
        return $t->members()->where('users.id', $u->id)->first()->pivot;
    }

    public function test_admin_can_cancel_a_completed_result_and_points_are_removed(): void
    {
        $t = $this->makeTournament();
        $admin = User::factory()->create();
        $player = User::factory()->create();
        $t->addMember($admin, 'admin');
        $t->addMember($player);

        $match = Game::create([
            'tournament_id' => $t->id,
            'round'         => 'group',
            'scheduled_at'  => '2026-06-15 20:00:00',
            'home_score'    => 2,
            'away_score'    => 0,
            'status'        => 'completed',
        ]);

        $prediction = Prediction::create([
            'user_id'    => $player->id,
            'match_id'   => $match->id,
            'home_score' => 2,
            'away_score' => 0,
        ]);

        // Simule le calcul des points comme le ferait updateResult()
        app(\App\Services\PredictionScoringService::class)->processMatchResults($match->fresh());

        $this->assertSame(6, (int) $this->pivot($t, $player)->total_points);
        $this->assertSame(6, (int) $prediction->fresh()->points_earned);
        $this->assertSame('exact', $prediction->fresh()->result_type);

        $response = $this->actingAs($admin)
            ->delete(route('tournaments.matches.result.cancel', [$t->id, $match->id]));

        $response->assertRedirect(route('tournaments.show', $t->id));

        // Les points sont retires du classement du tournoi
        $this->assertSame(0, (int) $this->pivot($t, $player)->total_points);

        // Le prono n'a plus de points ni de type de resultat
        $prediction->refresh();
        $this->assertNull($prediction->points_earned);
        $this->assertNull($prediction->result_type);

        // Le match repasse en attente de resultat
        $match->refresh();
        $this->assertSame('scheduled', $match->status);
        $this->assertNull($match->home_score);
        $this->assertNull($match->away_score);
    }

    public function test_cancel_result_is_admin_only(): void
    {
        $t = $this->makeTournament();
        $player = User::factory()->create();
        $t->addMember($player);

        $match = Game::create([
            'tournament_id' => $t->id,
            'round'         => 'group',
            'scheduled_at'  => '2026-06-15 20:00:00',
            'home_score'    => 2,
            'away_score'    => 0,
            'status'        => 'completed',
        ]);

        $this->actingAs($player)
            ->delete(route('tournaments.matches.result.cancel', [$t->id, $match->id]))
            ->assertForbidden();

        $match->refresh();
        $this->assertSame('completed', $match->status);
    }

    public function test_cancel_result_on_a_match_without_result_is_a_noop(): void
    {
        $t = $this->makeTournament();
        $admin = User::factory()->create();
        $t->addMember($admin, 'admin');

        $match = Game::create([
            'tournament_id' => $t->id,
            'round'         => 'group',
            'scheduled_at'  => '2026-06-15 20:00:00',
            'status'        => 'scheduled',
        ]);

        $this->actingAs($admin)
            ->delete(route('tournaments.matches.result.cancel', [$t->id, $match->id]))
            ->assertRedirect();

        $match->refresh();
        $this->assertSame('scheduled', $match->status);
    }

    public function test_cancel_result_recalculates_group_standings(): void
    {
        $t = $this->makeTournament();
        $admin = User::factory()->create();
        $t->addMember($admin, 'admin');

        $group = TournamentGroup::create([
            'tournament_id' => $t->id,
            'name' => 'Groupe A',
        ]);

        $home = $t->teams()->create(['name' => 'Equipe Domicile']);
        $away = $t->teams()->create(['name' => 'Equipe Exterieur']);
        $group->teams()->attach([$home->id, $away->id]);

        $match = Game::create([
            'tournament_id' => $t->id,
            'tournament_group_id' => $group->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'round'         => 'group',
            'scheduled_at'  => '2026-06-15 20:00:00',
            'home_score'    => 2,
            'away_score'    => 0,
            'status'        => 'completed',
        ]);

        app(\App\Services\PredictionScoringService::class)->updateGroupTeamStats($match);

        $homePivotBefore = $group->teams()->where('teams.id', $home->id)->first()->pivot;
        $this->assertSame(3, (int) $homePivotBefore->points);
        $this->assertSame(1, (int) $homePivotBefore->played);

        $this->actingAs($admin)
            ->delete(route('tournaments.matches.result.cancel', [$t->id, $match->id]))
            ->assertRedirect();

        $homePivotAfter = $group->teams()->where('teams.id', $home->id)->first()->pivot;
        $this->assertSame(0, (int) $homePivotAfter->points, 'Les points de poule doivent etre retires');
        $this->assertSame(0, (int) $homePivotAfter->played, 'Le match ne doit plus compter comme joue');
    }
}
