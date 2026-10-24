{{-- PROTOTYPE (#299) variant B: full-width photos below the text, as the month page shows them, captions typed inline, a menu per photo to move or remove it. --}}
<section class="space-y-6 px-4 pt-2 pb-4">
    <template x-for="(photo, i) in photos" :key="photo.id">
        <figure>
            <div class="rounded-box bg-base-200 relative overflow-hidden">
                <img
                    :src="photo.web"
                    alt=""
                    class="w-full"
                    :class="photo.state !== 'ready' && 'opacity-40'"
                />

                <template x-if="photo.state === 'uploading'">
                    <div class="absolute inset-x-4 bottom-4 font-sans text-sm">
                        <progress class="progress w-full" :value="photo.progress" max="100"></progress>
                        <p class="mt-1">Uploading…</p>
                    </div>
                </template>
                <template x-if="photo.state === 'processing'">
                    <div class="absolute inset-0 grid place-items-center font-sans text-sm">
                        <span class="badge gap-2"><span class="loading loading-spinner loading-xs"></span> Getting it ready…</span>
                    </div>
                </template>
                <template x-if="photo.state === 'failed'">
                    <div class="absolute inset-0 grid place-items-center font-sans text-sm">
                        <div class="bg-base-100 rounded-box space-y-2 p-3 text-center shadow">
                            <p>This photo didn't upload.</p>
                            <button type="button" class="btn btn-sm" x-on:click="retry(photo.id)">Try again</button>
                        </div>
                    </div>
                </template>

                <div class="dropdown dropdown-end absolute top-2 right-2 font-sans">
                    <button type="button" tabindex="0" class="btn btn-circle btn-sm bg-base-100/90 border-0" aria-label="Photo options">
                        ⋯
                    </button>
                    <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-10 w-44 p-2 shadow">
                        <li x-show="i > 0"><button type="button" x-on:click="move(photo.id, -1); document.activeElement.blur()">Move up</button></li>
                        <li x-show="i < photos.length - 1"><button type="button" x-on:click="move(photo.id, 1); document.activeElement.blur()">Move down</button></li>
                        <li><button type="button" class="text-error" x-on:click="remove(photo.id)">Remove</button></li>
                    </ul>
                </div>
            </div>
            <input
                type="text"
                class="mt-1 w-full bg-transparent py-2 text-base italic opacity-70 outline-none focus:opacity-100"
                placeholder="Add a caption…"
                x-model="photo.caption"
                x-on:input.debounce.1000ms="captioned()"
            />
        </figure>
    </template>

    <label class="btn btn-outline btn-block font-sans" :class="room === 0 && 'btn-disabled'">
        <span x-show="room > 0">Add photos</span>
        <span x-show="room === 0">10 photos, the most a memory holds</span>
        <span class="opacity-60" x-show="room > 0 && photos.length" x-text="`${photos.length} of ${max}`"></span>
        <input type="file" accept="image/*" multiple class="hidden" :disabled="room === 0" x-on:change="pick($event)" />
    </label>
</section>
