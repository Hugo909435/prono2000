<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentLastPlacePrediction extends Model
{
    protected $fillable = [
        'user_id',
        'tournament_id',
        'predicted_user_id',
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

    public function predictedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'predicted_user_id');
    }

    public function calculatePoints(?int $lastPlaceUserId): int
    {
        if (!$lastPlaceUserId) {
            return 0;
        }
        return $this->predicted_user_id === $lastPlaceUserId ? 15 : 0;
    }
}
