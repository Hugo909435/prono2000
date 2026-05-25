<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentTopScorerPrediction extends Model
{
    protected $fillable = [
        'user_id',
        'tournament_id',
        'player_name',
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

    public function calculatePoints(?string $topScorerName): int
    {
        if (!$topScorerName) {
            return 0;
        }
        return strtolower(trim($this->player_name)) === strtolower(trim($topScorerName)) ? 15 : 0;
    }
}
