<x-app-layout>
    <x-type.page-title>Profile</x-type.page-title>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-card title='Profile Information'>
                @include('profile.partials.update-profile-information-form')
            </x-card>

            <x-card title='Update Password'>
                @include('profile.partials.update-password-form')
            </x-card>

            <x-card title='Delete Your Account'>
                @include('profile.partials.delete-user-form')
            </x-card>
        </div>
    </div>
</x-app-layout>
