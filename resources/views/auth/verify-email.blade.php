<x-layout.app>
    <x-type.page-title class>Verify Email</x-type.page-title>

    <div class="mt-8 space-y-8">
        <p>
            Thanks for signing up! Before getting started, could you verify your
            email address by clicking on the link we just emailed to you? If you
            didn\'t receive the email, we will gladly send you another.
        </p>

        @if (session("status") == "verification-link-sent")
            <p class="text-success">
                A new verification link has been sent to the email address you
                provided during registration.
            </p>
        @endif

        <div class="mt-4 flex items-center justify-between">
            <form method="POST" action="{{ route("verification.send") }}">
                @csrf
                <input
                    type="submit"
                    value="Resend Verification Email"
                    class="btn btn-primary"
                />
            </form>

            <form method="POST" action="{{ route("logout") }}">
                @csrf
                <input type="submit" value="Log out" class="link" />
            </form>
        </div>
    </div>
</x-layout.app>
