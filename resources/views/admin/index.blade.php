<x-layout.app title="Admin" description="Administration">
    <div>
        <x-type.page-title>Admin</x-type.page-title>

        <ul class="list-disc">
            <a class="list-item link link-primary" href="/admin">Filament Admin</a>

            <form method="POST" action="{{ route('admin.backup') }}" class="inline">
                @csrf
                <button type="submit" class="list-item link link-primary">
                    Backup database
                </button>
            </form>
        </ul>

        @if (session('backupError'))
            <div>
                {{ session('backupError') }}
            </div>
        @endif
    </div>
</x-layout.app>
