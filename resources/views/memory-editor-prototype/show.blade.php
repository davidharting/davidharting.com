{{-- PROTOTYPE, throwaway (#272): the memory editor variants. Nothing here saves; autosave and Share are faked in Alpine. --}}
@php
    $initial = [
        'body' => $body,
        'state' => $state,
        'otherShared' => $otherShared,
    ];
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $monthName }} · memory editor prototype</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-base-100 font-serif antialiased">
    <div x-data="memoryEditor(@js($initial))">
        @include("memory-editor-prototype.{$variant}")
    </div>

    @unless (request()->boolean('bare'))
        @include('memory-editor-prototype._switcher')
    @endunless

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('memoryEditor', (initial) => ({
                body: initial.body,
                otherShared: initial.otherShared,
                status:
                    { fresh: 'empty', saving: 'saving', failed: 'failed', conflict: 'conflict' }[initial.state] ??
                    'saved',
                shared: ['waiting', 'revealed', 'editing-shared'].includes(initial.state),
                revealed: ['revealed', 'editing-shared'].includes(initial.state),
                justShared: ['waiting', 'revealed'].includes(initial.state),
                confirming: initial.state === 'sharing',
                screen:
                    initial.state === 'sharing'
                        ? 'review'
                        : ['waiting', 'revealed'].includes(initial.state)
                          ? 'done'
                          : 'write',
                timer: null,

                typed() {
                    this.status = 'saving';
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => (this.status = navigator.onLine ? 'saved' : 'failed'), 1200);
                },
                retry() {
                    this.typed();
                },
                resolveConflict() {
                    this.status = 'saved';
                },
                share() {
                    this.shared = true;
                    this.revealed = this.otherShared;
                    this.justShared = true;
                    this.confirming = false;
                    this.screen = 'done';
                },
                keepEditing() {
                    this.justShared = false;
                    this.screen = 'write';
                },
            }));
        });
    </script>
    @livewireScripts
</body>
</html>
