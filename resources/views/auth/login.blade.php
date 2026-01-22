<x-layout.app>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <h1 class="font-serif text-6xl">Log in</h1>

    <form method="POST" action="{{ route("login") }}" class="mt-8 space-y-8">
        @csrf
        <x-form.input
            name="email"
            type="email"
            label="Email"
            required
            autofocus
            autocomplete
        />
        <x-form.input
            name="password"
            type="password"
            label="Password"
            required
            autocomplete="current-password"
        />
        <x-form.checkbox name="remember" label="Remember me" />

        <div class="flex items-center justify-end mt-4">
            @if (Route::has("password.request"))
                <a class="link" href="{{ route("password.request") }}">
                    Forgot your password?
                </a>
            @endif

            <input type="submit" class="btn btn-primary ml-3" value="Log in" />
        </div>
    </form>
</x-layout.app>
