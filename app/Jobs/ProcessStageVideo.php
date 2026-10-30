<?php

namespace App\Jobs;

use App\Models\StageVideo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessStageVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(
        public int $stageVideoId
    ) {}

    public function handle(): void
    {
        $video = StageVideo::find($this->stageVideoId);

        if (! $video) {
            return;
        }

        $video->update([
            'processing_status' => 'processing',
            'processing_error' => null,
        ]);

        try {
            $absolutePath = Storage::disk($video->disk)
                ->path($video->original_path);

            $result = Process::timeout(120)->run([
                'ffprobe',
                '-v',
                'quiet',
                '-print_format',
                'json',
                '-show_format',
                '-show_streams',
                '-count_frames',
                $absolutePath,
            ]);

            if ($result->failed()) {
                throw new \RuntimeException(
                    trim($result->errorOutput()) ?: 'ffprobe failed.'
                );
            }

            $metadata = json_decode(
                $result->output(),
                true,
                flags: JSON_THROW_ON_ERROR
            );

            /** @var array<int, array<string, mixed>> $streams */
            $streams = $metadata['streams'] ?? [];

            $videoStream = collect($streams)
                ->firstWhere('codec_type', 'video');

            if (! $videoStream) {
                throw new \RuntimeException(
                    'No video stream was found in the uploaded file.'
                );
            }

            $frameRate = $this->parseFrameRate(
                $videoStream['avg_frame_rate']
                ?? $videoStream['r_frame_rate']
                ?? null
            );

            $durationSeconds =
                isset($videoStream['duration'])
                    ? (float) $videoStream['duration']
                    : (float) ($metadata['format']['duration'] ?? 0);

            $durationMs = $durationSeconds > 0
                ? (int) round($durationSeconds * 1000)
                : null;

            $frameCount = null;

            if (! empty($videoStream['nb_read_frames'])
                && is_numeric($videoStream['nb_read_frames'])) {
                $frameCount = (int) $videoStream['nb_read_frames'];
            } elseif (! empty($videoStream['nb_frames'])
                && is_numeric($videoStream['nb_frames'])) {
                $frameCount = (int) $videoStream['nb_frames'];
            } elseif ($durationSeconds > 0 && $frameRate) {
                $frameCount = (int) round(
                    $durationSeconds * $frameRate
                );
            }

            $video->update([
                'duration_ms' => $durationMs,
                'frame_rate' => $frameRate,
                'frame_count' => $frameCount,
                'width' => isset($videoStream['width'])
                    ? (int) $videoStream['width']
                    : null,
                'height' => isset($videoStream['height'])
                    ? (int) $videoStream['height']
                    : null,
                'codec' => $videoStream['codec_name'] ?? null,
                'metadata' => $metadata,
                'processing_status' => 'completed',
                'processing_error' => null,
            ]);
        } catch (Throwable $exception) {
            $video->update([
                'processing_status' => 'failed',
                'processing_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        StageVideo::whereKey($this->stageVideoId)
            ->where('processing_status', 'processing')
            ->update([
                'processing_status' => 'failed',
                'processing_error' => $exception?->getMessage()
                    ?? 'Video processing failed.',
            ]);
    }

    private function parseFrameRate(?string $value): ?float
    {
        if (! $value || $value === '0/0') {
            return null;
        }

        if (str_contains($value, '/')) {
            [$numerator, $denominator] = array_pad(
                explode('/', $value, 2),
                2,
                null
            );

            if (! is_numeric($numerator)
                || ! is_numeric($denominator)
                || (float) $denominator === 0.0) {
                return null;
            }

            return round(
                (float) $numerator / (float) $denominator,
                4
            );
        }

        return is_numeric($value)
            ? round((float) $value, 4)
            : null;
    }
}
