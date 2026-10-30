<?php

use App\Actions\ImportPractiScoreMatch;
use App\Exceptions\InvalidPractiScorePage;
use App\Models\MatchResult;
use App\Services\PractiScoreResultsParser;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Import Match')] class extends Component
{
    use WithFileUploads;

    public $page;

    public ?array $parsed = null;

    public ?int $existingMatchId = null;

    public function preview(PractiScoreResultsParser $parser): void
    {
        $this->validate([
            'page' => ['required', 'file', 'mimes:html,htm', 'max:5120'],
        ]);

        try {
            $parsed = $parser->parse((string) file_get_contents($this->page->getRealPath()));
        } catch (InvalidPractiScorePage $exception) {
            $this->addError('page', $exception->getMessage());

            return;
        }

        $this->parsed = $parsed;
        $this->existingMatchId = $this->findExistingMatchId();
    }

    public function save(ImportPractiScoreMatch $importer): void
    {
        abort_if($this->parsed === null, 422);

        if ($this->existingMatchId = $this->findExistingMatchId()) {
            return;
        }

        $match = $importer->handle(Auth::user(), $this->parsed);

        $this->redirectRoute(
            'matches.show',
            ['matchResult' => $match->id],
            navigate: true,
        );
    }

    public function startOver(): void
    {
        $this->reset();
    }

    private function findExistingMatchId(): ?int
    {
        $matchId = $this->parsed['match']['practiscore_match_id'] ?? null;

        if (! $matchId) {
            return null;
        }

        return MatchResult::where('user_id', Auth::id())
            ->where('practiscore_match_id', $matchId)
            ->value('id');
    }
};

?>

<div class="mx-auto w-full max-w-5xl">
    <div class="mb-6">
        <flux:button
            variant="ghost"
            icon="arrow-left"
            :href="route('matches.index')"
            wire:navigate
        >
            Matches
        </flux:button>
    </div>

    <div class="mb-8">
        <flux:heading size="xl">Import from PractiScore</flux:heading>

        <flux:text class="mt-1">
            Create a match and its stage results from a saved PractiScore page.
        </flux:text>
    </div>

    @if ($parsed === null)
        <form wire:submit="preview" class="space-y-6">
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading>Save your results page</flux:heading>

                <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-zinc-600 dark:text-zinc-400">
                    <li>Open the match results on PractiScore and select your name so your stage breakdown shows.</li>
                    <li>Press Ctrl+S and choose Webpage, HTML Only.</li>
                    <li>Upload that file here.</li>
                </ol>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Results Page
                </label>

                <input
                    type="file"
                    wire:model="page"
                    accept=".html,.htm,text/html"
                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 file:mr-4 file:rounded-md file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:file:bg-zinc-800 dark:hover:file:bg-zinc-700"
                />

                @error('page')
                <div class="mt-2 text-sm text-red-500">
                    {{ $message }}
                </div>
                @enderror
            </div>

            <div class="flex justify-end">
                <flux:button
                    type="submit"
                    variant="primary"
                    wire:loading.attr="disabled"
                    wire:target="preview,page"
                >
                    Review Import
                </flux:button>
            </div>
        </form>
    @else
        <div class="space-y-6">
            @if ($existingMatchId)
                <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm">
                    You already imported this match.
                    <a href="{{ route('matches.show', $existingMatchId) }}" class="font-medium underline" wire:navigate>
                        View it
                    </a>
                </div>
            @endif

            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">{{ $parsed['match']['name'] }}</flux:heading>

                <flux:text class="mt-1">
                    {{ collect([
                        $parsed['match']['match_date'],
                        $parsed['match']['club_name'],
                        $parsed['match']['division'],
                        $parsed['match']['classification'] ? 'Class '.$parsed['match']['classification'] : null,
                    ])->filter()->implode(' · ') }}
                </flux:text>

                @if ($parsed['shooter'])
                    <flux:text class="mt-1">
                        Shooter: {{ $parsed['shooter'] }}
                    </flux:text>
                @endif
            </div>

            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-zinc-500 dark:border-zinc-700">
                        <tr>
                            <th class="px-4 py-3 font-medium">Stage</th>
                            <th class="px-4 py-3 font-medium">Time</th>
                            <th class="px-4 py-3 font-medium">A</th>
                            <th class="px-4 py-3 font-medium">C</th>
                            <th class="px-4 py-3 font-medium">D</th>
                            <th class="px-4 py-3 font-medium">M</th>
                            <th class="px-4 py-3 font-medium">NS</th>
                            <th class="px-4 py-3 font-medium">Proc</th>
                            <th class="px-4 py-3 font-medium">Pts</th>
                            <th class="px-4 py-3 font-medium">HF</th>
                            <th class="px-4 py-3 font-medium">Stage %</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($parsed['stages'] as $stage)
                            <tr wire:key="stage-{{ $stage['stage_number'] }}" class="border-b border-zinc-200 last:border-0 dark:border-zinc-700">
                                <td class="px-4 py-3">
                                    {{ $stage['stage_number'] }}: {{ $stage['name'] }}

                                    @if ($stage['is_classifier'])
                                        <flux:badge size="sm">{{ $stage['classifier_code'] }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{ $stage['result']['time'] === null ? '—' : number_format($stage['result']['time'], 2).'s' }}

                                    @if ($stage['result']['source_data']['time_calculated'])
                                        <span class="text-xs text-zinc-500">calculated</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $stage['result']['alpha'] }}</td>
                                <td class="px-4 py-3">{{ $stage['result']['charlie'] }}</td>
                                <td class="px-4 py-3">{{ $stage['result']['delta'] }}</td>
                                <td class="px-4 py-3">{{ $stage['result']['mike'] }}</td>
                                <td class="px-4 py-3">{{ $stage['result']['no_shoot'] }}</td>
                                <td class="px-4 py-3">{{ $stage['result']['procedural'] }}</td>
                                <td class="px-4 py-3">{{ $stage['result']['points'] }}</td>
                                <td class="px-4 py-3">{{ number_format($stage['result']['hit_factor'], 4) }}</td>
                                <td class="px-4 py-3">{{ number_format($stage['result']['stage_percentage'], 2) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button wire:click="startOver">
                    Start Over
                </flux:button>

                @unless ($existingMatchId)
                    <flux:button
                        variant="primary"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                    >
                        Import Match
                    </flux:button>
                @endunless
            </div>
        </div>
    @endif
</div>
