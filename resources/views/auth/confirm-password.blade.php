<x-app-layout>
    <p>
        This is a secure area of the application. Please confirm your password before continuing.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <x-form.input name='password' label='Password' type='password' required autofocus />
        <div class="flex justify-end mt-4">
            <input type='submit' value='Confirm' class='btn btn-primary' />
        </div>
    </form>
</x-app-layout>
