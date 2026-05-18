<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointBooster extends Model
{
    use HasFactory;

    public const MAX_BOOSTERS_PER_TOURNAMENT = 5;
    public const MULTIPLIER = 2;

    protected $fillable = [
        'user_id',
        'tournament_id',
        'match_id',
        'activated_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'match_id');
    }

    public function match(): BelongsTo
    {
        return $this->game();
    }

    public static function getRemainingBoostersCount(int $userId, int $tournamentId): int
    {
        $usedCount = static::where('user_id', $userId)
            ->where('tournament_id', $tournamentId)
            ->count();

        return max(0, static::MAX_BOOSTERS_PER_TOURNAMENT - $usedCount);
    }

    public static function hasBoosterForMatch(int $userId, int $matchId): bool
    {
        return static::where('user_id', $userId)
            ->where('match_id', $matchId)
            ->exists();
    }

    public static function canActivate(int $userId, int $tournamentId, Game $match): bool
    {
        if ($match->status !== 'scheduled') {
            return false;
        }

        if ($match->scheduled_at && $match->scheduled_at->isPast()) {
            return false;
        }

        return static::getRemainingBoostersCount($userId, $tournamentId) > 0;
    }
}
