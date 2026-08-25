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
                    @foreach ($facts as $fact)
                        <tr>
                            <th class="font-mono whitespace-nowrap">{{ $fact->label }}</th>
                            <td class="font-mono break-all">
                                @if ($fact->url)
                                    <a class="link link-primary" href="{{ $fact->url }}">{{ $fact->value }}</a>
                                @else
                                    {{ $fact->value }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layout.app>
