<x-mail::message>
# {{ $greeting }}

{{ $intro }}

@foreach ($copies as $copy)
## {{ $copy->heading }}

{{ $copy->markdownBody() }}

@if ($copy->photos->isNotEmpty())
Photos attached:

@foreach ($copy->photos as $photo)
- {{ $copy->filename($photo) }}{{ $photo->caption ? ': '.$photo->caption : '' }}
@endforeach

@endif
@if ($copy->photosLeftOut > 0)
{{ trans_choice(':count photo wasn\'t ready to attach. See it on the site.|:count photos weren\'t ready to attach. See them on the site.', $copy->photosLeftOut) }}

@endif
@endforeach
<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

Regards,<br>
{{ config('app.name') }}
</x-mail::message>
