{{--
    Every time David practised a word, newest first.
--}}
@props(['reviews'])

@php
    $misses = $reviews->where('rating', \App\Fsrs\Rating::Again)->count();
@endphp

<section aria-labelledby="practice-history">
    <h2 id="practice-history" class="text-base-content/70 text-sm tracking-wide uppercase">Practice</h2>

    @if ($reviews->isEmpty())
        <p class="text-base-content/70 mt-2">Not practised yet.</p>
    @else
        <p class="mt-2">
            {{ trans_choice("{1} Practised once|[2,*] Practised :count times", $reviews->count(), ["count" => number_format($reviews->count())]) }}, {{ trans_choice("{0} never missed|{1} missed once|[2,*] missed :count times", $misses, ["count" => number_format($misses)]) }}.
        </p>

        <ul class="list mt-2">
            @foreach ($reviews as $review)
                <li class="list-row px-1 py-2">
                    <time datetime="{{ $review->reviewed_at->toIso8601String() }}" class="text-base-content/70">
                        {{ $review->reviewed_at->format("j F Y") }}
                    </time>
                    <span class="{{ $review->rating === \App\Fsrs\Rating::Again ? "text-error" : "" }}">
                        {{ $review->outcome() }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
