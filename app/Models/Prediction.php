<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
{
    use HasFactory;

    public const MAX_DOUBLED_PER_TOURNAMENT = 3;

    protected $fillable = [
        'user_id',
        'match_id',
        'home_score',
        'away_score',
        'points_earned',
        'result_type',
        'is_doubled',
    ];

    protected $casts = [
        'home_score' => 'integer',
        'away_score' => 'integer',
        'points_earned' => 'integer',
        'is_doubled' => 'boolean',
    ];

    public static function getDoubledCount(int $userId, int $tournamentId): int
    {
        return static::where('user_id', $userId)
            ->whereHas('game', fn($q) => $q->where('tournament_id', $tournamentId)->where('round', 'group'))
            ->where('is_doubled', true)
            ->count();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'match_id');
    }

    // Alias for backward compatibility
    public function match(): BelongsTo
    {
        return $this->game();
    }

    public function isExact(): bool
    {
        return $this->result_type === 'exact';
    }

    public function isCorrectWinner(): bool
    {
        return $this->result_type === 'correct_winner';
    }

    public function isWrong(): bool
    {
        return $this->result_type === 'wrong';
    }

    public function isPending(): bool
    {
        return $this->result_type === null;
    }

    public function getResultLabelAttribute(): string
    {
        return match ($this->result_type) {
            'exact' => 'Score exact',
            'correct_winner' => 'Bon vainqueur',
            'wrong' => 'Incorrect',
            default => 'En attente',
        };
    }
}
