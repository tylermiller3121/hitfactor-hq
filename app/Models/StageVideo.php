<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageVideo extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $fillable = [
        'stage_id',
        'video_type',
        'disk',
        'original_path',
        'proxy_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'duration_ms',
        'frame_rate',
        'frame_count',
        'width',
        'height',
        'codec',
        'processing_status',
        'processing_error',
        'recorded_at',
        'metadata',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'duration_ms' => 'integer',
            'frame_rate' => 'decimal:4',
            'frame_count' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'recorded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Stage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
