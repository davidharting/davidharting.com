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
                    <td>
                        {{ $cell }}
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
</div>
