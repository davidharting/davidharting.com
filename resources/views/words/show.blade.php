<x-layout.app cross-document-transitions transition-direction="forward">
    <article class="mx-auto mb-12 max-w-2xl">
        <a href="{{ $backUrl }}" class="link link-hover text-base-content/70 tap-target text-sm">← Words</a>

        <div class="mt-6">
            <x-words.entry :word="$word" :status="$status" :reviews="$reviews" />
        </div>
    </article>
</x-layout.app>
