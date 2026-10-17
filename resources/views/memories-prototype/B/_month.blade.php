{{-- One month in the stream: the other person's memory first, then yours, like letters. --}}
@php
    $other = $m['other']->displayName();
    $empty = ! $m['mine'] && ! $m['theirs'];
@endphp
<section class="border-base-300 border-t pt-8">
    <div class="flex items-baseline justify-between gap-4">
        <h2 class="text-4xl">
            @if ($full)
                {{ $m['month']->label() }}
            @else
                <a
                    class="link link-hover"
                    href="{{ route('memories-prototype.show', ['month' => $m['slug'], 'variant' => $variant]) }}"
                >{{ $m['month']->label() }}</a>
            @endif
        </h2>
        <span class="font-sans text-xs tracking-wide uppercase opacity-60">{{ $m['revealed'] ? 'Revealed' : 'Waiting' }}</span>
    </div>

    @if ($empty && $m['revealed'])
        <p class="mt-3 italic opacity-70">
            Neither of you wrote about this month. <a class="link" href="#">Write yours</a>
        </p>
    @else
        @if (! $m['revealed'])
            <div class="bg-accent/20 rounded-box mt-4 p-4">
                <p class="text-lg">{{ $m['theirStatus'] }}.</p>
                <p class="mt-1 font-sans text-sm opacity-80">{{ $m['revealLine'] }}</p>
            </div>
        @endif

        @foreach ([[$other.' wrote', $m['theirs'], $m['photos']['theirs'], $m['theirsLate'], false], ['You wrote', $m['mine'], $m['photos']['mine'], $m['mineLate'], true]] as [$heading, $memory, $photos, $late, $isMine])
            @if ($memory && ($isMine || ($m['revealed'] && $memory->isShared())))
                <article class="mt-8 max-w-prose">
                    <h3 class="font-sans text-sm tracking-wide uppercase opacity-60">
                        {{ $heading }}
                        @if ($isMine && $memory->isDraft())
                            <span class="badge badge-warning badge-sm ml-1">draft, only you can see this</span>
                        @endif
                        @if ($late)
                            <span class="badge badge-accent badge-sm ml-1">shared late</span>
                        @endif
                    </h3>
                    <div class="mt-3 text-lg leading-relaxed">
                        @include('memories-prototype._body', ['memory' => $memory, 'limit' => $full ? null : 2])
                    </div>
                    @include('memories-prototype._photos', ['count' => $full ? $photos : min($photos, 1), 'layout' => 'stack'])
                    @if (! $full && $photos > 1)
                        <p class="mt-1 text-sm italic opacity-70">+{{ $photos - 1 }} more photos</p>
                    @endif
                    @include('memories-prototype._reactions', ['memory' => $memory])
                </article>
            @elseif (! $isMine && $m['revealed'])
                <p class="mt-6 italic opacity-70">{{ $other }} didn't share a memory this month.</p>
            @endif
        @endforeach

        @if ($m['cta'])
            <a class="btn btn-primary mt-6" href="#">{{ $m['cta'] }}</a>
        @endif
    @endif
</section>
