<div>
    <div class="stats w-full">
        <div class="stat">
            <div class="stat-title">Total Clicks</div>
            <div class="stat-value">
                {{ $this->totalCount }}
            </div>
            <div class="stat-desc">All clicks from everyone</div>
        </div>

        @auth
            <div class="stat">
                <div class="stat-title">Your Clicks</div>
                <div class="stat-value">{{ $this->userCount }}</div>
                <div class="stat-desc">Only your clicks</div>
            </div>
        @endauth
    </div>

    <form wire:submit="click">
        <button type="submit" class="btn btn-primary w-full">Click!</button>
    </form>
</div>
