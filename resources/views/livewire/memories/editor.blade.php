@php
    $monthForViewer = $this->monthForViewer;
    $month = $monthForViewer->month;
    $otherName = $monthForViewer->other->displayName();
@endphp

<div
    x-data="{ confirming: false }"
    x-on:online.window="$wire.$commit()"
    x-on:beforeunload.window="if ($wire.$dirty('body')) $event.preventDefault();"
    class="flex h-dvh flex-col"
>
    <header class="border-base-300 flex items-center justify-between gap-4 border-b px-4 py-3 font-sans text-sm">
        <a
            class="link link-hover"
            href="{{ route("memories.show", ["month" => $month->toYearMonth(), "who" => "yours"]) }}"
        >← {{ $month->name() }}</a>

        <span class="text-right">
            <span wire:offline class="text-error">Not saved</span>
            <span wire:offline.class="hidden">
                <span wire:dirty wire:target="body" class="opacity-60">Saving…</span>
                <span
                    wire:dirty.remove
                    wire:target="body"
                    @class(['text-warning' => $changedElsewhere, 'opacity-60' => ! $changedElsewhere])
                >{{ $this->saveStatusLabel() }}</span>
            </span>
        </span>
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

    <p class="px-4 pt-4 font-sans text-sm opacity-60">{{ $this->standingLabel() }}</p>

    <textarea
        wire:model.live.debounce.1000ms="body"
        x-on:blur="$wire.$commit()"
        class="flex-1 resize-none bg-transparent px-4 py-4 text-lg leading-relaxed outline-none"
        placeholder="What do you want to remember about {{ $month->name() }}?"
        aria-label="Your memory for {{ $month->label() }}"
    ></textarea>
    @unless ($this->isShared())
        <footer class="border-base-300 border-t px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] font-sans">
            <button
                type="button"
                class="btn btn-primary btn-block"
                x-bind:disabled="$wire.body.trim() === ''"
                x-on:click="confirming = true"
            >
                Share with {{ $otherName }}…
            </button>
            <p class="mt-2 text-center text-xs opacity-60">Only you can see this until you share it.</p>
        </footer>

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
