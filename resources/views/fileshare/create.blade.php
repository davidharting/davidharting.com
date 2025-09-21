<x-layout.app>
    <x-type.page-title>Upload a File</x-type.page-title>
    <form
        method="POST"
        action="{{ route("fileshare.store") }}"
        enctype="multipart/form-data"
    >
        @csrf

        <x-form.input name="file" type="file" class="file-input" />

        <input type="submit" class="btn" />
    </form>
</x-layout.app>
