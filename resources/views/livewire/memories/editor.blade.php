@php
    $monthForViewer = $this->monthForViewer;
    $month = $monthForViewer->month;
    $otherName = $monthForViewer->other->displayName();
@endphp

<div
    x-data="{
        confirming: false,
        justSaved: false,
        justSavedTimer: null,
        fitAboveKeyboard() {
            const viewport = window.visualViewport;
            if (! viewport) return;
            $el.style.height = `${viewport.height}px`;
            $el.style.transform = `translateY(${viewport.offsetTop}px)`;
        },
        flashSaved() {
            this.justSaved = true;
            clearTimeout(this.justSavedTimer);
            this.justSavedTimer = setTimeout(() => (this.justSaved = false), 1500);
        },
    }"
    x-init="
        fitAboveKeyboard();
        window.visualViewport?.addEventListener('resize', () => fitAboveKeyboard());
        window.visualViewport?.addEventListener('scroll', () => fitAboveKeyboard());
    "
    x-on:memory-saved="flashSaved()"
    x-on:online.window="$wire.$commit()"
    x-on:beforeunload.window="if ($wire.$dirty('body')) $event.preventDefault();"
    class="fixed inset-x-0 top-0 flex h-dvh flex-col"
>
    <header class="border-base-300 border-b px-4 py-3 font-sans text-sm">
        <a
            class="link link-hover"
            href="{{ route("memories.show", ["month" => $month->toYearMonth(), "who" => "yours"]) }}"
        >← {{ $month->name() }}</a>
    </header>

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
        wire:model.live.debounce.1000ms="body"
        x-on:blur="$wire.$commit()"
        class="flex-1 resize-none bg-transparent px-4 py-4 text-lg leading-relaxed outline-none"
        placeholder="What do you want to remember about {{ $month->name() }}?"
        aria-label="Your memory for {{ $month->label() }}"
    ></textarea>
    <footer class="border-base-300 bg-base-100 flex items-center justify-between gap-3 border-t px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] font-sans text-sm">
        <span aria-live="polite">
            <span wire:offline class="text-error">Not saved</span>
            <span wire:offline.class="hidden">
                <span wire:dirty wire:target="body" class="opacity-60">Saving…</span>
                <span wire:dirty.remove wire:target="body">
                    @if ($changedElsewhere)
                        <span class="text-warning">{{ $this->saveStatusLabel() }}</span>
                    @elseif ($this->saveStatusLabel())
                        <span
                            class="inline-flex items-center gap-1 transition duration-500"
                            x-bind:class="justSaved ? 'text-success' : 'opacity-60'"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                                class="size-5"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            {{ $this->saveStatusLabel() }}
                        </span>
                    @endif
                </span>
            </span>
        </span>

        @unless ($this->isShared())
            <button
                type="button"
                class="btn btn-primary btn-sm"
                x-bind:disabled="$wire.body.trim() === ''"
                x-on:click="confirming = true"
            >
                Share with {{ $otherName }}…
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
            @if ($monthForViewer->reveal->hasHappened())
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
