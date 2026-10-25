<div x-data="memoryReactions">
    @if ($this->canReact || $this->reactions->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($this->reactions as $reaction)
                @if ($this->canReact)
                    <button
                        type="button"
                        wire:key="reaction-{{ $reaction->id }}"
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
                <div class="relative">
                    <button
                        type="button"
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
                        aria-hidden="true"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        x-on:input="picked()"
                        x-on:blur="hint = null"
                        x-on:keydown.escape="$el.blur()"
                    />
                </div>
            @endif
        </div>

        <p x-show="hint" x-text="hint" class="mt-2 font-sans text-sm opacity-60" style="display: none"></p>
    @endif

    <div
        wire:ignore
        x-show="notice"
        x-transition.opacity
        class="fixed inset-x-4 bottom-6 z-40 flex justify-center"
        style="display: none"
    >
        <div role="status" class="alert bg-neutral text-neutral-content flex font-sans text-sm shadow-lg">
            <span x-text="notice"></span>
            <button type="button" class="btn btn-sm btn-ghost" x-on:click="undo()">Undo</button>
        </div>
    </div>
</div>
