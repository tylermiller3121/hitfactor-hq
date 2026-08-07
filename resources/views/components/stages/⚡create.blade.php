<?php

use App\Models\MatchResult;
use App\Models\Stage;
use App\Models\StageResult;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add Stage')] class extends Component
{
    public MatchResult $matchResult;

    public int $stage_number = 1;
    public string $name = '';
    public bool $is_classifier = false;
    public string $classifier_code = '';
    public string $minimum_rounds = '';
    public string $maximum_points = '';

    public string $time = '';
    public int $alpha = 0;
    public int $charlie = 0;
    public int $delta = 0;
    public int $mike = 0;
    public int $no_shoot = 0;
    public int $procedural = 0;

    public string $stage_percentage = '';
    public string $overall_place = '';
    public string $division_place = '';
    public string $class_place = '';

    public string $notes = '';

    public function mount(MatchResult $matchResult): void
    {
        abort_unless($matchResult->user_id === Auth::id(), 403);

        $this->matchResult = $matchResult;

        $this->stage_number =
            ((int) $matchResult->stages()->max('stage_number')) + 1;
    }

    protected function rules(): array
    {
        return [
            'stage_number' => [
                'required',
                'integer',
                'min:1',
                'unique:stages,stage_number,NULL,id,match_result_id,'.$this->matchResult->id,
            ],

            'name' => ['nullable', 'string', 'max:255'],
            'is_classifier' => ['boolean'],
            'classifier_code' => ['nullable', 'string', 'max:50'],
            'minimum_rounds' => ['nullable', 'integer', 'min:1'],
            'maximum_points' => ['nullable', 'integer', 'min:1'],

            'time' => ['nullable', 'numeric', 'min:0.001'],

            'alpha' => ['required', 'integer', 'min:0'],
            'charlie' => ['required', 'integer', 'min:0'],
            'delta' => ['required', 'integer', 'min:0'],
            'mike' => ['required', 'integer', 'min:0'],
            'no_shoot' => ['required', 'integer', 'min:0'],
            'procedural' => ['required', 'integer', 'min:0'],

            'stage_percentage' => ['nullable', 'numeric', 'min:0'],
            'overall_place' => ['nullable', 'integer', 'min:1'],
            'division_place' => ['nullable', 'integer', 'min:1'],
            'class_place' => ['nullable', 'integer', 'min:1'],

            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $stage = DB::transaction(function () use ($validated) {
            $stage = Stage::create([
                'match_result_id' => $this->matchResult->id,
                'stage_number' => $validated['stage_number'],
                'name' => $validated['name'] ?: null,
                'is_classifier' => $validated['is_classifier'],
                'classifier_code' => $validated['classifier_code'] ?: null,
                'minimum_rounds' => $validated['minimum_rounds'] ?: null,
                'maximum_points' => $validated['maximum_points'] ?: null,
                'sequence' => $validated['stage_number'],
                'notes' => $validated['notes'] ?: null,
            ]);

            $points = $this->calculatePoints();

            $hitFactor = null;

            if ($validated['time'] !== '') {
                $time = (float) $validated['time'];

                if ($time > 0) {
                    $hitFactor = $points / $time;
                }
            }

            StageResult::create([
                'stage_id' => $stage->id,
                'time' => $validated['time'] ?: null,
                'alpha' => $validated['alpha'],
                'charlie' => $validated['charlie'],
                'delta' => $validated['delta'],
                'mike' => $validated['mike'],
                'no_shoot' => $validated['no_shoot'],
                'procedural' => $validated['procedural'],
                'points' => $points,
                'hit_factor' => $hitFactor,
                'stage_percentage' => $validated['stage_percentage'] ?: null,
                'overall_place' => $validated['overall_place'] ?: null,
                'division_place' => $validated['division_place'] ?: null,
                'class_place' => $validated['class_place'] ?: null,
                'source' => 'manual',
            ]);

            return $stage;
        });

        $this->redirectRoute(
            'stages.show',
            ['stage' => $stage->id],
            navigate: true,
        );
    }

    private function calculatePoints(): int
    {
        /*
         * Initial USPSA Minor scoring implementation.
         *
         * A = 5
         * C = 3
         * D = 1
         * Mike = -10
         * No Shoot = -10
         * Procedural = -10
         *
         * We'll move scoring into a dedicated scoring service
         * before supporting multiple power factors/disciplines.
         */

        return
            ($this->alpha * 5)
            + ($this->charlie * 3)
            + ($this->delta * 1)
            - ($this->mike * 10)
            - ($this->no_shoot * 10)
            - ($this->procedural * 10);
    }
};

?>

<div class="mx-auto w-full max-w-4xl">
    <div class="mb-6">
        <flux:button
            variant="ghost"
            icon="arrow-left"
            :href="route('matches.show', $matchResult)"
            wire:navigate
        >
            {{ $matchResult->name }}
        </flux:button>
    </div>

    <div class="mb-8">
        <flux:heading size="xl">
            Add Stage
        </flux:heading>

        <flux:text class="mt-1">
            Enter the stage information and your result.
        </flux:text>
    </div>

    <form wire:submit="save" class="space-y-8">

        <section class="space-y-5">
            <flux:heading size="lg">Stage Information</flux:heading>

            <div class="grid gap-6 md:grid-cols-2">
                <flux:input
                    wire:model="stage_number"
                    type="number"
                    min="1"
                    label="Stage Number"
                    required
                />

                <flux:input
                    wire:model="name"
                    label="Stage Name"
                    placeholder="It's All Porta the Plan"
                />
            </div>

            <flux:checkbox
                wire:model.live="is_classifier"
                label="This stage is a classifier"
            />

            @if ($is_classifier)
                <flux:input
                    wire:model="classifier_code"
                    label="Classifier Code"
                    placeholder="Classifier number"
                />
            @endif

            <div class="grid gap-6 md:grid-cols-2">
                <flux:input
                    wire:model="minimum_rounds"
                    type="number"
                    min="1"
                    label="Minimum Rounds"
                />

                <flux:input
                    wire:model="maximum_points"
                    type="number"
                    min="1"
                    label="Maximum Points"
                />
            </div>
        </section>

        <div class="border-t border-zinc-200 dark:border-zinc-700"></div>

        <section class="space-y-5">
            <div>
                <flux:heading size="lg">Stage Result</flux:heading>

                <flux:text class="mt-1">
                    Enter the score directly from your match results.
                </flux:text>
            </div>

            <flux:input
                wire:model="time"
                type="number"
                step="0.001"
                min="0"
                label="Time (seconds)"
                placeholder="15.63"
            />

            <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                <flux:input wire:model="alpha" type="number" min="0" label="Alpha" />
                <flux:input wire:model="charlie" type="number" min="0" label="Charlie" />
                <flux:input wire:model="delta" type="number" min="0" label="Delta" />
                <flux:input wire:model="mike" type="number" min="0" label="Mike" />
                <flux:input wire:model="no_shoot" type="number" min="0" label="No Shoot" />
                <flux:input wire:model="procedural" type="number" min="0" label="Procedural" />
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <flux:input
                    wire:model="stage_percentage"
                    type="number"
                    step="0.001"
                    min="0"
                    label="Stage %"
                    placeholder="57.070"
                />

                <flux:input
                    wire:model="overall_place"
                    type="number"
                    min="1"
                    label="Overall Place"
                    placeholder="31"
                />
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <flux:input
                    wire:model="division_place"
                    type="number"
                    min="1"
                    label="Division Place"
                />

                <flux:input
                    wire:model="class_place"
                    type="number"
                    min="1"
                    label="Class Place"
                />
            </div>
        </section>

        <div class="border-t border-zinc-200 dark:border-zinc-700"></div>

        <flux:textarea
            wire:model="notes"
            label="Stage Notes"
            placeholder="Movement, steel, mistakes, stage plan, things to work on..."
            rows="4"
        />

        <div class="flex justify-end gap-3">
            <flux:button
                :href="route('matches.show', $matchResult)"
                wire:navigate
            >
                Cancel
            </flux:button>

            <flux:button
                type="submit"
                variant="primary"
                icon="check"
            >
                Save Stage
            </flux:button>
        </div>
    </form>
</div>
