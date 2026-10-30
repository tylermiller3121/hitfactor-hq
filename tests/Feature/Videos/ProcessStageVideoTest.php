<?php

use App\Jobs\ProcessStageVideo;
use App\Models\MatchResult;
use App\Models\Stage;
use App\Models\StageVideo;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

function pendingStageVideo(): StageVideo
{
    $match = MatchResult::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Test Match',
    ]);

    $stage = Stage::create([
        'match_result_id' => $match->id,
        'stage_number' => 1,
    ]);

    return StageVideo::create([
        'stage_id' => $stage->id,
        'original_path' => 'stage-videos/test.mp4',
    ]);
}

function ffprobeOutput(array $videoStream, array $format = []): string
{
    return json_encode([
        'streams' => [
            ['codec_type' => 'audio', 'codec_name' => 'aac'],
            ['codec_type' => 'video', ...$videoStream],
        ],
        'format' => $format,
    ]);
}

beforeEach(function () {
    Storage::fake('local');
});

it('stores metadata from ffprobe', function () {
    Process::fake(['*' => Process::result(ffprobeOutput([
        'codec_name' => 'h264',
        'width' => 1920,
        'height' => 1080,
        'avg_frame_rate' => '30000/1001',
        'duration' => '12.5125',
        'nb_read_frames' => '375',
    ]))]);

    $video = pendingStageVideo();

    (new ProcessStageVideo($video->id))->handle();

    $video->refresh();

    expect($video->processing_status)->toBe('completed')
        ->and($video->processing_error)->toBeNull()
        ->and($video->duration_ms)->toBe(12513)
        ->and((float) $video->frame_rate)->toBe(29.97)
        ->and($video->frame_count)->toBe(375)
        ->and($video->width)->toBe(1920)
        ->and($video->height)->toBe(1080)
        ->and($video->codec)->toBe('h264')
        ->and($video->metadata['streams'])->toHaveCount(2);
});

it('derives duration and frame count when the stream omits them', function () {
    Process::fake(['*' => Process::result(ffprobeOutput(
        ['avg_frame_rate' => '60/1'],
        ['duration' => '2.5'],
    ))]);

    $video = pendingStageVideo();

    (new ProcessStageVideo($video->id))->handle();

    $video->refresh();

    expect($video->duration_ms)->toBe(2500)
        ->and($video->frame_count)->toBe(150);
});

it('marks the video failed when ffprobe fails', function () {
    Process::fake(['*' => Process::result(
        errorOutput: 'Invalid data found',
        exitCode: 1,
    )]);

    $video = pendingStageVideo();

    expect(fn () => (new ProcessStageVideo($video->id))->handle())
        ->toThrow(RuntimeException::class, 'Invalid data found');

    $video->refresh();

    expect($video->processing_status)->toBe('failed')
        ->and($video->processing_error)->toBe('Invalid data found');
});

it('marks the video failed when the file has no video stream', function () {
    Process::fake(['*' => Process::result(json_encode([
        'streams' => [['codec_type' => 'audio']],
    ]))]);

    $video = pendingStageVideo();

    expect(fn () => (new ProcessStageVideo($video->id))->handle())
        ->toThrow(RuntimeException::class, 'No video stream');

    expect($video->refresh()->processing_status)->toBe('failed');
});

it('ignores videos that no longer exist', function () {
    Process::fake();

    (new ProcessStageVideo(999))->handle();

    Process::assertNothingRan();
});

it('fails a video left processing when the worker kills the job', function () {
    $video = pendingStageVideo();

    $video->update(['processing_status' => 'processing']);

    (new ProcessStageVideo($video->id))->failed(new RuntimeException('Timed out'));

    $video->refresh();

    expect($video->processing_status)->toBe('failed')
        ->and($video->processing_error)->toBe('Timed out');
});

it('keeps the original error when failed runs after the job handled it', function () {
    $video = pendingStageVideo();

    $video->update([
        'processing_status' => 'failed',
        'processing_error' => 'Invalid data found',
    ]);

    (new ProcessStageVideo($video->id))->failed(new RuntimeException('Rethrown'));

    expect($video->refresh()->processing_error)->toBe('Invalid data found');
});
