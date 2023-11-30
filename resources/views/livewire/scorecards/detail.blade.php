<x-card>
    <div class='overflow-x-auto'>
        <table class='table table-pin-rows table-pin-cols'>
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
