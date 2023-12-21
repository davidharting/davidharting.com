<div>
    <x-type.page-title>Interval timer</x-type.page-title>

    <div class='mt-12 w-full flex justify-center'>
        <div x-data='interval'>
            <div class="radial-progress text-primary shadow-md text-3xl" x-bind:style='style' role="progressbar"
                x-text='time'>
            </div>
        </div>
    </div>
</div>

@script
    <script>
        Alpine.data('interval', () => ({
            time: 0,
            interval: 15,
            audio: new window.Howl({
                src: ['sounds/bell.mp3']
            }),
            style() {
                return `--value: ${this.time / this.interval * 100}; --size: 12rem;`
            },
            init() {
                setInterval(() => {
                    if (this.time < this.interval) {
                        this.time = this.time + 1
                    } else {
                        this.time = 0
                        this.audio.play()
                    }
                }, 1000)
            },
        }))
    </script>
@endscript
