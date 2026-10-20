{{-- Variant A: Write opens its own full-screen page. Status sits in the top bar; Share is a sticky button that opens a bottom sheet. --}}
<div class="flex h-dvh flex-col">
    <header class="border-base-300 flex items-center justify-between gap-4 border-b px-4 py-3 font-sans text-sm">
        <a
            class="link link-hover"
            href="{{ route('memories.show', ['month' => $month->toYearMonth(), 'who' => 'yours']) }}"
        >← {{ $monthName }}</a>

        <span class="text-right">
            <span x-show="status === 'saving'" class="opacity-60">Saving…</span>
            <span x-show="status === 'saved'" class="opacity-60" x-text="shared ? 'Shared · Saved' : 'Saved'"></span>
            <span x-show="status === 'failed'" class="text-error">
                Not saved <button type="button" class="link" @click="retry()">Retry</button>
            </span>
            <span x-show="status === 'conflict'" class="text-warning">Changed on another device</span>
        </span>
    </header>

    <div
        x-show="status === 'conflict'"
        role="alert"
        class="bg-warning/20 rounded-box mx-4 mt-3 space-y-3 p-3 font-sans text-sm"
    >
        <p>You edited this on another device at 9:14 PM. Which version do you want?</p>
        <div class="flex gap-2">
            <button type="button" class="btn btn-sm" @click="resolveConflict()">Use that one</button>
            <button type="button" class="btn btn-sm btn-ghost" @click="resolveConflict()">Keep this one</button>
        </div>
    </div>

    <div x-show="status === 'failed'" role="alert" class="alert alert-error alert-soft mx-4 mt-3 font-sans text-sm">
        You seem to be offline. Keep this page open and it will save when you're back.
    </div>

    <p class="px-4 pt-4 font-sans text-sm opacity-60" x-show="! shared">{{ $otherLine }}</p>
    <p class="px-4 pt-4 font-sans text-sm opacity-60" x-show="shared">
        Shared {{ $today }}. Edits still save as you type<span x-show="revealed">, and {{ $otherName }} sees them</span
        >.
    </p>

    <textarea
        x-model="body"
        @input="typed()"
        class="flex-1 resize-none bg-transparent px-4 py-4 text-lg leading-relaxed outline-none"
        placeholder="What do you want to remember about {{ $monthName }}?"
        aria-label="Your memory for {{ $monthName }}"
    ></textarea>

    <footer
        x-show="! shared"
        class="border-base-300 border-t px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] font-sans"
    >
        <button
            type="button"
            class="btn btn-primary btn-block"
            :disabled="body.trim() === ''"
            @click="confirming = true"
        >
            Share with {{ $otherName }}…
        </button>
        <p class="mt-2 text-center text-xs opacity-60">Only you can see this until you share it.</p>
    </footer>

    {{-- The Share sheet --}}
    <div class="modal modal-bottom" :class="confirming && 'modal-open'">
        <div class="modal-box font-sans">
            <h3 class="font-serif text-2xl">Share your {{ $monthName }} memory?</h3>
            <ul class="mt-4 list-disc space-y-2 pl-5">
                @if ($otherShared)
                    <li>{{ $otherName }} has shared, so {{ $monthName }} reveals as soon as you do.</li>
                @else
                    <li>{{ $otherName }} can read it once you've both shared, or on {{ $deadline }}.</li>
                @endif
                <li>You can keep editing it afterwards.</li>
                <li>You can't unshare it.</li>
            </ul>
            <div class="mt-6 flex flex-col gap-2">
                <button type="button" class="btn btn-primary btn-block" @click="share()">Share</button>
                <button type="button" class="btn btn-ghost btn-block" @click="confirming = false">Not yet</button>
            </div>
        </div>
        <div class="modal-backdrop" @click="confirming = false"></div>
    </div>

    {{-- Right after sharing --}}
    <div
        x-show="justShared"
        class="bg-base-100 fixed inset-0 z-40 flex flex-col items-center justify-center gap-4 px-8 text-center"
    >
        <template x-if="! revealed">
            <div>
                <p class="text-4xl">Shared.</p>
                <p class="mt-4 text-lg">
                    {{ $monthName }} reveals when {{ $otherName }} shares, or on {{ $deadline }} at the latest.
                </p>
                <div class="mt-8 flex flex-col gap-2 font-sans">
                    <a
                        class="btn btn-primary"
                        href="{{ route('memories.show', ['month' => $month->toYearMonth(), 'who' => 'yours']) }}"
                    >Back to {{ $monthName }}</a>
                    <button type="button" class="btn btn-ghost" @click="keepEditing()">Keep editing</button>
                </div>
            </div>
        </template>
        <template x-if="revealed">
            <div>
                <p class="text-4xl">{{ $monthName }} is revealed.</p>
                <p class="mt-4 text-lg">You've both shared.</p>
                <div class="mt-8 flex flex-col gap-2 font-sans">
                    <a class="btn btn-primary" href="{{ route('memories.show', $month->toYearMonth()) }}"
                        >Read {{ $otherName }}'s →</a>
                    <button type="button" class="btn btn-ghost" @click="keepEditing()">Keep editing yours</button>
                </div>
            </div>
        </template>
    </div>
</div>
