<x-app-layout>
    <x-type.page-title>Profile</x-type.page-title>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-card title='Profile Information'>
                <x-profile.info :user='$user' />
            </x-card>

            <x-card title='Update Password'>
                <x-profile.update-password />
            </x-card>

            <x-card title='Delete Your Account'>
                <x-profile.delete-user />
            </x-card>
        </div>
    </div>
</x-app-layout>
