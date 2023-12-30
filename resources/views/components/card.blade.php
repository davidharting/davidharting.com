<section class="card bg-base-200">
    <div class="card-body">
        @isset($title)
            <header class="card-title">
                {{ $title }}
            </header>
        @endisset

        {{ $slot }}
    </div>
</section>
