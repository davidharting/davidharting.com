<?php

use App\Filament\Resources\Notes\Pages\CreateNote;
use App\Filament\Resources\Notes\Pages\EditNote;
use App\Filament\Resources\Notes\Pages\ListNotes;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\PhotoInspector;
use Tests\TestCase;

test('admin can list notes', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    Note::factory()->count(3)->create();

    Livewire::actingAs($admin)
        ->test(ListNotes::class)
        ->assertSuccessful();
});

test('admin can create a note', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(CreateNote::class)
        ->fillForm([
            'slug' => 'test-note',
            'title' => 'Test Note',
            'markdown_content' => 'This is a test note',
            'visible' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('notes', [
        'slug' => 'test-note',
        'title' => 'Test Note',
        'markdown_content' => 'This is a test note',
        'visible' => true,
    ]);
});

test('admin can edit a note', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);
    $note = Note::factory()->create([
        'title' => 'Original Title',
        'markdown_content' => 'Original content',
    ]);

    Livewire::actingAs($admin)
        ->test(EditNote::class, [
            'record' => $note->slug,
        ])
        ->fillForm([
            'title' => 'Updated Title',
            'markdown_content' => 'Updated content',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('notes', [
        'slug' => $note->slug,
        'title' => 'Updated Title',
        'markdown_content' => 'Updated content',
    ]);
});

test('markdown editor uses public disk and note slug directory for file attachments', function () {
    /** @var TestCase $this */
    Storage::fake('public');

    $admin = User::factory()->create(['is_admin' => true]);
    $note = Note::factory()->create(['slug' => 'my-note']);

    $instance = Livewire::actingAs($admin)
        ->test(EditNote::class, ['record' => $note->slug])
        ->instance();

    $editor = $instance->getSchema('form')->getComponentByStatePath('markdown_content');

    expect($editor->getFileAttachmentsDiskName())->toBe('public');
    expect($editor->getFileAttachmentsDirectory())->toBe('notes/my-note');
});

test('markdown editor uses date-based directory for file attachments on new note', function () {
    /** @var TestCase $this */
    Storage::fake('public');

    $admin = User::factory()->create(['is_admin' => true]);

    $instance = Livewire::actingAs($admin)
        ->test(CreateNote::class)
        ->instance();

    $editor = $instance->getSchema('form')->getComponentByStatePath('markdown_content');

    expect($editor->getFileAttachmentsDiskName())->toBe('public');
    expect($editor->getFileAttachmentsDirectory())->toBe('notes/'.now()->toDateString());
});

test('a photo pasted into the markdown editor is saved as a resized WebP on the public disk, and the original is not kept', function () {
    /** @var TestCase $this */
    Storage::fake('public');
    Storage::fake('tmp-for-tests');

    $admin = User::factory()->create(['is_admin' => true]);
    $note = Note::factory()->create(['slug' => 'my-note']);
    $photo = UploadedFile::fake()->createWithContent('iphone.jpg', file_get_contents(PhotoInspector::fixture('iphone.jpg')));

    $page = Livewire::actingAs($admin)
        ->test(EditNote::class, ['record' => $note->slug])
        ->set('componentFileAttachments.data.markdown_content', $photo);
    $editor = $page->instance()->getSchema('form')->getComponentByStatePath('markdown_content');
    $url = $page->instance()->callSchemaComponentMethod($editor->getKey(), 'saveUploadedFileAttachmentAndGetUrl');

    [$path] = Storage::disk('public')->allFiles();
    expect($path)->toStartWith('notes/my-note/')->toEndWith('.webp')
        ->and($url)->toBe(Storage::disk('public')->url($path))
        ->and(PhotoInspector::inspect(Storage::disk('public')->path($path)))->toMatchArray(['width' => 1536, 'height' => 2048, 'exif_tags' => 0])
        ->and(Storage::disk('tmp-for-tests')->allFiles())->toBeEmpty();
});
