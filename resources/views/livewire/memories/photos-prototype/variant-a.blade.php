{{-- PROTOTYPE (#299) variants A and D: a thumbnail grid below the text. Hold and drag to reorder; tap for a caption sheet. A shows a caption line under each photo, D a chip on the photo. --}}
<section class="px-4 pt-2 pb-4 font-sans">
    <div class="mb-2 flex items-baseline justify-between">
        <h2 class="text-sm font-semibold">Photos</h2>
        <span class="text-xs opacity-60" x-text="`${photos.length} of ${max}`"></span>
    </div>

    <div
        class="grid grid-cols-3 gap-1.5"
        x-sort="(id, position) => moveTo(id, position)"
        x-sort:config="{ delay: 200, delayOnTouchOnly: true, animation: 150, filter: '.prototype-add', preventOnFilter: false }"
    >
        <template x-for="(photo, i) in photos" :key="photo.id">
            <button
                type="button"
                x-sort:item="photo.id"
                class="block text-left"
                x-on:click="sheet = photo.id"
            >
                <span class="rounded-field bg-base-200 relative block aspect-square overflow-hidden">
                    <img
                        :src="photo.thumb"
                        alt=""
                        class="h-full w-full object-cover"
                        :class="photo.state !== 'ready' && 'opacity-40'"
                    />
                    <template x-if="photo.state === 'uploading'">
                        <span class="absolute inset-0 grid place-items-center">
                            <span
                                class="radial-progress bg-base-100/80 text-xs"
                                :style="`--value: ${Math.round(photo.progress)}; --size: 2.5rem; --thickness: 3px`"
                                x-text="`${Math.round(photo.progress)}%`"
                            ></span>
                        </span>
                    </template>
                    <template x-if="photo.state === 'processing'">
                        <span class="absolute inset-0 grid place-items-center">
                            <span class="loading loading-spinner loading-md"></span>
                        </span>
                    </template>
                    <template x-if="photo.state === 'failed'">
                        <span class="absolute inset-0 grid place-items-center">
                            <span class="badge badge-error badge-sm">Didn't upload</span>
                        </span>
                    </template>
                    @if ($variant === 'D')
                        <template x-if="photo.caption && photo.state !== 'failed'">
                            <span
                                class="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/70 to-transparent px-1.5 pt-4 pb-1 text-[11px] text-white"
                                x-text="photo.caption"
                            ></span>
                        </template>
                        <template x-if="! photo.caption && photo.state !== 'failed'">
                            <span class="badge badge-sm bg-base-100/90 absolute bottom-1.5 left-1.5 gap-1 border-0">
                                ✎ Caption
                            </span>
                        </template>
                    @endif
                </span>
                @if ($variant === 'A')
                    <span
                        class="mt-1 line-clamp-2 block text-xs leading-snug"
                        :class="photo.caption ? 'font-serif italic opacity-80' : 'text-primary'"
                        x-text="photo.caption || '✎ Add a caption'"
                    ></span>
                @endif
            </button>
        </template>

        <label
            x-show="room > 0"
            class="prototype-add rounded-field border-base-300 grid aspect-square self-start cursor-pointer place-items-center border-2 border-dashed text-sm opacity-70"
        >
            <span class="text-center">
                <span class="block text-2xl leading-none">+</span>
                Add
            </span>
            <input type="file" accept="image/*" multiple class="hidden" x-on:change="pick($event)" />
        </label>
    </div>

    <p class="mt-2 text-xs opacity-60" x-show="photos.length > 1">Hold and drag a photo to move it.</p>
    <p class="mt-2 text-xs opacity-60" x-show="room === 0">That's the most a memory can hold. Remove one to add another.</p>
</section>

<div class="modal modal-bottom" :class="sheet && 'modal-open'">
    <template x-if="sheetPhoto">
        <div class="modal-box max-h-[90dvh] w-full max-w-none p-0 font-sans">
            <img :src="sheetPhoto.web" alt="" class="max-h-[38dvh] w-full object-cover" />
            <div class="space-y-3 p-4">
                <template x-if="sheetPhoto.state === 'failed'">
                    <div class="alert alert-error alert-soft text-sm">
                        <span>This photo didn't upload.</span>
                        <button type="button" class="btn btn-sm" x-on:click="retry(sheetPhoto.id)">Try again</button>
                    </div>
                </template>
                <textarea
                    class="textarea w-full font-serif text-base italic"
                    rows="2"
                    placeholder="Add a caption (optional)"
                    x-model="sheetPhoto.caption"
                    x-on:input.debounce.1000ms="captioned()"
                ></textarea>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-ghost"
                        x-show="index(sheetPhoto.id) > 0"
                        x-on:click="moveTo(sheetPhoto.id, 0)"
                    >
                        Make it first
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-ghost text-error ml-auto"
                        x-on:click="remove(sheetPhoto.id)"
                    >
                        Remove photo
                    </button>
                </div>
                <button type="button" class="btn btn-block" x-on:click="sheet = null">Done</button>
            </div>
        </div>
    </template>
    <div class="modal-backdrop" x-on:click="sheet = null"></div>
</div>
