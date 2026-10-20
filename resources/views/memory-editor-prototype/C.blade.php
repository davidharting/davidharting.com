{{-- Variant C: a distraction-free writing screen with only Done. Share lives on a separate review screen showing the memory as the other person will read it. --}}

{{-- Writing --}}
<div x-show="screen === 'write'" class="flex h-dvh flex-col">
    <header class="flex items-center justify-between gap-3 px-4 pt-3 font-sans">
        <div class="flex items-center gap-2">
            <span class="font-serif text-xl">{{ $monthName }}</span>
            <span x-show="status === 'empty'" class="badge badge-ghost badge-sm">New</span>
            <span x-show="status === 'saving'" class="badge badge-ghost badge-sm gap-1.5"
                ><span class="status status-neutral animate-pulse"></span>Saving</span>
            <span x-show="status === 'saved'" class="badge badge-ghost badge-sm gap-1.5"
                ><span class="status status-success"></span><span x-text="shared ? 'Shared · Saved' : 'Saved'"></span
            ></span>
            <button
                x-show="status === 'failed'"
                type="button"
                class="badge badge-error badge-sm gap-1.5"
                @click="retry()"
            >
                <span class="status"></span>Not saved · retry
            </button>
            <span x-show="status === 'conflict'" class="badge badge-warning badge-sm gap-1.5"
                ><span class="status"></span>Conflict</span>
        </div>

        <template x-if="! shared">
            <button
                type="button"
                class="btn btn-sm btn-primary"
                :disabled="body.trim() === ''"
                @click="screen = 'review'"
            >
                Done
            </button>
        </template>
        <template x-if="shared">
            <a
                class="btn btn-sm"
                href="{{ route('memories.show', ['month' => $month->toYearMonth(), 'who' => 'yours']) }}"
            >Done</a>
        </template>
    </header>

    <div
        x-show="status === 'conflict'"
        role="alert"
        class="bg-warning/20 rounded-box mx-4 mt-3 space-y-3 p-3 font-sans text-sm"
    >
        <p>This changed on another device at 9:14 PM.</p>
        <div class="flex gap-2">
            <button type="button" class="btn btn-sm" @click="resolveConflict()">Use that version</button>
            <button type="button" class="btn btn-sm btn-ghost" @click="resolveConflict()">Keep this one</button>
        </div>
    </div>

    <textarea
        x-model="body"
        @input="typed()"
        class="flex-1 resize-none bg-transparent px-4 py-5 text-lg leading-relaxed outline-none"
        placeholder="Start anywhere. It saves as you go, and nobody sees it until you share."
        aria-label="Your memory for {{ $monthName }}"
    ></textarea>

    <div x-show="status === 'failed'" class="toast toast-center toast-bottom w-[calc(100%-2rem)] font-sans">
        <div class="alert alert-error text-sm">
            Offline. Your words are safe on this page and will save when you're back.
        </div>
    </div>
</div>

{{-- Review and share --}}
<div x-show="screen === 'review'" class="flex min-h-dvh flex-col">
    <header class="px-4 pt-3 font-sans">
        <button type="button" class="link link-hover text-sm" @click="screen = 'write'">← Keep writing</button>
    </header>

    <main class="flex-1 px-4 pt-6 pb-48">
        <p class="font-sans text-sm opacity-60">
            @if ($otherShared)
                {{ $otherName }} has shared. Share yours and {{ $monthName }} reveals for both of you.
            @else
                {{ $otherName }} hasn't shared yet. {{ $monthName }} reveals when you both have, or on {{ $deadline }}.
            @endif
        </p>
        <h1 class="mt-6 text-3xl">What {{ $otherName }} will read</h1>
        <div
            class="border-base-300 mt-4 border-l-2 pl-4 text-lg leading-relaxed whitespace-pre-line"
            x-text="body.trim()"
        ></div>
    </main>

    <footer class="bg-base-100 border-base-300 fixed inset-x-0 bottom-0 border-t px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] font-sans">
        <button type="button" class="btn btn-primary btn-block" @click="share()">Share {{ $monthName }}</button>
        <button type="button" class="btn btn-ghost btn-block mt-1" @click="screen = 'write'">
            Keep it a draft for now
        </button>
        <p class="mt-1 text-center text-xs opacity-60">You can edit it after sharing. You can't unshare it.</p>
    </footer>
</div>

{{-- Shared --}}
<div x-show="screen === 'done'" class="flex min-h-dvh flex-col items-center justify-center px-8 text-center">
    <template x-if="! revealed">
        <div>
            <p class="text-5xl">✓</p>
            <p class="mt-4 text-3xl">Shared</p>
            <p class="mt-4 text-lg">
                {{ $monthName }} reveals when {{ $otherName }} shares, or on {{ $deadline }} at the latest. We'll email
                you both.
            </p>
            <a
                class="btn btn-primary mt-8 font-sans"
                href="{{ route('memories.show', ['month' => $month->toYearMonth(), 'who' => 'yours']) }}"
            >Back to {{ $monthName }}</a>
        </div>
    </template>
    <template x-if="revealed">
        <div>
            <p class="text-3xl">{{ $monthName }} is revealed</p>
            <p class="mt-4 text-lg">You've both shared.</p>
            <a class="btn btn-primary mt-8 font-sans" href="{{ route('memories.show', $month->toYearMonth()) }}"
                >Read {{ $otherName }}'s →</a>
        </div>
    </template>
</div>
