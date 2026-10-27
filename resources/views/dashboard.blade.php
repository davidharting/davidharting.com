<x-layout.app>
    <x-type.page-title class="mb-8">Account</x-type.page-title>

    <ul class="menu menu-lg bg-base-200 rounded-box w-full max-w-md">
        <li><a href="{{ route("profile.edit") }}">Profile</a></li>
        @can('administrate')
            <li><a href="{{ route("admin.index") }}">Admin</a></li>
        @endcan
    </ul>

    <form method="POST" action="{{ route("logout") }}" class="mt-8">
        @csrf
        <input type="submit" value="Log out" class="btn" />
    </form>
</x-layout.app>
