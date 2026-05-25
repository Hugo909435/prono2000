<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionSwap extends Model
{
    protected $fillable = [
        'tournament_id',
        'initiator_user_id',
        'target_user_id',
        'initiator_match_id',
        'target_match_id',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_user_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function initiatorMatch(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'initiator_match_id');
    }

    public function targetMatch(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'target_match_id');
    }

    public static function getSwapByInitiator(int $initiatorUserId, int $targetUserId, int $tournamentId): ?self
    {
        return static::where('initiator_user_id', $initiatorUserId)
            ->where('target_user_id', $targetUserId)
            ->where('tournament_id', $tournamentId)
            ->first();
    }
}
