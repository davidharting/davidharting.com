@if ($memory->reactions->isNotEmpty())
    <div class="mt-3 flex flex-wrap gap-1">
        @foreach ($memory->reactions->groupBy('user_id') as $byUser)
            <span class="badge badge-ghost gap-1" title="{{ $byUser->first()->user->displayName() }}">
                {{ $byUser->pluck('emoji')->implode('') }}
                <span class="font-sans text-xs opacity-60">{{ $byUser->first()->user->displayName() }}</span>
            </span>
        @endforeach
    </div>
@endif
