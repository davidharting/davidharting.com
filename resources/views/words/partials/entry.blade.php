@php
    $fullDefinition = $word->renderFullDefinition();
@endphp

<header class="border-base-300 border-b pb-4">
    <h1 class="text-4xl font-bold wrap-anywhere sm:text-5xl">{{ $word->word }}</h1>
    <p class="text-base-content/70 mt-2 text-sm">Added {{ $word->created_at->format("j F Y") }}</p>
</header>

<p class="mt-6 text-lg">{{ $word->short_definition }}</p>

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
