<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\TournamentLastPlacePrediction;
use App\Models\TournamentLoserPrediction;
use App\Models\TournamentTopScorerPrediction;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'format',
        'team_count',
        'group_count',
        'teams_per_group',
        'qualified_per_group',
        'status',
        'access_code',
        'predictions_open',
        'winner_predictions_locked',
        'winner_team_id',
        'loser_team_id',
        'top_scorer_name',
        'last_place_user_id',
    ];

    protected $casts = [
        'team_count' => 'integer',
        'group_count' => 'integer',
        'teams_per_group' => 'integer',
        'qualified_per_group' => 'integer',
        'predictions_open' => 'boolean',
        'winner_predictions_locked' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Tournament $tournament) {
            if (empty($tournament->access_code)) {
                $tournament->access_code = strtoupper(Str::random(8));
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function tournamentGroups(): HasMany
    {
        return $this->hasMany(TournamentGroup::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tournament_user')
            ->withPivot([
                'total_points',
                'exact_scores',
                'correct_results',
                'wrong_predictions',
                'role',
                'joined_at',
            ])
            ->withTimestamps()
            ->orderByPivot('total_points', 'desc')
            ->orderByPivot('exact_scores', 'desc');
    }

    public function winnerPredictions(): HasMany
    {
        return $this->hasMany(TournamentWinnerPrediction::class);
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function loserTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'loser_team_id');
    }

    public function loserPredictions(): HasMany
    {
        return $this->hasMany(TournamentLoserPrediction::class);
    }

    public function topScorerPredictions(): HasMany
    {
        return $this->hasMany(TournamentTopScorerPrediction::class);
    }

    public function lastPlacePredictions(): HasMany
    {
        return $this->hasMany(TournamentLastPlacePrediction::class);
    }

    public function lastPlaceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_place_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function isElimination(): bool
    {
        return $this->format === 'elimination';
    }

    public function hasGroups(): bool
    {
        return $this->format === 'groups_elimination';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function arePredictionsOpen(): bool
    {
        return $this->predictions_open && $this->isActive();
    }

    public function isMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function isAdmin(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->where('tournament_user.role', 'admin')->exists();
    }

    public function addMember(User $user, string $role = 'member'): void
    {
        if (!$this->isMember($user)) {
            $this->members()->attach($user->id, [
                'role' => $role,
                'joined_at' => now(),
            ]);
        }
    }

    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
    }
}
