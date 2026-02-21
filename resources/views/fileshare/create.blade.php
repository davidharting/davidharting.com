<x-layout.app>
    <x-type.page-title>Upload a File</x-type.page-title>
    <form
        method="POST"
        action="{{ route("fileshare.store") }}"
        enctype="multipart/form-data"
    >
        @csrf

        <fieldset class="mb-4">
            <legend class="font-bold mb-2">Disk</legend>
            <label class="flex items-center gap-2">
                <input
                    type="radio"
                    name="disk"
                    value="private"
                    checked
                    class="radio"
                />
                Private
            </label>
            <label class="flex items-center gap-2">
                <input type="radio" name="disk" value="public" class="radio" />
                Public
            </label>
        </fieldset>

        <input name="file" type="file" class="file-input @error('file') file-input-error @enderror" />
        @error('file')
            <p class="text-error mt-1">{{ $message }}</p>
        @enderror

        <input type="submit" class="btn mt-4" />
    </form>
</x-layout.app>
