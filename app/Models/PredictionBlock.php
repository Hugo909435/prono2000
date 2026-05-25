<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionBlock extends Model
{
    protected $fillable = [
        'tournament_id',
        'blocker_user_id',
        'target_user_id',
        'target_match_id',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_user_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetMatch(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'target_match_id');
    }

    public static function isBlocked(int $userId, int $matchId): bool
    {
        return static::where('target_user_id', $userId)
            ->where('target_match_id', $matchId)
            ->exists();
    }

    public static function getBlockByBlocker(int $blockerUserId, int $targetUserId, int $tournamentId): ?self
    {
        return static::where('blocker_user_id', $blockerUserId)
            ->where('target_user_id', $targetUserId)
            ->where('tournament_id', $tournamentId)
            ->first();
    }
}
