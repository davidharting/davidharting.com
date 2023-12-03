<div class='drawer drawer-end'>
    <input id="the-drawer" type="checkbox" class="drawer-toggle" />
    <div class='drawer-content'>
        <x-card>
            <div class='flex justify-end'>
                <label for="the-drawer" class="btn btn-primary drawer-button w-auto">Record new round</label>
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
                        <tr>
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
    </div>

    <div class='drawer-side'>
        <label for="the-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
        <div class='min-h-full bg-base-200 p-4 text-base-content opacity-100'>
            <h2 class='font-serif mt-4 text-3xl'>Record new round</h2>
            <form class='mt-4 space-y-4'>
                @foreach ($this->playerNames as $name)
                    <x-form.input name='player.{{ $loop->index }}' :label='$name' />
                @endforeach
                <div class='flex justify-end'>
                    <button type='submit' class='btn btn-primary w-3/4 mt-4'>Add</button>
                </div>
            </form>
        </div>
    </div>
</div>
