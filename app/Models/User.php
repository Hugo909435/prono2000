<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'is_admin',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Get the user's avatar URL.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    public function joinedTournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class, 'tournament_user')
            ->withPivot([
                'total_points',
                'exact_scores',
                'correct_results',
                'wrong_predictions',
                'role',
                'joined_at',
            ])
            ->withTimestamps();
    }

    public function winnerPredictions(): HasMany
    {
        return $this->hasMany(TournamentWinnerPrediction::class);
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
}
