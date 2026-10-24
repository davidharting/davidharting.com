@php
    $monthForViewer = $this->monthForViewer;
    $month = $monthForViewer->month->calendarMonth;
    $otherName = $monthForViewer->other->displayName();
@endphp

<div
    x-data="{
        confirming: false,
        justSaved: false,
        savedFading: false,
        savedTimers: [],
        keepPillInView() {
            const viewport = window.visualViewport;
            if (! viewport) return;
            this.$refs.pill.style.transform = `translateY(${viewport.offsetTop}px)`;
        },
        growText() {
            const text = this.$refs.text;
            const scrolledTo = window.scrollY;
            text.style.height = 'auto';
            text.style.height = `${text.scrollHeight}px`;
            window.scrollTo(0, scrolledTo);
        },
        flashSaved() {
            this.hideSaved();
            this.justSaved = true;
            this.savedTimers = [
                setTimeout(() => (this.savedFading = true), 1000),
                setTimeout(() => this.hideSaved(), 1500),
            ];
        },
        hideSaved() {
            this.savedTimers.forEach((timer) => clearTimeout(timer));
            this.justSaved = false;
            this.savedFading = false;
        },
    }"
    x-init="
        $nextTick(() => growText());
        keepPillInView();
        window.visualViewport?.addEventListener('resize', () => keepPillInView());
        window.visualViewport?.addEventListener('scroll', () => keepPillInView());
        $wire.$watch('body', () => $nextTick(() => growText()));
    "
    x-on:memory-saved="flashSaved()"
    x-on:online.window="$wire.$commit()"
    x-on:beforeunload.window="if ($wire.$dirty('body') || $wire.changedElsewhere) $event.preventDefault();"
    class="flex min-h-dvh flex-col"
