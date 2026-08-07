<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Stage extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_result_id',
        'stage_number',
        'name',
        'is_classifier',
        'classifier_code',
        'minimum_rounds',
        'maximum_points',
        'stage_type',
        'sequence',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_classifier' => 'boolean',
            'stage_number' => 'integer',
            'minimum_rounds' => 'integer',
            'maximum_points' => 'integer',
            'sequence' => 'integer',
        ];
    }

    public function matchResult(): BelongsTo
    {
        return $this->belongsTo(MatchResult::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(StageResult::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(StageVideo::class);
    }
}
