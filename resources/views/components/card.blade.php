<section class="card shadow-sm">
    <div class="card-body">
        @isset($title)
            <header class="card-title">
                {{ $title }}
            </header>
        @endisset

        {{ $slot }}
    </div>
</section>
