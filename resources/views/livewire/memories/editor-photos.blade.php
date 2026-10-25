@php
    $photos = $this->photos();
    $max = \App\Memories\AddMemoryPhoto::MAX_PHOTOS;
@endphp

<section
    x-data="memoryPhotos({{ $max }})"
    x-on:upload-handled.window="handled($event.detail.photoId)"
    x-on:photo-removed.window="removed($event.detail.photoId)"
    x-on:beforeunload.window="if (uploads.length > 0 && ! $store.memoryUploads.abandoned) $event.preventDefault();"
    x-on:keydown.escape.window="sheet = null"
    class="px-4 pt-2 pb-4 font-sans"
>
    <div class="mb-2 flex items-baseline justify-between">
        <h2 class="text-sm font-semibold">Photos</h2>
        <span wire:ignore class="text-xs opacity-60" x-text="`${$wire.photoCount + uploads.length} of ${max}`"
            >{{ $photos->count() }} of {{ $max }}</span>
    </div>

    @foreach (['upload', 'photo', 'caption'] as $field)
        @error($field)
            <div role="alert" class="alert alert-error alert-soft mb-2 text-sm">{{ $message }}</div>
        @enderror
    @endforeach

    <div
        class="grid grid-cols-3 gap-1.5"
        wire:sort="movePhoto"
        wire:sort:config="{ delay: 200, delayOnTouchOnly: true, touchStartThreshold: 8, forceFallback: true, fallbackTolerance: 3 }"
    >
        @foreach ($photos as $photo)
            <button
                type="button"
                wire:key="photo-{{ $photo->id }}"
                wire:sort:item="{{ $photo->id }}"
                x-on:click="sheet = {{ $photo->id }}"
                aria-label="Photo {{ $loop->iteration }}: {{ $photo->caption ?? "no caption" }}"
                class="block select-none [-webkit-touch-callout:none]"
            >
                <span class="rounded-field bg-base-200 relative block aspect-square overflow-hidden">
                    @if ($photo->isReady())
                        <img
                            src="{{ route("memories.photos.show", [$photo, "thumbnail"]) }}"
                            alt=""
                            draggable="false"
                            class="pointer-events-none h-full w-full object-cover"
                        />
                    @else
                        <img
                            x-show="previews[{{ $photo->id }}]"
                            x-bind:src="previews[{{ $photo->id }}]"
                            alt=""
                            draggable="false"
                            class="pointer-events-none h-full w-full object-cover opacity-40"
                            style="display: none"
                        />
                        <span class="absolute inset-0 grid place-items-center">
                            @if ($photo->isFailed())
                                <span class="badge badge-error badge-sm">Didn't process</span>
                            @else
                                <span class="loading loading-spinner loading-md" aria-label="Processing"></span>
                            @endif
                        </span>
                    @endif

                    @if ($photo->caption !== null)
                        <span class="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/70 to-transparent px-1.5 pt-4 pb-1 text-left text-[11px] text-white">{{ $photo->caption }}</span>
                    @elseif (! $photo->isFailed())
                        <span class="badge badge-sm bg-base-100/90 absolute bottom-1.5 left-1.5 gap-1 border-0">
                            ✎ Caption
                        </span>
                    @endif
                </span>
            </button>
        @endforeach

        <div wire:ignore class="contents">
            <template x-for="upload in uploads" x-bind:key="upload.key">
                <div class="rounded-field bg-base-200 relative aspect-square overflow-hidden">
                    <img x-bind:src="upload.preview" alt="" class="h-full w-full object-cover opacity-40" />
                    <span class="absolute inset-0 grid place-items-center" x-show="! upload.failed">
                        <span
                            class="radial-progress bg-base-100/80 text-xs"
                            x-bind:style="`--value: ${upload.progress}; --size: 2.5rem; --thickness: 3px`"
                            x-text="`${upload.progress}%`"
                            role="progressbar"
                        ></span>
                    </span>
                    <button
                        type="button"
                        class="absolute inset-0 grid place-items-center"
                        x-show="upload.failed"
                        x-on:click="retry(upload)"
                    >
                        <span class="text-center text-xs">
                            <span class="badge badge-error badge-sm">Didn't upload</span>
                            <span class="mt-1 block">Tap to retry</span>
                        </span>
                    </button>
                    <button
                        type="button"
                        class="btn btn-circle btn-xs absolute top-1 right-1"
                        x-show="upload.failed"
                        x-on:click="dismiss(upload)"
                        aria-label="Forget this photo"
                    >
                        ✕
                    </button>
                </div>
            </template>
        </div>

        <label
            x-show="room > 0"
            class="rounded-field border-base-300 grid aspect-square cursor-pointer place-items-center self-start border-2 border-dashed text-sm opacity-70"
            @if ($photos->count() >= $max) style="display: none" @endif
        >
            <span class="text-center">
                <span class="block text-2xl leading-none">+</span>
                Add
            </span>
            <input
                type="file"
                accept="{{ implode(",", [...\App\Memories\AddMemoryPhoto::MIME_TYPES, ".heic", ".heif"]) }}"
                multiple
                class="hidden"
                x-on:change="pick($event)"
            />
        </label>
    </div>

    @if ($photos->count() > 1)
        <p class="mt-2 text-xs opacity-60">Hold and drag a photo to move it.</p>
    @endif

    @if ($photos->count() >= $max)
        <p class="mt-2 text-xs opacity-60">That's the most a memory can hold. Remove one to add another.</p>
    @endif

    @if ($photos->contains(fn ($photo) => $photo->isProcessing()))
        <div wire:poll.3s></div>
    @endif

    {{-- The caption sheet. Every photo's panel is rendered up front, and Alpine shows the one for the tapped photo. --}}
    <div class="modal modal-bottom" x-bind:class="sheet !== null && 'modal-open'">
        <div class="modal-box max-h-[90dvh] w-full max-w-none p-0 font-sans sm:max-w-lg">
            @foreach ($photos as $photo)
                <div wire:key="sheet-{{ $photo->id }}" x-show="sheet === {{ $photo->id }}" style="display: none">
                    @if ($photo->isReady())
                        <img
                            src="{{ route("memories.photos.show", [$photo, "web"]) }}"
                            alt=""
                            loading="lazy"
                            class="bg-base-200 max-h-[38dvh] w-full object-cover"
                        />
                    @else
                        <img
                            x-show="previews[{{ $photo->id }}]"
                            x-bind:src="previews[{{ $photo->id }}]"
                            alt=""
                            class="max-h-[38dvh] w-full object-cover"
                            style="display: none"
                        />
                    @endif
                    <div class="space-y-3 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                        @if ($photo->isFailed())
                            <div class="alert alert-error alert-soft text-sm">
                                <span>This photo couldn't be processed.</span>
                                <button type="button" class="btn btn-sm" wire:click="retryPhoto({{ $photo->id }})">
                                    Try again
                                </button>
                            </div>
                        @elseif ($photo->isProcessing())
                            <p class="text-sm opacity-60">Still processing. You can caption it already.</p>
                        @endif
                        <textarea
                            wire:ignore
                            class="textarea w-full font-serif text-base italic"
                            rows="2"
                            maxlength="{{ \App\Memories\MemoryPhotos::MAX_CAPTION_LENGTH }}"
                            placeholder="Add a caption (optional)"
                            aria-label="Caption for photo {{ $loop->iteration }}"
                            x-data="{ saved: @js($photo->caption ?? '') }"
                            x-on:input.debounce.1000ms="if ($el.value !== saved) $wire.caption({{ $photo->id }}, (saved = $el.value))"
                            x-on:blur="if ($el.value !== saved) $wire.caption({{ $photo->id }}, (saved = $el.value))"
                        >{{ $photo->caption }}</textarea>
                        <div class="flex gap-2">
                            @unless ($loop->first)
                                <button
                                    type="button"
                                    class="btn btn-sm btn-ghost"
                                    wire:click="movePhoto({{ $photo->id }}, 0)"
                                    x-on:click="sheet = null"
                                >
                                    Make it first
                                </button>
                            @endunless
                            <button
                                type="button"
                                class="btn btn-sm btn-ghost text-error ml-auto"
                                wire:click="removePhoto({{ $photo->id }})"
                                wire:confirm="Remove this photo?"
                            >
                                Remove photo
                            </button>
                        </div>
                        <button type="button" class="btn btn-block" x-on:click="sheet = null">Done</button>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="modal-backdrop" x-on:click="sheet = null"></div>
    </div>

    <div
        wire:ignore
        x-show="notice"
        x-transition.opacity
        class="fixed inset-x-4 bottom-20 z-40 flex justify-center"
        style="display: none"
    >
        <div role="status" class="alert bg-neutral text-neutral-content flex text-sm shadow-lg">
            <span x-text="notice"></span>
            <button
                type="button"
                class="btn btn-sm btn-ghost"
                x-show="removedPhotoId !== null"
                x-on:click="undoRemove()"
            >
                Undo
            </button>
        </div>
    </div>
</section>
