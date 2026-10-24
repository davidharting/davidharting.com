{{-- PROTOTYPE (#299) variant C: a filmstrip above the text, a full-screen caption view that steps through photos, and an Arrange mode where you tap photos in the order you want. --}}
<section class="pt-3 font-sans">
    <div class="flex items-baseline justify-between px-4">
        <span class="text-xs opacity-60" x-text="photos.length ? `${photos.length} of ${max} photos` : 'No photos yet'"></span>
        <button
            type="button"
            class="link link-hover text-sm"
            x-show="photos.length > 1"
            x-on:click="startArranging()"
        >
            Arrange
        </button>
    </div>

    <div class="flex snap-x gap-2 overflow-x-auto px-4 pt-2 pb-3">
        <label
            x-show="room > 0"
            class="rounded-field border-base-300 grid h-28 w-24 shrink-0 cursor-pointer snap-start place-items-center border-2 border-dashed text-sm opacity-70"
        >
            <span class="text-center"><span class="block text-2xl leading-none">+</span>Photos</span>
            <input type="file" accept="image/*" multiple class="hidden" x-on:change="pick($event)" />
        </label>

        <template x-for="photo in photos" :key="photo.id">
            <button type="button" class="w-24 shrink-0 snap-start text-left" x-on:click="sheet = photo.id">
                <span class="rounded-field bg-base-200 relative block h-28 overflow-hidden">
                    <img
                        :src="photo.thumb"
                        alt=""
                        class="h-full w-full object-cover"
                        :class="photo.state !== 'ready' && 'opacity-40'"
                    />
                    <template x-if="photo.state === 'uploading'">
                        <progress class="progress absolute inset-x-2 bottom-2 w-auto" :value="photo.progress" max="100"></progress>
                    </template>
                    <template x-if="photo.state === 'processing'">
                        <span class="absolute inset-0 grid place-items-center"><span class="loading loading-spinner loading-sm"></span></span>
                    </template>
                    <template x-if="photo.state === 'failed'">
                        <span class="absolute inset-0 grid place-items-center"><span class="badge badge-error badge-sm">Retry</span></span>
                    </template>
                </span>
                <span
                    class="mt-1 line-clamp-2 block text-xs"
                    :class="photo.caption ? 'italic opacity-80' : 'opacity-40'"
                    x-text="photo.caption || 'Caption'"
                ></span>
            </button>
        </template>
    </div>
</section>

{{-- Full-screen caption view: step through photos one after another. --}}
<template x-if="sheetPhoto">
    <div class="fixed inset-0 z-40 flex flex-col bg-black font-sans text-white">
        <div class="flex items-center justify-between px-4 py-3 text-sm">
            <button type="button" class="text-error-content/80" x-on:click="remove(sheetPhoto.id)">Remove</button>
            <span class="opacity-70" x-text="`${index(sheetPhoto.id) + 1} of ${photos.length}`"></span>
            <button type="button" class="font-semibold" x-on:click="sheet = null">Done</button>
        </div>
        <div class="grid min-h-0 grow place-items-center">
            <img :src="sheetPhoto.web" alt="" class="max-h-full max-w-full object-contain" />
        </div>
        <template x-if="sheetPhoto.state === 'failed'">
            <div class="flex items-center justify-center gap-3 p-2 text-sm">
                <span>This photo didn't upload.</span>
                <button type="button" class="btn btn-sm" x-on:click="retry(sheetPhoto.id)">Try again</button>
            </div>
        </template>
        <div class="space-y-3 px-4 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <textarea
                class="w-full resize-none bg-white/10 rounded-field p-3 font-serif text-base italic outline-none"
                rows="2"
                placeholder="Add a caption (optional)"
                x-model="sheetPhoto.caption"
                x-on:input.debounce.1000ms="captioned()"
            ></textarea>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="btn btn-sm grow"
                    :disabled="index(sheetPhoto.id) === 0"
                    x-on:click="sheet = photos[index(sheetPhoto.id) - 1].id"
                >
                    ← Previous
                </button>
                <button
                    type="button"
                    class="btn btn-sm grow"
                    :disabled="index(sheetPhoto.id) === photos.length - 1"
                    x-on:click="sheet = photos[index(sheetPhoto.id) + 1].id"
                >
                    Next →
                </button>
            </div>
        </div>
    </div>
</template>

{{-- Arrange mode: tap photos in the order you want them. --}}
<template x-if="arranging">
    <div class="bg-base-100 fixed inset-0 z-40 flex flex-col font-sans">
        <div class="border-base-300 flex items-center justify-between border-b px-4 py-3 text-sm">
            <button type="button" x-on:click="arranging = false; picked = []">Cancel</button>
            <span class="font-semibold">Arrange photos</span>
            <button type="button" class="font-semibold" x-on:click="finishArranging()">Done</button>
        </div>
        <p class="px-4 pt-3 text-sm opacity-70">
            Tap the photos in the order you want them. The ones you don't tap keep their order after the rest.
        </p>
        <div class="grid grid-cols-3 gap-1.5 p-4">
            <template x-for="photo in photos" :key="photo.id">
                <button type="button" class="rounded-field relative aspect-square overflow-hidden" x-on:click="tapToOrder(photo.id)">
                    <img :src="photo.thumb" alt="" class="h-full w-full object-cover" :class="picked.includes(photo.id) && 'opacity-60'" />
                    <template x-if="picked.includes(photo.id)">
                        <span class="badge badge-primary absolute top-1.5 left-1.5" x-text="picked.indexOf(photo.id) + 1"></span>
                    </template>
                </button>
            </template>
        </div>
    </div>
</template>
