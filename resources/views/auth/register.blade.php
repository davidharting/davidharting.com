<x-layout.app>
    <x-type.page-title>Sign up</x-type.page-title>

    <form method="POST" class="mt-8 space-y-8" action="{{ route("register") }}">
        @csrf

        <x-form.input name="name" label="Name" required autofocus />
        <x-form.input name="email" label="Email" required />
        <x-form.input name="password" type="password" label="Password" required />
        <x-form.input name="password_confirmation" type="password" label="Confirm Password" required />

        <div class="mt-4 flex items-center justify-end">
            <a class="link" href="{{ route("login") }}">Already registered?</a>

            <input type="submit" class="btn btn-primary ml-4" value="Register" />
        </div>
    </form>
</x-layout.app>
