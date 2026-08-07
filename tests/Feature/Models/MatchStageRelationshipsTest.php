<?php

use App\Models\MatchResult;
use App\Models\Stage;
use App\Models\StageResult;
use App\Models\StageVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows a user to own matches with stages results and videos', function () {
    $user = User::factory()->create();

    $match = MatchResult::create([
        'user_id' => $user->id,
        'name' => 'SFPS USPSA Match',
        'club_name' => 'Sioux Falls Practical Shooters',
        'match_date' => '2026-07-26',
        'discipline' => 'USPSA',
        'division' => 'Carry Optics',
        'classification' => 'C',
    ]);

    $stage = Stage::create([
        'match_result_id' => $match->id,
        'stage_number' => 1,
        'name' => "It's All Porta the Plan",
        'is_classifier' => true,
        'classifier_code' => '25-05',
        'minimum_rounds' => 16,
        'maximum_points' => 80,
    ]);

    $result = StageResult::create([
        'stage_id' => $stage->id,
        'time' => 13.960,
        'alpha' => 14,
        'charlie' => 2,
        'points' => 78,
        'hit_factor' => 5.5882,
        'stage_percentage' => 57.920,
    ]);

    $video = StageVideo::create([
        'stage_id' => $stage->id,
        'video_type' => 'pov',
        'disk' => 'local',
        'original_path' => 'stage-videos/test.mp4',
        'original_filename' => 'stage-1.mp4',
        'mime_type' => 'video/mp4',
        'processing_status' => 'pending',
    ]);

    expect($user->matchResults)->toHaveCount(1)
        ->and($user->matchResults->first()->is($match))->toBeTrue();

    expect($match->stages)->toHaveCount(1)
        ->and($match->stages->first()->is($stage))->toBeTrue();

    expect($stage->matchResult->is($match))->toBeTrue();

    expect($stage->result)->not->toBeNull()
        ->and($stage->result->is($result))->toBeTrue();

    expect($stage->videos)->toHaveCount(1)
        ->and($stage->videos->first()->is($video))->toBeTrue();

    expect($result->stage->is($stage))->toBeTrue();
    expect($video->stage->is($stage))->toBeTrue();
});

it('deletes dependent stages results and videos when a match is deleted', function () {
    $user = User::factory()->create();

    $match = MatchResult::create([
        'user_id' => $user->id,
        'name' => 'Test Match',
    ]);

    $stage = Stage::create([
        'match_result_id' => $match->id,
        'stage_number' => 1,
    ]);

    StageResult::create([
        'stage_id' => $stage->id,
    ]);

    StageVideo::create([
        'stage_id' => $stage->id,
        'original_path' => 'stage-videos/test.mp4',
    ]);

    $match->delete();

    expect(MatchResult::count())->toBe(0)
        ->and(Stage::count())->toBe(0)
        ->and(StageResult::count())->toBe(0)
        ->and(StageVideo::count())->toBe(0);
});
