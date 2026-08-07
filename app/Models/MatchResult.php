<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'club_name',
        'match_date',
        'discipline',
        'division',
        'classification',
        'location',
        'practiscore_url',
        'practiscore_match_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'match_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)
            ->orderBy('stage_number');
    }
}
