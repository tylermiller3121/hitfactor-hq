<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage_id',
        'time',
        'alpha',
        'charlie',
        'delta',
        'mike',
        'no_shoot',
        'procedural',
        'points',
        'hit_factor',
        'stage_percentage',
        'overall_place',
        'division_place',
        'class_place',
        'stage_points',
        'source',
        'source_data',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'time' => 'decimal:3',
            'alpha' => 'integer',
            'charlie' => 'integer',
            'delta' => 'integer',
            'mike' => 'integer',
            'no_shoot' => 'integer',
            'procedural' => 'integer',
            'points' => 'integer',
            'hit_factor' => 'decimal:6',
            'stage_percentage' => 'decimal:3',
            'overall_place' => 'integer',
            'division_place' => 'integer',
            'class_place' => 'integer',
            'stage_points' => 'decimal:4',
            'source_data' => 'array',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
