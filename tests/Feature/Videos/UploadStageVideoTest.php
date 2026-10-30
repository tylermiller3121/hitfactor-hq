<?php

use App\Jobs\ProcessStageVideo;
use App\Models\MatchResult;
use App\Models\Stage;
use App\Models\StageVideo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();

    $this->user = User::factory()->create();

    $match = MatchResult::create([
        'user_id' => $this->user->id,
        'name' => 'Test Match',
    ]);

    $this->stage = Stage::create([
        'match_result_id' => $match->id,
        'stage_number' => 1,
    ]);
});

it('stores an uploaded video and queues processing', function () {
    Livewire::actingAs($this->user)
        ->test('videos.upload', ['stage' => $this->stage])
        ->set('video', UploadedFile::fake()->create('stage-1.mp4', 2048, 'video/mp4'))
        ->set('video_type', 'third_person')
        ->set('notes', 'Filmed by squadmate')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('stages.show', $this->stage));

    $video = StageVideo::sole();

    expect($video->stage_id)->toBe($this->stage->id)
        ->and($video->video_type)->toBe('third_person')
        ->and($video->original_filename)->toBe('stage-1.mp4')
        ->and($video->processing_status)->toBe('pending')
        ->and($video->notes)->toBe('Filmed by squadmate')
        ->and($video->original_path)->toStartWith("stage-videos/{$this->stage->id}/originals/");

    Storage::disk('local')->assertExists($video->original_path);

    Queue::assertPushed(
        ProcessStageVideo::class,
        fn (ProcessStageVideo $job) => $job->stageVideoId === $video->id
    );
});

it('rejects files that are not videos', function () {
    Livewire::actingAs($this->user)
        ->test('videos.upload', ['stage' => $this->stage])
        ->set('video', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('video');

    expect(StageVideo::count())->toBe(0);

    Queue::assertNothingPushed();
});

it('requires a file', function () {
    Livewire::actingAs($this->user)
        ->test('videos.upload', ['stage' => $this->stage])
        ->call('save')
        ->assertHasErrors(['video' => 'required']);
});

it('rejects unknown video types', function () {
    Livewire::actingAs($this->user)
        ->test('videos.upload', ['stage' => $this->stage])
        ->set('video', UploadedFile::fake()->create('stage-1.mp4', 2048, 'video/mp4'))
        ->set('video_type', 'drone')
        ->call('save')
        ->assertHasErrors('video_type');
});

it('blocks other users from uploading to a stage', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('videos.upload', ['stage' => $this->stage])
        ->assertForbidden();
});

it('blocks other users from viewing a stage', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('stages.show', ['stage' => $this->stage])
        ->assertForbidden();
});

it('lists uploaded videos on the stage page', function () {
    StageVideo::create([
        'stage_id' => $this->stage->id,
        'original_path' => 'stage-videos/test.mp4',
        'original_filename' => 'stage-1.mp4',
    ]);

    Livewire::actingAs($this->user)
        ->test('stages.show', ['stage' => $this->stage])
        ->assertSee('stage-1.mp4');
});
