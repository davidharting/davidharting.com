<x-layout.app>
    <p>This is a secure area of the application. Please confirm your password before continuing.</p>

    <form method="POST" action="{{ route("password.confirm") }}">
        @csrf
        <x-form.input name="password" label="Password" type="password" required autofocus />
        <div class="mt-4 flex justify-end">
            <input type="submit" value="Confirm" class="btn btn-primary" />
        </div>
    </form>
</x-layout.app>
