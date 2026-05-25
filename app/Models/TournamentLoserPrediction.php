<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentLoserPrediction extends Model
{
    protected $fillable = [
        'user_id',
        'tournament_id',
        'team_id',
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function calculatePoints(?int $loserTeamId): int
    {
        if (!$loserTeamId) {
            return 0;
        }
        return $this->team_id === $loserTeamId ? 15 : 0;
    }
}
