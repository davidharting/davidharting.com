<x-layout.app>
    <x-type.page-title class="mb-8">Account</x-type.page-title>

    <ul class="space-y-4">
        <li><a class="link" href="{{ route("profile.edit") }}">Profile</a></li>
        @can('administrate')
            <li><a class="link" href="{{ route("admin.index") }}">Admin</a></li>
        @endcan
    </ul>

    <form method="POST" action="{{ route("logout") }}" class="mt-8">
        @csrf
        <input type="submit" value="Log out" class="btn" />
    </form>
</x-layout.app>
