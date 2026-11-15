@php
    $senses = $word->senses();
    $fullDefinition = $word->renderFullDefinition();
@endphp

<header class="border-base-300 border-b pb-4">
    <h1
        class="w-fit max-w-full text-4xl font-bold wrap-anywhere sm:text-5xl"
        style="view-transition-name: headword-{{ $word->id }}; view-transition-class: headword"
    >
        {{ $word->word }}
    </h1>
    <p class="text-base-content/70 mt-2 text-sm">Added {{ $word->created_at->format("j F Y") }}</p>
</header>

<section aria-label="Short definition" class="mt-6 text-lg">
    @if (count($senses) > 1)
        <ol class="list-decimal space-y-2 pl-6">
            @foreach ($senses as $sense)
                <li>{{ $sense }}</li>
            @endforeach
        </ol>
    @else
        <p>{{ $senses[0] ?? $word->short_definition }}</p>
    @endif
</section>

@if ($fullDefinition)
    <section aria-labelledby="full-entry" class="mt-8">
        <h2 id="full-entry" class="text-base-content/70 text-sm tracking-wide uppercase">Full entry</h2>
        <div class="prose mt-2 max-w-none">{!! $fullDefinition !!}</div>
    </section>
@endif

<p class="mt-10">
    <a href="{{ route("filament.admin.resources.words.edit", $word) }}" class="btn btn-sm btn-outline tap-target"
        >Edit</a>
</p>
