<div x-data="memoryReactions">
    @if ($this->canReact || $this->reactions->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($this->reactions as $reaction)
                @if ($this->canReact && $reaction->user_id === auth()->id())
                    <button
                        type="button"
                        wire:key="reaction-{{ $reaction->id }}"
                        wire:loading.attr="disabled"
                        class="btn btn-circle btn-outline tap-target border-base-300 text-xl"
                        aria-label="Remove your {{ $reaction->emoji }} reaction"
                        x-bind:class="justAdded === @js($reaction->emoji) && 'moment-pop'"
                        x-on:click="remove(@js($reaction->emoji))"
                    >
                        {{ $reaction->emoji }}
                    </button>
                @else
                    <span
                        wire:key="reaction-{{ $reaction->id }}"
                        class="badge badge-xl badge-outline border-base-300 text-xl"
                        role="img"
                        aria-label="{{ $reaction->user->displayName() }} reacted {{ $reaction->emoji }}"
                    >
                        {{ $reaction->emoji }}
                    </span>
                @endif
            @endforeach

            @if ($this->canReact && $this->hasRoom)
                <button
                    type="button"
                    wire:key="add"
                    wire:loading.attr="disabled"
                    class="btn btn-circle btn-outline tap-target border-base-300 text-xl"
                    aria-label="Add a reaction"
                    x-bind:aria-expanded="open"
                    x-on:click="open = ! open"
                >
                    +
                </button>
            @endif
        </div>

        @if ($this->canReact && $this->hasRoom)
            <div
                wire:key="picker"
                x-show="open"
                x-transition:enter="transition duration-150 ease-out"
                x-transition:enter-start="-translate-y-1 scale-95 opacity-0"
                x-transition:enter-end="translate-y-0 scale-100 opacity-100"
                x-transition:leave="transition duration-100 ease-in"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-on:keydown.escape.window="open = false"
                class="relative mt-2 flex origin-top-left flex-wrap items-center gap-1"
                style="display: none"
            >
                @foreach ($this->quickPicks as $emoji)
                    <button
                        type="button"
                        wire:key="pick-{{ $emoji }}"
                        wire:loading.attr="disabled"
                        class="btn btn-ghost btn-square text-xl"
                        aria-label="React {{ $emoji }}"
                        x-on:click="quickPick(@js($emoji))"
                    >
                        {{ $emoji }}
                    </button>
                @endforeach
                <button type="button" class="btn btn-ghost btn-sm tap-target font-sans" x-on:click="pick()">
                    More…
                </button>
                <input
                    x-ref="picker"
                    type="text"
                    class="pointer-events-none absolute inset-0 opacity-0"
                    style="font-size: 16px"
                    tabindex="-1"
                    aria-label="Type an emoji to react"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    x-on:input="picked($event)"
                    x-on:compositionend="picked($event)"
                    x-on:blur="hint = null"
                    x-on:keydown.escape="$el.blur()"
                />
            </div>
        @endif

        <p x-show="hint" x-text="hint" class="mt-2 font-sans text-sm opacity-60" style="display: none"></p>

        @error('emoji')
            <p class="text-error mt-2 font-sans text-sm">{{ $message }}</p>
        @enderror
    @endif

    <div wire:ignore class="toast toast-center toast-bottom pointer-events-none z-40">
        <div
            role="status"
            x-show="notice"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-y-4 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="translate-y-4 opacity-0"
            class="alert bg-neutral text-neutral-content pointer-events-auto flex font-sans text-sm shadow-lg"
            style="display: none"
        >
            <span x-text="notice"></span>
            <button type="button" class="btn btn-sm btn-ghost" x-on:click="undo()">Undo</button>
        </div>
    </div>
</div>
