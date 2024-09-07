<div>
    <x-crumb.container>
        <x-crumb.item :url="route('notes.index')">Notes</x-crumb.item>
    </x-crumb.container>

    <x-type.page-title>Notes</x-type.page-title>

    <div class="mt-12 w-full space-y-4">
        <x-note :note='$this->note' />
    </div>
</div>
