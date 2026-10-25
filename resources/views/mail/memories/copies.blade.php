<x-mail::message>
# {{ $greeting }}

{{ $intro }}

@foreach ($copies as $copy)
## {{ $copy->heading }}

{{ $copy->markdownBody() }}

@if ($copy->photos->isNotEmpty())
Photos attached:

@foreach ($copy->photos as $photo)
- {{ $copy->photoLine($photo) }}
@endforeach

@endif
@if ($copy->photosLeftOut > 0)
{{ trans_choice(':count photo isn\'t attached. See it on the site.|:count photos aren\'t attached. See them on the site.', $copy->photosLeftOut) }}

@endif
@endforeach
<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

Regards,<br>
{{ config('app.name') }}
</x-mail::message>
