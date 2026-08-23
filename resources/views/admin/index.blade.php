<x-layout.app title="Admin">
    <x-type.page-title>Admin</x-type.page-title>

    <ul class="list-disc">
        <li class="list-item">
            <a class="link link-primary" href="/admin">Filament Admin</a>
        </li>

        <li class="list-item">
            <form action="/backend/backup" method="POST" class="inline">
                @csrf
                <button type="submit" class="link link-primary">Backup database</button>
            </form>
        </li>

        <li class="list-item">
            <a class="link link-primary" href="{{ route("kitchen-sink") }}"> Kitchen Sink </a>
        </li>
    </ul>

    @error('backup')
        <div class="text-error mt-4">{{ $message }}</div>
    @enderror
</x-layout.app>
