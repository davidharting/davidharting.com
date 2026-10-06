<div
    x-data="{
        files: [],
        async pick(event) {
            const picked = Array.from(event.target.files);
            this.files = picked.map((file) => ({
                name: file.name,
                size: file.size,
                type: file.type || '(empty)',
                read: null,
                status: 'reading',
                progress: 0,
            }));
            const copies = [];
            for (const [index, file] of picked.entries()) {
                const bytes = await file.arrayBuffer();
                this.files[index].read = bytes.byteLength;
                this.files[index].status = 'waiting';
                copies.push(new File([bytes], file.name, { type: file.type }));
            }
            event.target.value = '';
            this.uploadAll(copies);
        },
        async uploadAll(picked) {
            for (const [index, file] of picked.entries()) {
                this.files[index].status = 'uploading';
                const ok = await new Promise((resolve) => {
                    $wire.upload(
                        'photos.' + index,
                        file,
                        () => resolve(true),
                        () => resolve(false),
                        (event) => (this.files[index].progress = event.detail.progress),
                    );
                });
                this.files[index].status = ok ? 'uploaded' : 'failed';
            }
            const browserInfo = this.files.map(({ name, size, type, read }) => ({ name, size, type, read }));
            if (this.files.some((file) => file.status === 'uploaded')) {
                await $wire.process(browserInfo);
            }
        },
    }"
    @if ($isProcessing) wire:poll.3s @endif
>
    <div class="card bg-base-200 mb-6">
        <div class="card-body gap-4">
            <p>
                Pick up to 10 photos (try a Live Photo too). Each uploads straight to R2, then the worker runs
                <code>vipsthumbnail</code>
                .
            </p>
            <label class="label gap-2">
                <input type="checkbox" class="toggle" wire:model.live="keepAcl" />
                Sign with
                <code>x-amz-acl: private</code>
                (Livewire's default)
            </label>
            <div wire:ignore>
                <input type="file" accept="image/*" multiple class="file-input" @change="pick($event)" />
            </div>
            <ul class="flex flex-col gap-1">
                <template x-for="file in files">
                    <li>
                        <span x-text="file.name"></span>
                        ·
                        <span x-text="file.type"></span>
                        ·
                        <span x-text="(file.size / 1024 / 1024).toFixed(1) + ' MB'"></span>
                        ·
                        <span x-text="file.read === null ? '' : 'read ' + (file.read / 1024 / 1024).toFixed(1) + ' MB'"></span>
                        ·
                        <span
                            class="badge"
                            :class="file.status === 'failed' ? 'badge-error' : 'badge-neutral'"
                            x-text="file.status === 'uploading' ? file.progress + '%' : file.status"
                        ></span>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    <div class="flex flex-col gap-6">
        @foreach ($entries as $entry)
            <div class="card bg-base-100 border-base-300 border" wire:key="{{ $entry["meta"]["id"] ?? $loop->index }}">
                <div class="card-body gap-3">
                    <h2 class="card-title">{{ $entry["meta"]["client_original_name"] ?? "?" }}</h2>
                    @if ($entry['thumb_url'])
                        <a href="{{ $entry["web_url"] }}" target="_blank">
                            <img src="{{ $entry["thumb_url"] }}" alt="Thumbnail" class="rounded-box" />
                        </a>
                    @endif

                    <div class="flex gap-2">
                        @if ($entry['web_url'])
                            <a href="{{ $entry["web_url"] }}" class="btn btn-sm" target="_blank">Web copy</a>
                        @endif

                        @if ($entry['original_url'])
                            <a href="{{ $entry["original_url"] }}" class="btn btn-sm" target="_blank">Original</a>
                        @endif
                    </div>
                    @if ($entry['result'] === null)
                        <span class="badge badge-warning">Processing…</span>
                    @endif

                    <details>
                        <summary>Details</summary>
                        <pre class="overflow-x-auto text-xs">{{ json_encode(["meta" => $entry["meta"], "result" => $entry["result"]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                </div>
            </div>
        @endforeach
    </div>
</div>
