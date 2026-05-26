<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RankingSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'position',
        'total_points',
        'exact_scores',
        'correct_results',
        'football_day',
    ];

    protected $casts = [
        'position'       => 'integer',
        'total_points'   => 'integer',
        'exact_scores'   => 'integer',
        'correct_results'=> 'integer',
        'football_day'   => 'date',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calcule la "football_day" pour un timestamp donné.
     * Une journée foot va de 12h00 à 11h59 le lendemain.
     * Si heure >= 12h → football_day = date du jour
     * Sinon → football_day = date d'hier
     */
    public static function footballDayFor(\Carbon\Carbon $dt): string
    {
        return $dt->hour >= 12
            ? $dt->toDateString()
            : $dt->subDay()->toDateString();
    }

    /**
     * Retourne la football_day courante (journée en cours).
     */
    public static function currentFootballDay(): string
    {
        return static::footballDayFor(now());
    }

    /**
     * Retourne la dernière football_day COMPLÈTE (celle qui vient de se terminer).
     * Si maintenant >= 12h → la journée d'hier vient de se terminer ce matin à 11h59
     *   mais la journée courante a démarré → dernière complète = hier
     * Si maintenant < 12h → dernière complète = avant-hier
     */
    public static function lastCompleteFootballDay(): string
    {
        $now = now();
        if ($now->hour >= 12) {
            return $now->subDay()->toDateString();
        }
        return $now->subDays(2)->toDateString();
    }
}
