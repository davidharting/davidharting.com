<x-layout.app>
    <x-type.page-title>Reset Password</x-type.page-title>

    <form method="POST" class="mt-8" action="{{ route("password.store") }}">
        @csrf
        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route("token") }}" />
        <!-- The token only works for the address it was emailed to, so show that address rather than ask for it -->
        <input type="hidden" name="email" value="{{ old("email", $request->query("email")) }}" />
        <div>
            <p>Choose a new password for <strong>{{ old("email", $request->query("email")) }}</strong>.</p>
            @error('email')
                <div class="text-error text-sm">{{ $message }}</div>
            @enderror
        </div>
        <!-- Visible form fields -->
        <x-form.input name="password" label="Password" type="password" required autofocus />
        <x-form.input name="password_confirmation" label="Confirm Password" type="password" required />
        <input type="submit" value="Reset password" class="btn btn-primary" />
    </form>
</x-layout.app>
