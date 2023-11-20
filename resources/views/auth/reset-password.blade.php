<x-layout.app>
    <x-type.page-title>Reset Password</x-type.page-title>

    <form method="POST" class='mt-8' action="{{ route('password.store') }}">
        @csrf
        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <!-- Visible form fields -->
        <x-form.input name='email' label='Email' required autofocus />
        <x-form.input name='password' label='Password' type='password' required />
        <x-form.input name='password_confirmation' label='Confirm Password' type='password' required />
        <input type='submit' value='Reset password' class='btn btn-primary' />
    </form>
</x-layout.app>
