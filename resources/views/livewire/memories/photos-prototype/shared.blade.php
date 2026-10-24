{{--
    PROTOTYPE (#299), throwaway. Client-side only: nothing here uploads or saves.
    Three variants of adding, captioning and ordering photos in the editor,
    switched with ?variant=A|B|C. ?photos=none|mixed|full picks the starting set.
--}}
@php
    $sample = fn (string $name) => [
        'thumb' => asset("prototype-memory-photos/{$name}-thumb.jpg"),
        'web' => asset("prototype-memory-photos/{$name}-web.jpg"),
    ];
    $ready = fn (int $id, string $name, string $caption = '') => [
        'id' => $id,
        'state' => 'ready',
        'progress' => 100,
        'caption' => $caption,
        ...$sample($name),
    ];
    $set = $this->prototypePhotos;
    $initialPhotos = match ($set) {
        'none' => [],
        'full' => [
            $ready(1, 'snowy-street', 'Our street before anyone was up'),
            $ready(2, 'night-concert', 'Didn’t know half the songs'),
            $ready(3, 'garden', 'Eleven tomatoes'),
            $ready(4, 'sunset-beach'),
            $ready(5, 'mountains', 'Somewhere in hour six'),
            $ready(6, 'kitchen-light', 'The hallway, finally'),
            $ready(7, 'snowy-street'),
            $ready(8, 'garden', 'Pumpkins, again'),
            $ready(9, 'sunset-beach', 'Last swim'),
            $ready(10, 'mountains'),
        ],
        default => [
            $ready(1, 'garden', 'The pumpkin patch'),
            $ready(2, 'sunset-beach'),
            $ready(3, 'mountains', 'Somewhere in hour six'),
            ['id' => 4, 'state' => 'processing', 'progress' => 100, 'caption' => '', ...$sample('kitchen-light')],
            ['id' => 5, 'state' => 'uploading', 'progress' => 45, 'caption' => '', ...$sample('night-concert')],
            ['id' => 6, 'state' => 'failed', 'progress' => 0, 'caption' => '', ...$sample('snowy-street')],
        ],
    };
@endphp

<script>
    window.photoPrototype = (initial) => ({
        photos: initial,
        max: 10,
        notice: null,
        undo: null,
        noticeTimer: null,
        sheet: null,
        arranging: false,
        picked: [],
        nextId: 100,
        get room() {
            return this.max - this.photos.length;
        },
        get sheetPhoto() {
            return this.photos.find((photo) => photo.id === this.sheet);
        },
        say(text, undo = null) {
            clearTimeout(this.noticeTimer);
            this.notice = text;
            this.undo = undo;
            this.noticeTimer = setTimeout(() => {
                this.notice = null;
                this.undo = null;
            }, 6000);
        },
        pick(event) {
            const files = Array.from(event.target.files);
            event.target.value = '';
            const room = this.room;
            const take = files.slice(0, room);
            if (files.length > room) {
                this.say(`A memory holds ${this.max} photos, so only the first ${room} of the ${files.length} you picked were added.`);
            }
            take.forEach((file) => {
                const photo = {
                    id: this.nextId++,
                    state: 'uploading',
                    progress: 0,
                    caption: '',
                    thumb: URL.createObjectURL(file),
                    web: URL.createObjectURL(file),
                };
                this.photos.push(photo);
                this.simulate(photo.id);
            });
        },
        simulate(id) {
            const tick = setInterval(() => {
                const photo = this.photos.find((p) => p.id === id);
                if (! photo) return clearInterval(tick);
                photo.progress = Math.min(100, photo.progress + 15 + Math.random() * 20);
                if (photo.progress >= 100) {
                    clearInterval(tick);
                    photo.state = 'processing';
                    setTimeout(() => {
                        photo.state = 'ready';
                        this.$dispatch('memory-saved');
                    }, 2500);
                }
            }, 300);
        },
        retry(id) {
            const photo = this.photos.find((p) => p.id === id);
            photo.state = 'uploading';
            photo.progress = 0;
            this.simulate(id);
        },
        index(id) {
            return this.photos.findIndex((p) => p.id === id);
        },
        move(id, delta) {
            const from = this.index(id);
            const to = from + delta;
            if (to < 0 || to >= this.photos.length) return;
            const [photo] = this.photos.splice(from, 1);
            this.photos.splice(to, 0, photo);
            this.$dispatch('memory-saved');
        },
        moveTo(id, position) {
            const from = this.index(id);
            const [photo] = this.photos.splice(from, 1);
            this.photos.splice(position, 0, photo);
            this.$dispatch('memory-saved');
        },
        remove(id) {
            const from = this.index(id);
            const [photo] = this.photos.splice(from, 1);
            this.sheet = null;
            this.$dispatch('memory-saved');
            this.say('Photo removed.', () => {
                this.photos.splice(from, 0, photo);
                this.notice = null;
                this.undo = null;
            });
        },
        captioned() {
            this.$dispatch('memory-saved');
        },
        startArranging() {
            this.arranging = true;
            this.picked = [];
        },
        tapToOrder(id) {
            if (this.picked.includes(id)) {
                this.picked = this.picked.filter((p) => p !== id);
                return;
            }
            this.picked.push(id);
            if (this.picked.length === this.photos.length) this.finishArranging();
        },
        finishArranging() {
            const rest = this.photos.filter((p) => ! this.picked.includes(p.id));
            this.photos = [...this.picked.map((id) => this.photos.find((p) => p.id === id)), ...rest];
            this.arranging = false;
            this.picked = [];
            this.$dispatch('memory-saved');
        },
    });
</script>

@php
    $variant = $this->prototypeVariant;
    $placement = $variant === 'C' ? 'above' : 'below';
@endphp

@if ($placement === $where)
    <div
        wire:ignore
        x-data="photoPrototype(@js($initialPhotos))"
        x-on:keydown.escape.window="sheet = null"
    >
        @include('livewire.memories.photos-prototype.variant-'.strtolower($variant === 'D' ? 'A' : $variant))

        <div
            x-show="notice"
            x-transition.opacity
            class="fixed inset-x-4 bottom-16 z-40 flex justify-center"
            style="display: none"
        >
            <div role="status" class="alert bg-neutral text-neutral-content flex font-sans text-sm shadow-lg">
                <span x-text="notice"></span>
                <button type="button" class="btn btn-sm btn-ghost" x-show="undo" x-on:click="undo()">Undo</button>
            </div>
        </div>
    </div>
@endif
