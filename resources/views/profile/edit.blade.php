<x-layout.app>
    <x-type.page-title>Account</x-type.page-title>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @can('administrate')
                <x-card title="Admin">
                    <ul class="space-y-2">
                        <li><a class="link link-primary" href="/admin">Filament Admin</a></li>
                        <li>
                            <form action="{{ route("admin.backup") }}" method="POST">
                                @csrf
                                <button type="submit" class="link link-primary">Backup database</button>
                            </form>
                        </li>
                        <li><a class="link link-primary" href="{{ route("kitchen-sink") }}">Kitchen Sink</a></li>
                    </ul>
                    @error('backup')
                        <div class="text-error mt-4">{{ $message }}</div>
                    @enderror
                </x-card>
            @endcan

            <x-card title="Profile Information">
                <x-profile.info :user="$user" />
            </x-card>

            <x-card title="Update Password">
                <x-profile.update-password />
            </x-card>

            @unless ($user->isMemoryParticipant())
                <x-card title="Delete Your Account">
                    <x-profile.delete-user />
                </x-card>
            @endunless

            <form method="POST" action="{{ route("logout") }}">
                @csrf
                <input type="submit" value="Log out" class="btn" />
            </form>
        </div>
    </div>
</x-layout.app>
