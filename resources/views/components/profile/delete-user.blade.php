<div class="space-y-6">
    <p>
        Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your
        account, please download any data or information that you wish to retain.
    </p>
    </header>

    <div class='w-full flex justify-end'>

        <form method="post" action="{{ route('profile.destroy') }}">
            @csrf
            @method('delete')
            <div class="mt-6 flex justify-end">
                <input type='submit' value='Delete Account' class='btn btn-error' />
            </div>
        </form>
    </div>
