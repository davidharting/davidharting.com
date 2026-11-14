{{-- PROTOTYPE: shared end-of-session screen, shown when every due word has been graded or nothing was due. --}}
<div class="mx-auto flex max-w-md flex-col items-center gap-4 py-12 text-center">
    <div class="text-5xl">🌿</div>
    <h2 class="font-serif text-3xl">All caught up</h2>

    <template x-if="results.length > 0">
        <div class="flex flex-col gap-2">
            <p>You got <strong x-text="gotCount()"></strong> of <strong x-text="results.length"></strong>.</p>
            <template x-if="missedWords().length > 0">
                <p class="text-base-content/70">
                    You'll see
                    <span x-text="missedWords().join(', ')"></span>
                    again in a few minutes.
                </p>
            </template>
        </div>
    </template>

    <p class="text-base-content/70">The next word is due tomorrow morning.</p>

    <div class="flex flex-col gap-2">
        <button type="button" class="btn btn-outline" x-on:click="restart()">Practise ahead anyway</button>
        <a class="link text-sm" href="{{ route("home") }}">Back to the site</a>
    </div>
</div>
