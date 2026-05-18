<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'tournament_id',
        'tournament_group_id',
        'home_team_id',
        'away_team_id',
        'round',
        'match_number',
        'scheduled_at',
        'deadline_at',
        'home_score',
        'away_score',
        'home_score_penalties',
        'away_score_penalties',
        'status',
        'placeholder_home',
        'placeholder_away',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'deadline_at' => 'datetime',
        'match_number' => 'integer',
        'home_score' => 'integer',
        'away_score' => 'integer',
        'home_score_penalties' => 'integer',
        'away_score_penalties' => 'integer',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function tournamentGroup(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'match_id');
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canPredict(): bool
    {
        return $this->tournament->arePredictionsOpen() && $this->isScheduled();
    }

    public function getWinner(): ?Team
    {
        if (!$this->isCompleted()) {
            return null;
        }

        $homeTotal = $this->home_score + ($this->home_score_penalties ?? 0);
        $awayTotal = $this->away_score + ($this->away_score_penalties ?? 0);

        if ($homeTotal > $awayTotal) {
            return $this->homeTeam;
        }

        if ($awayTotal > $homeTotal) {
            return $this->awayTeam;
        }

        return null;
    }

    public function getHomeDisplayAttribute(): string
    {
        return $this->homeTeam?->name ?? $this->placeholder_home ?? 'À déterminer';
    }

    public function getAwayDisplayAttribute(): string
    {
        return $this->awayTeam?->name ?? $this->placeholder_away ?? 'À déterminer';
    }

    public function getRoundLabelAttribute(): string
    {
        $labels = [
            'group' => 'Phase de poules',
            'round_of_32' => '32èmes de finale',
            'round_of_16' => '8èmes de finale',
            'quarter' => 'Quarts de finale',
            'semi' => 'Demi-finales',
            'third_place' => 'Match pour la 3ème place',
            'final' => 'Finale',
        ];

        return $labels[$this->round] ?? $this->round;
    }
}