>
    <header class="border-base-300 border-b px-4 py-3 font-sans text-sm">
        <a
            class="link link-hover"
            href="{{ route("memories.show", ["month" => $month->toYearMonth(), "who" => "yours"]) }}"
        >← {{ $month->name() }}</a>
    </header>

    <div
        x-ref="pill"
        wire:ignore.self
        aria-live="polite"
        class="pointer-events-none fixed top-3 right-3 z-30 font-sans text-sm"
    >
        <span wire:offline class="badge badge-error">Not saved</span>
        <span wire:offline.class="hidden" class="grid justify-items-end">
            @unless ($changedElsewhere)
                <span wire:dirty wire:target="body" class="badge [grid-area:1/1]">Saving…</span>
            @endunless
            <span
                x-show="justSaved"
                x-on:input.window="hideSaved()"
                x-bind:class="savedFading && 'opacity-0'"
                class="badge badge-success gap-1 transition-opacity duration-500 [grid-area:1/1]"
                style="display: none"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                    class="size-4"
                >
                    <path
                        fill-rule="evenodd"
                        d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                        clip-rule="evenodd"
                    />
                </svg>
                Saved
            </span>
        </span>
    </div>

    <div
        wire:offline.class.remove="hidden"
        role="alert"
        class="alert alert-error alert-soft mx-4 mt-3 hidden font-sans text-sm"
    >
        You seem to be offline. Keep this page open and it will save when you're back.
    </div>

    @if ($changedElsewhere)
        <div role="alert" class="bg-warning/20 rounded-box mx-4 mt-3 space-y-3 p-3 font-sans text-sm">
            <p>This changed on another device since you opened it. Which version do you want?</p>
            <div class="flex gap-2">
                <button type="button" class="btn btn-sm" wire:click="useTheirs">Use that one</button>
                <button type="button" class="btn btn-sm btn-ghost" wire:click="keepMine">Keep this one</button>
            </div>
        </div>
    @endif

    @error('body')
        <div role="alert" class="alert alert-error alert-soft mx-4 mt-3 font-sans text-sm">{{ $message }}</div>
    @enderror

    <div class="space-y-1 px-4 pt-4 font-sans text-sm opacity-60">
        <p>{{ $this->standingLabel() }}</p>
        @unless ($this->isShared())
            <p>Only you can see this until you share it.</p>
        @endunless
    </div>

    <textarea
        wire:model="body"
        x-ref="text"
        wire:ignore.self
        x-on:input="growText()"
        x-on:input.debounce.1000ms="$wire.$commit()"
        x-on:blur="$wire.$commit()"
        class="min-h-40 shrink-0 resize-none overflow-hidden bg-transparent px-4 py-4 text-lg leading-relaxed outline-none"
        maxlength="{{ \App\Memories\MemoryWriter::MAX_LENGTH }}"
        placeholder="What do you want to remember about {{ $month->name() }}?"
        aria-label="Your memory for {{ $month->label() }}"
    ></textarea>

    @include('livewire.memories.editor-photos')

    <footer class="mt-auto flex gap-2 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] font-sans">
        <a
            @class(['btn btn-outline', 'w-1/3' => ! $this->isShared(), 'grow' => $this->isShared()])
            href="{{ route("memories.show", ["month" => $month->toYearMonth(), "who" => "yours"]) }}"
            x-on:click.prevent="
                if ($store.memoryUploads.pending > 0 && !confirm('Your photos are still uploading. Leave anyway?'))
                    return;
                $wire
                    .$commit()
                    .then(() => {
                        if (!$wire.changedElsewhere) window.location = $el.href;
                    })
                    .catch(() => {});
            "
        >Done</a>
        @unless ($this->isShared())
            <button
                type="button"
                class="btn btn-primary grow"
                x-bind:disabled="
                    ($wire.body.trim() === '' && $wire.photoCount === 0) || $store.memoryUploads.pending > 0
                "
                x-on:click="confirming = true"
            >
                <span x-show="$store.memoryUploads.pending === 0">Share with {{ $otherName }}…</span>
                <span
                    x-show="$store.memoryUploads.pending > 0"
                    x-text="
                        `Uploading ${$store.memoryUploads.pending} ${$store.memoryUploads.pending === 1 ? 'photo' : 'photos'}…`
                    "
                    style="display: none"
                ></span>
            </button>
        @endunless
    </footer>

    @unless ($this->isShared())
        {{-- The Share sheet --}}
        <div class="modal modal-bottom" x-bind:class="confirming && 'modal-open'">
            <div class="modal-box font-sans">
                <h3 class="font-serif text-2xl">Share your {{ $month->name() }} memory?</h3>
                <ul class="mt-4 list-disc space-y-2 pl-5">
                    <li>{{ $this->whenTheyCanReadLabel() }}</li>
                    <li>You can keep editing it afterwards.</li>
                    <li>You can't unshare it.</li>
                </ul>
                <div class="mt-6 flex flex-col gap-2">
                    <button
                        type="button"
                        class="btn btn-primary btn-block"
                        wire:click="share"
                        wire:loading.attr="disabled"
                        x-on:click="confirming = false"
                    >
                        Share
                    </button>
                    <button type="button" class="btn btn-ghost btn-block" x-on:click="confirming = false">
                        Not yet
                    </button>
                </div>
            </div>
            <div class="modal-backdrop" x-on:click="confirming = false"></div>
        </div>
    @endunless

    @if ($justShared)
        <div class="bg-base-100 fixed inset-0 z-40 flex flex-col items-center justify-center px-8 text-center">
            @if ($monthForViewer->month->hasRevealed())
                <p class="text-4xl">{{ $month->name() }} is revealed.</p>
                <div class="mt-8 flex flex-col gap-2 font-sans">
                    @if ($monthForViewer->revealedOtherMemory())
                        <a class="btn btn-primary" href="{{ route("memories.show", $month->toYearMonth()) }}"
                            >Read {{ $otherName }}'s →</a>
                    @else
                        <a
                            class="btn btn-primary"
                            href="{{ route("memories.show", ["month" => $month->toYearMonth(), "who" => "yours"]) }}"
                        >Back to {{ $month->name() }}</a>
                    @endif
                    <button type="button" class="btn btn-ghost" wire:click="keepEditing">Keep editing yours</button>
                </div>
            @else
                <p class="text-4xl">Shared.</p>
                <p class="mt-4 text-lg">{{ $monthForViewer->revealStatusLabel() }}</p>
                <div class="mt-8 flex flex-col gap-2 font-sans">
                    <a
                        class="btn btn-primary"
                        href="{{ route("memories.show", ["month" => $month->toYearMonth(), "who" => "yours"]) }}"
                    >Back to {{ $month->name() }}</a>
                    <button type="button" class="btn btn-ghost" wire:click="keepEditing">Keep editing</button>
                </div>
            @endif
        </div>
    @endif
</div>
