<?php

use App\Models\MatchResult;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add Match')] class extends Component
{
    public string $name = '';
    public string $club_name = '';
    public string $match_date = '';
    public string $discipline = 'USPSA';
    public string $division = '';
    public string $classification = '';
    public string $location = '';
    public string $practiscore_url = '';
    public string $notes = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'club_name' => ['nullable', 'string', 'max:255'],
            'match_date' => ['nullable', 'date'],
            'discipline' => ['required', 'string', 'max:50'],
            'division' => ['nullable', 'string', 'max:100'],
            'classification' => ['nullable', 'string', 'max:10'],
            'location' => ['nullable', 'string', 'max:255'],
            'practiscore_url' => ['nullable', 'url', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $match = MatchResult::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        $this->redirectRoute(
            'matches.show',
            ['matchResult' => $match->id],
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
            :href="route('matches.index')"
            wire:navigate
        >
            Matches
        </flux:button>
    </div>

    <div class="mb-8">
        <flux:heading size="xl">Add Match</flux:heading>

        <flux:text class="mt-1">
            Enter the basic information for your match.
        </flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input
            wire:model="name"
            label="Match Name"
            placeholder="SFPS USPSA Match"
            required
        />

        <div class="grid gap-6 md:grid-cols-2">
            <flux:input
                wire:model="club_name"
                label="Club"
                placeholder="Sioux Falls Practical Shooters"
            />

            <flux:input
                wire:model="match_date"
                type="date"
                label="Match Date"
            />
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <flux:select wire:model="discipline" label="Discipline">
                <flux:select.option value="USPSA">USPSA</flux:select.option>
                <flux:select.option value="Steel Challenge">Steel Challenge</flux:select.option>
            </flux:select>

            <flux:select wire:model="division" label="Division">
                <flux:select.option value="">Select division</flux:select.option>
                <flux:select.option value="Carry Optics">Carry Optics</flux:select.option>
                <flux:select.option value="Limited Optics">Limited Optics</flux:select.option>
                <flux:select.option value="Production">Production</flux:select.option>
                <flux:select.option value="Open">Open</flux:select.option>
                <flux:select.option value="Limited">Limited</flux:select.option>
                <flux:select.option value="PCC">PCC</flux:select.option>
                <flux:select.option value="Single Stack">Single Stack</flux:select.option>
                <flux:select.option value="Revolver">Revolver</flux:select.option>
            </flux:select>

            <flux:select wire:model="classification" label="Class">
                <flux:select.option value="">Select class</flux:select.option>
                <flux:select.option value="U">U</flux:select.option>
                <flux:select.option value="D">D</flux:select.option>
                <flux:select.option value="C">C</flux:select.option>
                <flux:select.option value="B">B</flux:select.option>
                <flux:select.option value="A">A</flux:select.option>
                <flux:select.option value="M">M</flux:select.option>
                <flux:select.option value="GM">GM</flux:select.option>
            </flux:select>
        </div>

        <flux:input
            wire:model="location"
            label="Location"
            placeholder="Sioux Falls, SD"
        />

        <flux:input
            wire:model="practiscore_url"
            type="url"
            label="PractiScore URL"
            placeholder="https://practiscore.com/..."
        />

        <flux:textarea
            wire:model="notes"
            label="Notes"
            placeholder="Anything you want to remember about this match..."
            rows="4"
        />

        <div class="flex justify-end gap-3">
            <flux:button :href="route('matches.index')" wire:navigate>
                Cancel
            </flux:button>

            <flux:button type="submit" variant="primary" icon="check">
                Save Match
            </flux:button>
        </div>
    </form>
</div>
