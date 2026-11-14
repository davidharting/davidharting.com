<ul class="list">
    @foreach ($words as $word)
        <li class="list-row p-0">
            <div class="px-1 py-3">
                <span class="block text-xl font-semibold">{{ $word->word }}</span>
                <span class="text-base-content/70 line-clamp-2 block">{{ $word->short_definition }}</span>
            </div>
        </li>
    @endforeach
</ul>
