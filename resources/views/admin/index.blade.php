<x-layout.app>
    <x-type.page-title>Admin</x-type.page-title>

    <ul class="list-disc">
        <a class="list-item link link-primary" href="/admin">Filament Admin</a>

        <li class="list-item">
            <form method="POST" action="{{ route('admin.backup') }}" class="inline">
                @csrf
                <button type="submit" class="link link-primary">
                    Backup database
                </button>
            </form>
        </li>
    </ul>

    @if (session('backupError'))
        <div>
            {{ session('backupError') }}
        </div>
    @endif
</x-layout.app>
