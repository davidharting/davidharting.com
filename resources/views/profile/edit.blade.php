<x-layout.app>
    <x-type.page-title>Profile</x-type.page-title>
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
        </div>
    </div>
</x-layout.app>
