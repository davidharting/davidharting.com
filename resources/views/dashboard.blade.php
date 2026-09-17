<x-layout.app>
    <x-type.page-title class="mb-8">Dashboard</x-type.page-title>

    <ul>
        <li><a class="link" href="{{ Route("profile.edit") }}">Profile</a></li>
        <li>
            <form method="POST" action="{{ route("logout") }}">
                @csrf
                <input type="submit" value="Log out" class="link" />
            </form>
        </li>
    </ul>
</x-layout.app>
