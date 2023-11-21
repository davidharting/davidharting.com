<div>
    <form wire:submit="click">
        <button type='submit' class='btn btn-primary'>
            Click!
        </button>
    </form>

    <p>
        This button has been clicked {{ $this->total_count }} times.
    </p>

    @unless (Auth::guest())
        <p>
            You have clicked the button {{ $this->user_count }} times.
        </p>
    @endunless

</div>
