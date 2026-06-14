<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recap extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'published_by',
        'football_day',
        'payload',
        'is_baseline',
        'published_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'football_day' => 'date',
        'is_baseline'  => 'boolean',
        'published_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
