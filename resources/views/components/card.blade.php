<section class="card bg-slate-800 text-neutral-content">
    <div class="card-body">
        @isset($title)
            <header class='card-title'>
                {{ $title }}
            </header>
        @endisset
        {{ $slot }}
    </div>
</section>
