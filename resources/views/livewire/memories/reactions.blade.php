<div x-data="memoryReactions">
    @if ($this->canReact || $this->reactions->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($this->reactions as $reaction)
                @if ($this->canReact && $reaction->user_id === auth()->id())
                    <button
                        type="button"
                        wire:key="reaction-{{ $reaction->id }}"
                        wire:loading.attr="disabled"
                        class="btn btn-circle btn-outline border-base-300 text-xl"
                        aria-label="Remove your {{ $reaction->emoji }} reaction"
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
                <div class="relative" wire:key="picker">
                    <button
                        type="button"
                        wire:loading.attr="disabled"
                        class="btn btn-circle btn-outline border-base-300 text-xl"
                        aria-label="Add a reaction"
                        x-on:click="pick()"
                    >
                        +
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
        </div>

        <p x-show="hint" x-text="hint" class="mt-2 font-sans text-sm opacity-60" style="display: none"></p>

        @error('emoji')
            <p class="text-error mt-2 font-sans text-sm">{{ $message }}</p>
        @enderror
    @endif

    <div wire:ignore class="toast toast-center toast-bottom pointer-events-none z-40">
        <div
            role="status"
            x-show="notice"
            x-transition.opacity
            class="alert bg-neutral text-neutral-content pointer-events-auto flex font-sans text-sm shadow-lg"
            style="display: none"
        >
            <span x-text="notice"></span>
            <button type="button" class="btn btn-sm btn-ghost" x-on:click="undo()">Undo</button>
        </div>
    </div>
</div>
