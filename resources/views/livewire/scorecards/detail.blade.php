<div class='drawer drawer-end' x-data='{ drawer: false }'>
    <input name='the-drawer' id="the-drawer" type="checkbox" class="drawer-toggle" wire:model='drawer' />
    <div class='drawer-content'>
        <x-card>
            <div class='flex justify-end'>
                <button class="btn btn-primary drawer-button w-auto" wire:click="openNewRoundForm">Record new
                    round</button>
            </div>
            <div class='overflow-x-auto'>
                <table class='table'>
                    <thead>
                        <tr>
                            <th>Round</th>
                            @foreach ($this->playerNames as $name)
                                <th>{{ $name }}</th>
                            @endforeach
                        </tr>
                    </thead>

                    @foreach ($this->rounds as $round)
                        <tr class='hover:bg-accent hover:bg-opacity-30 cursor-pointer' aria-label='edit row'
                            wire:click='openEditForm({{ $round[0] }})'>
                            @foreach ($round as $cell)
                                @if ($loop->first)
                                    <th>
                                        {{ $cell }}
                                    </th>
                                    @continue
                                @else
                                    <td>
                                        {{ $cell }}
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach

                    <tfoot>
                        <tr>
                            @foreach ($this->totals as $total)
                                <td>
                                    {{ $total }}
                                </td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-card>

        <div class='mt-12'>
            @if ($this->linkRequested)
                <p class='text-success'>
                    A link to this scorecard will be sent to {{ $this->email }}.
                </p>
            @else
                <details>
                    <summary class='cursor-pointer'>
                        Email yourself a link to this scorecard
                    </summary>
                    <form wire:submit='emailLink'>
                        <div class='flex flex-row items-end'>
                            <div class='w-1/2'>
                                <x-form.input name='email' type='email' wire:model='email'
                                    placeholder='me@example.com' required />
                            </div>
                            <button type='submit' class='btn btn-secondary btn-ghost ml-2'>Send</button>
                        </div>
                    </form>
                </details>
            @endif

        </div>
    </div>

    <div class='drawer-side'>
        <label for="the-drawer" aria-label="close sidebar" class="drawer-overlay" wire:click='closeDrawer'></label>
        <div class='min-h-full bg-base-200 p-4 text-base-content opacity-100 sm:min-w-[350px] min-w-[90%]'>
            @if ($drawer)
                <h2 class='font-serif mt-4 text-3xl'>
                    @if ($selectedRound)
                        Edit round
                    @else
                        Record new round
                    @endif
                </h2>
                <form class='mt-4 space-y-4' wire:submit="submit">
                    @foreach ($this->playerNames as $name)
                        <x-form.input name='newRoundScores.{{ $loop->index }}' :label='$name' type='number'
                            min='-100000' max='100000' wire:model="newRoundScores.{{ $loop->index }}" />
                    @endforeach
                    <div class='flex justify-end'>
                        <button type='submit' class='btn btn-primary w-3/4 mt-4'>
                            @if ($selectedRound)
                                Edit
                            @else
                                Add
                            @endif
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
