<x-app-layout>
    <h1 class='font-serif text-6xl'>Sign up</h1>

    <form method="POST" class='mt-8 space-y-8' action="{{ route('register') }}">
        @csrf
        
        <x-form.input name='name' label='Name' required autofocus />
        <x-form.input name='email' label='Email' required />
        <x-form.input name='password' type='password' label='Password' required />
        <x-form.input name='password_confirmation' type='password' label='Confirm Password' required />
        
        
        <div class="flex items-center justify-end mt-4">
            <a class="link" href="{{ route('login') }}">Already registered?</a>

            <input type='submit' class="btn btn-primary" value='Register' />
        </div>
    </form>
</x-app-layout>
