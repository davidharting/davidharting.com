@props(['memory'])

<script>
    (() => {
        const memory = document.getElementById(@js("memory-{$memory->id}"));
        const key = @js("memory-unveiled-{$memory->id}");
        let unveiledBefore = true;
        try {
            const seen = localStorage.getItem(key) !== null;
            localStorage.setItem(key, '1');
            unveiledBefore = seen;
        } catch {}
        memory.classList.toggle('moment-unveil', !unveiledBefore);
    })();
</script>
