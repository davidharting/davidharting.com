<section class="card shadow">
    <div class="card-body">
        @isset($title)
            <header class="card-title">
                {{ $title }}
            </header>
        @endisset

        {{ $slot }}
    </div>
</section>
