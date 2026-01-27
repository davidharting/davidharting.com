<x-layout.app title="Admin">
    <x-type.page-title>Admin</x-type.page-title>

    <ul class="list-disc">
        <li class="list-item">
            <a class="link link-primary" href="/admin">Filament Admin</a>
        </li>

        <li class="list-item">
            <form action="/backend/backup" method="POST" class="inline">
                @csrf
                <button type="submit" class="link link-primary">
                    Backup database
                </button>
            </form>
        </li>
    </ul>

    @if (session("backup_error"))
        <div class="mt-4 text-error">
            {{ session("backup_error") }}
        </div>
    @endif
</x-layout.app>
