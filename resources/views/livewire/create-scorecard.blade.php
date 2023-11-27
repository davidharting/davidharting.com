<div class='mt-8 md:w-2/3 xl:w-1/2'>
    <form class='w-full space-y-8' wire:submit='create'>
        <x-form.input type='number' name='playerCount' label='Number of Players' wire:model.live='playerCount'
            min='1' max='10' required />

        <div class='mt-4 space-y-4'>
            <h2 class='text-2xl font-bold'>Player Names</h2>
            @for ($i = 0; $i < $playerCount; $i++)
                <x-form.input type='text' name='names.{{ $i }}' label='Player {{ $i + 1 }}'
                    wire:model='names.{{ $i }}' required />
            @endfor
        </div>

        <div class='flex justify-end'>
            <button class='btn btn-primary' type='submit'>Create Scorecard</button>
        </div>
    </form>
</div>
