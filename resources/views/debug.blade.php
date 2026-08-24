<x-layout.app title="Debug" description="Which deployment is serving this request">
    <x-slot:head>
        <meta name="robots" content="noindex, nofollow" />
    </x-slot:head>

    <div class="max-w-2xl space-y-6 pb-16">
        <x-type.page-title>Debug</x-type.page-title>

        <p class="text-base-content/70">
            Which deployment is serving this request. Same information as the <code class="kbd kbd-sm">/whoareyou</code>
            Telegram command.
        </p>

        <div class="overflow-x-auto">
            <table class="table">
                <tbody>
                    @foreach ($values as $key => $value)
                        <tr>
                            <th class="font-mono whitespace-nowrap">{{ $key }}</th>
                            <td class="font-mono break-all">
                                @if ($key === 'GIT_COMMIT' && $commitUrl)
                                    <a class="link link-primary" href="{{ $commitUrl }}">{{ $value }}</a>
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layout.app>
