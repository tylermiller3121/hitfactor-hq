<?php

use App\Models\MatchResult;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public MatchResult $matchResult;

    public function mount(MatchResult $matchResult): void
    {
        abort_unless($matchResult->user_id === Auth::id(), 403);

        $this->matchResult = $matchResult;
    }

    public function with(): array
    {
        return [
            'stages' => $this->matchResult
                ->stages()
                ->with(['result', 'videos'])
                ->get(),
        ];
    }
};

?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
    <div>
        <flux:button
            variant="ghost"
            icon="arrow-left"
            :href="route('matches.index')"
            wire:navigate
        >
            Matches
        </flux:button>
    </div>

    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <flux:heading size="xl">
                {{ $matchResult->name }}
            </flux:heading>

            <div class="mt-3 flex flex-wrap gap-2">
                @if ($matchResult->division)
                    <flux:badge>{{ $matchResult->division }}</flux:badge>
                @endif

                @if ($matchResult->classification)
                    <flux:badge>
                        {{ $matchResult->classification }} Class
                    </flux:badge>
                @endif

                <flux:badge>{{ $matchResult->discipline }}</flux:badge>
            </div>

            <div class="mt-4 space-y-1 text-sm text-zinc-500">
                @if ($matchResult->match_date)
                    <div>{{ $matchResult->match_date->format('F j, Y') }}</div>
                @endif

                @if ($matchResult->club_name)
                    <div>{{ $matchResult->club_name }}</div>
                @endif

                @if ($matchResult->location)
                    <div>{{ $matchResult->location }}</div>
                @endif
            </div>
        </div>

        <flux:button
            variant="primary"
            icon="plus"
            :href="route('stages.create', $matchResult)"
            wire:navigate
        >
            Add Stage
        </flux:button>
    </div>

    <div class="border-t border-zinc-200 pt-6 dark:border-zinc-700">
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="lg">Stages</flux:heading>

            <flux:text>
                {{ $stages->count() }}
                {{ Str::plural('stage', $stages->count()) }}
            </flux:text>
        </div>

        @if ($stages->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-700">
                <flux:heading>No stages added</flux:heading>

                <flux:text class="mt-2">
                    Add the first stage from this match.
                </flux:text>

                <div class="mt-5">
                    <flux:button
                        variant="primary"
                        :href="route('stages.create', $matchResult)"
                        wire:navigate
                    >
                        Add Stage
                    </flux:button>
                </div>
            </div>
        @else
            <div class="grid gap-4">
                @foreach ($stages as $stage)
                    <a
                        href="{{ route('stages.show', $stage) }}"
                        wire:navigate
                        class="rounded-xl border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/60"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:heading size="lg">
                                        Stage {{ $stage->stage_number }}
                                    </flux:heading>

                                    @if ($stage->is_classifier)
                                        <flux:badge>Classifier</flux:badge>
                                    @endif
                                </div>

                                @if ($stage->name)
                                    <flux:text class="mt-1">
                                        {{ $stage->name }}
                                    </flux:text>
                                @endif
                            </div>

                            <flux:icon.chevron-right class="size-5 text-zinc-400" />
                        </div>

                        @if ($stage->result)
                            <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div>
                                    <div class="text-xs uppercase text-zinc-500">Time</div>
                                    <div class="mt-1 font-semibold">
                                        {{ number_format((float) $stage->result->time, 2) }}s
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs uppercase text-zinc-500">Hit Factor</div>
                                    <div class="mt-1 font-semibold">
                                        {{ number_format((float) $stage->result->hit_factor, 4) }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs uppercase text-zinc-500">Hits</div>
                                    <div class="mt-1 font-semibold">
                                        {{ $stage->result->alpha }}A /
                                        {{ $stage->result->charlie }}C /
                                        {{ $stage->result->delta }}D
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs uppercase text-zinc-500">Videos</div>
                                    <div class="mt-1 font-semibold">
                                        {{ $stage->videos->count() }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
