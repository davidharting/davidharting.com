<x-layout.app cross-document-transitions>
    <article class="mx-auto mb-12 max-w-2xl">
        <a href="{{ $backUrl }}" class="link link-hover text-base-content/70 tap-target text-sm">← Words</a>

        <div class="mt-6">
            @include('words.partials.entry', ['word' => $word])
        </div>
    </article>
</x-layout.app>
