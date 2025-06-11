<x-slot:title>{{ $note->title }}</x-slot>

<x-slot:description>
    {{ $this->description() }}
</x-slot>

<div>
    <article class="prose dark:prose-invert prose-sm prose-pink">
        <p class="text-sm text-gray-600">
            {{ $note->publicationDate() }}
        </p>

        <x-notes.prose :note="$note" />

    </article>
    <div class='mt-8'>
    <flux:link class='text-sm' href="{{ route('notes.index') }}">
        Back to all notes
    </flux:link>
</div>
</div>
