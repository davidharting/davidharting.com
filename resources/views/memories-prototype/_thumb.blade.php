{{-- PROTOTYPE stand-in for a photo: a tinted block so tiles read as visual. --}}
@php
    $tints = ['from-primary/50 to-accent/40', 'from-secondary/40 to-primary/30', 'from-accent/50 to-secondary/30', 'from-info/30 to-primary/40', 'from-warning/40 to-accent/30'];
@endphp
<div {{ $attributes->merge(['class' => 'bg-gradient-to-br '.$tints[$seed % count($tints)].' rounded-box flex items-end p-2 font-sans text-xs opacity-90']) }}>
    <span class="opacity-60">{{ $label ?? 'photo' }}</span>
</div>
