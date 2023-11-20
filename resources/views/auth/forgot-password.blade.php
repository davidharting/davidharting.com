<x-layout.app>
    <x-type.page-title>Forgot your password?</x-type.page-title>
    <div class='space-y-8'>

        <p>
            No problem. Just let us know your email address and we will email you a password reset link that will allow
            you to choose a new one.
        </p>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class='space-y-8'>
            @csrf
            <x-form.input name='email' label='Email' required autofocus />
            <div class='flex justify-end'>
                <input type='submit' value='Email me a reset link' class='btn btn-primary' />
            </div>
        </form>
    </div>
</x-layout.app>
