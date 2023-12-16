<x-mail::message>
You requested a link to a Scorecard on [davidharting.com]({{ config('app.url') }}). Here it is!

<x-mail::button url="{{ route('scorecards.show', $scorecard) }}">
    {{ $scorecard->title }}
</x-mail::button>

Thanks,


David Harting
</x-mail::message>
