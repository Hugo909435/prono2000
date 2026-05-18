<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentWinnerPrediction extends Model
{
    protected $fillable = [
        'user_id',
        'tournament_id',
        'first_choice_team_id',
        'second_choice_team_id',
        'third_choice_team_id',
        'points_earned',
    ];

    protected $casts = [
        'points_earned' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function firstChoiceTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'first_choice_team_id');
    }

    public function secondChoiceTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'second_choice_team_id');
    }

    public function thirdChoiceTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'third_choice_team_id');
    }

    /**
     * Calcule les points gagnés en fonction du vainqueur réel
     */
    public function calculatePoints(?int $winnerTeamId): int
    {
        if (!$winnerTeamId) {
            return 0;
        }

        if ($this->first_choice_team_id === $winnerTeamId) {
            return 30;
        }

        if ($this->second_choice_team_id === $winnerTeamId) {
            return 20;
        }

        if ($this->third_choice_team_id === $winnerTeamId) {
            return 10;
        }

        return 0;
    }
}
