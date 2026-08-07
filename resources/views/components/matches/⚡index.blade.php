<?php

use App\Models\MatchResult;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Matches')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $matches = MatchResult::query()
            ->where('user_id', Auth::id())
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('club_name', 'like', '%'.$this->search.'%')
                        ->orWhere('location', 'like', '%'.$this->search.'%');
                });
            })
            ->withCount('stages')
            ->orderByDesc('match_date')
            ->orderByDesc('id')
            ->paginate(10);

        return [
            'matches' => $matches,
        ];
    }
};

?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">
                Matches
            </flux:heading>

            <flux:text class="mt-1">
                Track your USPSA matches, stages, results, and video analysis.
            </flux:text>
        </div>

        <flux:button
            variant="primary"
            icon="plus"
            :href="route('matches.create')"
            wire:navigate
        >
            Add Match
        </flux:button>
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        placeholder="Search matches..."
    />

    @if ($matches->isEmpty())
        <div class="rounded-xl border border-zinc-200 p-10 text-center dark:border-zinc-700">
            <flux:heading size="lg">
                No matches yet
            </flux:heading>

            <flux:text class="mt-2">
                Add your first match to start tracking your performance.
            </flux:text>

            <div class="mt-6">
                <flux:button
                    variant="primary"
                    :href="route('matches.create')"
                    wire:navigate
                >
                    Add Your First Match
                </flux:button>
            </div>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($matches as $match)
                    <a
                        href="{{ route('matches.show', $match) }}"
                        wire:navigate
                        class="flex items-center justify-between gap-6 p-5 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading size="lg">
                                    {{ $match->name }}
                                </flux:heading>

                                @if ($match->division)
                                    <flux:badge>
                                        {{ $match->division }}
                                    </flux:badge>
                                @endif
                            </div>

                            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-zinc-500">
                                @if ($match->match_date)
                                    <span>
                                        {{ $match->match_date->format('M j, Y') }}
                                    </span>
                                @endif

                                @if ($match->club_name)
                                    <span>
                                        {{ $match->club_name }}
                                    </span>
                                @endif

                                <span>
                                    {{ $match->stages_count }}
                                    {{ \Illuminate\Support\Str::plural('stage', $match->stages_count) }}
                                </span>
                            </div>
                        </div>

                        <flux:icon.chevron-right class="size-5 text-zinc-400" />
                    </a>
                @endforeach
            </div>
        </div>

        {{ $matches->links() }}
    @endif
</div>
