{{-- Variant B: no separate editor. The "Yours" page of the month is always editable; status is the quiet line above the text, and Share is a two-tap button at the end. --}}
<div class="container mx-auto px-4">
    <main class="mt-8">
        <div class="mb-8 flex w-full flex-wrap justify-between font-sans text-sm opacity-70">
            <nav class="flex gap-3"><span>Home</span><span>Notes</span><span>Media Log</span><span>Pages</span></nav>
            <span>Memories</span>
        </div>

        <article class="mx-auto max-w-xl pb-24">
            <a class="link font-sans text-sm" href="{{ route('memories.index') }}">← All months</a>

            <x-type.page-title class="mt-8 text-5xl">{{ $month->label() }}</x-type.page-title>

            <nav class="mt-4 flex items-baseline gap-6 text-xl">
                <a href="{{ route('memories.show', $month->toYearMonth()) }}" class="opacity-50">
                    {{ $otherName }}
                    <span x-show="revealed" class="badge badge-primary badge-sm ml-1 align-middle font-sans">new</span>
                </a>
                <span class="underline decoration-2 underline-offset-8" aria-current="page">Yours</span>
            </nav>

            <section class="mt-10">
                <p class="font-sans text-sm">
                    <span class="opacity-60" x-text="shared ? 'Shared {{ $today }}' : 'Draft, started Oct 3'"></span>
                    <span x-show="status === 'saving'" class="opacity-60">· Saving…</span>
                    <span x-show="status === 'saved'" class="opacity-60">· Saved</span>
                    <span x-show="status === 'failed'" class="text-error">
                        · Not saved, you're offline.
                        <button type="button" class="link" @click="retry()">Try again</button>
                    </span>
                    <span x-show="status === 'conflict'" class="text-warning">· Changed on another device</span>
                </p>

                <div
                    x-show="status === 'conflict'"
                    role="alert"
                    class="bg-warning/20 rounded-box mx-0 mt-3 space-y-3 p-3 font-sans text-sm"
                >
                    <p>A newer version was saved from another device at 9:14 PM.</p>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-sm" @click="resolveConflict()">Load it</button>
                        <button type="button" class="btn btn-sm btn-ghost" @click="resolveConflict()">
                            Keep this one
                        </button>
                    </div>
                </div>

                <textarea
                    x-model="body"
                    @input="typed()"
                    x-init="
                        $nextTick(() => {
                            $el.style.height = $el.scrollHeight + 'px';
                        })
                    "
                    x-effect="
                        body;
                        $el.style.height = 'auto';
                        $el.style.height = Math.max($el.scrollHeight, 240) + 'px';
                    "
                    class="focus:bg-base-200 -mx-2 mt-5 block w-[calc(100%+1rem)] resize-none overflow-hidden rounded-lg bg-transparent px-2 py-1 text-lg leading-relaxed outline-none"
                    placeholder="Tap here and write what you want to remember about {{ $monthName }}."
                    aria-label="Your memory for {{ $monthName }}"
                ></textarea>

                <div class="mt-8 font-sans" x-show="! shared">
                    <div x-show="! confirming">
                        <p class="text-sm opacity-60">{{ $otherLine }}</p>
                        <button
                            type="button"
                            class="btn btn-primary mt-3"
                            :disabled="body.trim() === ''"
                            @click="confirming = true"
                        >
                            Share
                        </button>
                    </div>

                    <div x-show="confirming" class="bg-base-200 rounded-box p-4">
                        <p>
                            @if ($otherShared)
                                {{ $otherName }} has shared, so {{ $monthName }} reveals the moment you do.
                            @else
                                {{ $otherName }} can read it once you've both shared, or on {{ $deadline }}.
                            @endif
                            You can still edit it, but you can't unshare it.
                        </p>
                        <div class="mt-4 flex gap-2">
                            <button type="button" class="btn btn-primary" @click="share()">Share for good</button>
                            <button type="button" class="btn btn-ghost" @click="confirming = false">Cancel</button>
                        </div>
                    </div>
                </div>

                <div x-show="justShared" class="mt-8 font-sans">
                    <div x-show="! revealed" role="status" class="alert alert-soft">
                        Shared. {{ $monthName }} reveals when {{ $otherName }} shares, or on {{ $deadline }}.
                    </div>
                    <div x-show="revealed" role="status" class="alert alert-success alert-soft flex justify-between">
                        <span>{{ $monthName }} is revealed.</span>
                        <a class="link font-semibold" href="{{ route('memories.show', $month->toYearMonth()) }}"
                            >Read {{ $otherName }}'s →</a>
                    </div>
                </div>
            </section>
        </article>
    </main>
</div>
