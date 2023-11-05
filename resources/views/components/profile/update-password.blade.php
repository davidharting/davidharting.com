<div>

    <p>Ensure your account is using a long, random password to stay secure.</p>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')
        
        <x-form.input type='password' name='current_password' required autofocus />
        <x-form.input type='password' name='password' required />
        <x-form.input type='password' name='password_confirmation' required />

 
        <div class="flex items-center justify-end gap-4">
            <input type='submit' value='Save' class='btn btn-primary' />

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>

</div>
