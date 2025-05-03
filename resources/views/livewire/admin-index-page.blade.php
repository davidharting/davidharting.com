<div>
    <x-type.page-title>Admin</x-type.page-title>

    <ul class="list-disc">
        <a class="list-item link link-primary" href="/admin">Filament Admin</a>

        <a wire:click="backupDatabase" class="list-item link link-primary">
            Backup database
        </a>

        <a class="list-item link link-primary" href="{{ route("pulse") }}">
            Pulse
        </a>
    </ul>

    <div>
        {{ $backupError }}
    </div>
</div>
