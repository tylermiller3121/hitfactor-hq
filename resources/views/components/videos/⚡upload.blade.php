<?php

use App\Models\Stage;
use App\Models\StageVideo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Jobs\ProcessStageVideo;

new class extends Component
{
    use WithFileUploads;

    public Stage $stage;

    public $video;

    public string $video_type = 'pov';

    public string $notes = '';

    public function mount(Stage $stage): void
    {
        $stage->load('matchResult');

        abort_unless(
            $stage->matchResult->user_id === Auth::id(),
            403
        );

        $this->stage = $stage;
    }

    protected function rules(): array
    {
        return [
            'video' => [
                'required',
                'file',
                'mimes:mp4,mov,m4v,webm',
                'max:1048576',
            ],
            'video_type' => [
                'required',
                'in:pov,third_person,other',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $extension = strtolower(
            $this->video->getClientOriginalExtension()
        );

        $filename = Str::uuid().'.'.$extension;

        $path = $this->video->storeAs(
            'stage-videos/'.$this->stage->id.'/originals',
            $filename,
            'local'
        );

        $stageVideo = StageVideo::create([
            'stage_id' => $this->stage->id,
            'video_type' => $this->video_type,
            'disk' => 'local',
            'original_path' => $path,
            'original_filename' => $this->video->getClientOriginalName(),
            'mime_type' => $this->video->getMimeType(),
            'size_bytes' => $this->video->getSize(),
            'processing_status' => 'pending',
            'notes' => $this->notes ?: null,
        ]);

        ProcessStageVideo::dispatch($stageVideo->id);

        session()->flash(
            'video-uploaded',
            'Video uploaded successfully.'
        );

        $this->redirectRoute(
            'stages.show',
            ['stage' => $this->stage->id],
            navigate: true,
        );
    }
};

?>

<div class="mx-auto w-full max-w-3xl">
    <div class="mb-6">
        <flux:button
            variant="ghost"
            icon="arrow-left"
            :href="route('stages.show', $stage)"
            wire:navigate
        >
            Stage {{ $stage->stage_number }}
        </flux:button>
    </div>

    <div class="mb-8">
        <flux:heading size="xl">
            Upload Stage Video
        </flux:heading>

        <flux:text class="mt-1">
            Upload POV or third-person footage for Stage {{ $stage->stage_number }}.
        </flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:select
            wire:model="video_type"
            label="Video Type"
        >
            <flux:select.option value="pov">
                POV
            </flux:select.option>

            <flux:select.option value="third_person">
                Third Person
            </flux:select.option>

            <flux:select.option value="other">
                Other
            </flux:select.option>
        </flux:select>

        <div>
            <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Video File
            </label>

            <input
                type="file"
                wire:model="video"
                accept="video/mp4,video/quicktime,video/x-m4v,video/webm"
                class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 file:mr-4 file:rounded-md file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:file:bg-zinc-800 dark:hover:file:bg-zinc-700"
            />

            @error('video')
            <div class="mt-2 text-sm text-red-500">
                {{ $message }}
            </div>
            @enderror

            <div
                wire:loading
                wire:target="video"
                class="mt-2 text-sm text-zinc-500"
            >
                Preparing upload...
            </div>
        </div>

        @if ($video)
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>
                    Selected:
                    <span class="font-medium">
                        {{ $video->getClientOriginalName() }}
                    </span>
                </flux:text>
            </div>
        @endif

        <flux:textarea
            wire:model="notes"
            label="Notes"
            placeholder="Optional notes about this footage..."
            rows="4"
        />

        <div class="flex justify-end gap-3">
            <flux:button
                :href="route('stages.show', $stage)"
                wire:navigate
            >
                Cancel
            </flux:button>

            <flux:button
                type="submit"
                variant="primary"
                icon="arrow-up-tray"
                wire:loading.attr="disabled"
                wire:target="save,video"
            >
                Upload Video
            </flux:button>
        </div>
    </form>
</div>
