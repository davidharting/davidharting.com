<div class='overflow-x-auto'>
    <table class='table'>
        <thead>
            <tr>
                <th></th> {{-- Empty cell for round number --}}
                @foreach ($this->playerNames as $name)
                    <th>{{ $name }}</th>
                @endforeach
            </tr>
        </thead>
    </table>
</div>
