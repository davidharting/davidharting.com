<x-slot:title>Create a scorecard</x-slot>
<x-slot:description>Track scores for a game</x-slot>

<div class="mt-8 md:w-2/3 xl:w-1/2">
    <form class="w-full space-y-8" wire:submit="create">
        <x-form.input
            name="title"
            wire:model="title"
            label="Title"
            required
            placeholder="Euchre with in-laws"
        />

        <x-form.input
            type="number"
            name="playerCount"
            label="Number of Players"
            wire:model.live="playerCount"
            min="1"
            max="10"
            required
        />

        <div class="mt-4 space-y-8">
            @for ($i = 0; $i < $playerCount; $i++)
                <x-form.input
                    type="text"
                    name="names.{{ $i }}"
                    label="Name of player {{ $i + 1 }}"
                    wire:model="names.{{ $i }}"
                    required
                />
            @endfor
        </div>

        <div class="flex justify-end">
            <button class="btn btn-primary" type="submit">
                Create Scorecard
            </button>
        </div>
    </form>
</div>
