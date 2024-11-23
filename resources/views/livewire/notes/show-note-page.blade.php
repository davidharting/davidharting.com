<div>
    <x-type.page-title>Notes</x-type.page-title>

    <div class="mt-8">
        <a
            href="{{ route("notes.index") }}"
            class="link link-primary"
            wire:navigate
        >
            < Back to all notes
        </a>
        <div>
            <div class="mt-12 w-full space-y-4">
                <x-note :note='$this->note' />
            </div>
        </div>
    </div>
</div>
