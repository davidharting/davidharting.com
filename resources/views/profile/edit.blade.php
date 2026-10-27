<x-layout.app>
    <x-type.page-title>Account</x-type.page-title>

    @can('administrate')
        <a class="link mt-4 inline-block" href="{{ route("admin.index") }}">Admin</a>
    @endcan

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
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
