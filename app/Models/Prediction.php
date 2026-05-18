<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'match_id',
        'home_score',
        'away_score',
        'points_earned',
        'result_type',
    ];

    protected $casts = [
        'home_score' => 'integer',
        'away_score' => 'integer',
        'points_earned' => 'integer',
    ];

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
