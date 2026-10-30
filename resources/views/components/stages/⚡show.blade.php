<?php

use App\Models\Stage;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public Stage $stage;

    public function mount(Stage $stage): void
    {
        $stage->load(['matchResult', 'result', 'videos']);

        abort_unless(
            $stage->matchResult->user_id === Auth::id(),
            403
        );

        $this->stage = $stage;
    }
};

?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6">

    <div>
        <flux:button
            variant="ghost"
            icon="arrow-left"
            :href="route('matches.show', $stage->matchResult)"
            wire:navigate
        >
            {{ $stage->matchResult->name }}
        </flux:button>
    </div>

    <div>
        <div class="flex flex-wrap items-center gap-3">
            <flux:heading size="xl">
                Stage {{ $stage->stage_number }}
            </flux:heading>

            @if ($stage->is_classifier)
                <flux:badge>Classifier</flux:badge>
            @endif
        </div>

        @if ($stage->name)
            <flux:text class="mt-2">
                {{ $stage->name }}
            </flux:text>
        @endif

        @if ($stage->classifier_code)
            <flux:text class="mt-1">
                {{ $stage->classifier_code }}
            </flux:text>
        @endif
    </div>

    @if ($stage->result)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="text-sm text-zinc-500">Time</div>

                <div class="mt-2 text-2xl font-semibold">
                    {{ number_format((float) $stage->result->time, 2) }}s
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="text-sm text-zinc-500">Points</div>

                <div class="mt-2 text-2xl font-semibold">
                    {{ $stage->result->points }}
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="text-sm text-zinc-500">Hit Factor</div>

                <div class="mt-2 text-2xl font-semibold">
                    {{ number_format((float) $stage->result->hit_factor, 4) }}
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="text-sm text-zinc-500">Stage %</div>

                <div class="mt-2 text-2xl font-semibold">
                    @if ($stage->result->stage_percentage !== null)
                        {{ number_format((float) $stage->result->stage_percentage, 2) }}%
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">
                Scoring
            </flux:heading>

            <div class="mt-5 grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-6">
                <div>
                    <div class="text-sm text-zinc-500">Alpha</div>
                    <div class="mt-1 text-xl font-semibold">{{ $stage->result->alpha }}</div>
                </div>

                <div>
                    <div class="text-sm text-zinc-500">Charlie</div>
                    <div class="mt-1 text-xl font-semibold">{{ $stage->result->charlie }}</div>
                </div>

                <div>
                    <div class="text-sm text-zinc-500">Delta</div>
                    <div class="mt-1 text-xl font-semibold">{{ $stage->result->delta }}</div>
                </div>

                <div>
                    <div class="text-sm text-zinc-500">Mike</div>
                    <div class="mt-1 text-xl font-semibold">{{ $stage->result->mike }}</div>
                </div>

                <div>
                    <div class="text-sm text-zinc-500">No Shoot</div>
                    <div class="mt-1 text-xl font-semibold">{{ $stage->result->no_shoot }}</div>
                </div>

                <div>
                    <div class="text-sm text-zinc-500">Procedural</div>
                    <div class="mt-1 text-xl font-semibold">{{ $stage->result->procedural }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="lg">
                    Stage Videos
                </flux:heading>

                <flux:text class="mt-1">
                    POV and third-person footage for analysis.
                </flux:text>
            </div>

            <flux:button
                variant="primary"
                icon="arrow-up-tray"
                :href="route('videos.upload', $stage)"
                wire:navigate
            >
                Upload Video
            </flux:button>
        </div>

        @if (session('video-uploaded'))
            <div class="mt-5 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm">
                {{ session('video-uploaded') }}
            </div>
        @endif

        @if ($stage->videos->isEmpty())
            <div class="mt-6 rounded-lg border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                <flux:text>
                    No videos uploaded yet.
                </flux:text>
            </div>
        @else
            <div class="mt-6 grid gap-4">
                @foreach ($stage->videos as $video)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:heading>
                                        {{ $video->original_filename }}
                                    </flux:heading>

                                    <flux:badge>
                                        {{ strtoupper(str_replace('_', ' ', $video->video_type)) }}
                                    </flux:badge>

                                    <flux:badge>
                                        {{ ucfirst($video->processing_status) }}
                                    </flux:badge>
                                </div>

                                <div class="mt-2 text-sm text-zinc-500">
                                    @if ($video->size_bytes)
                                        {{ number_format($video->size_bytes / 1024 / 1024, 1) }} MB
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($video->notes)
                            <flux:text class="mt-3">
                                {{ $video->notes }}
                            </flux:text>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if ($stage->notes)
        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">
                Notes
            </flux:heading>

            <flux:text class="mt-3 whitespace-pre-line">
                {{ $stage->notes }}
            </flux:text>
        </div>
    @endif
</div>
