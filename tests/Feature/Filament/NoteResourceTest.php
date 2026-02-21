<?php

use App\Filament\Resources\Notes\Pages\CreateNote;
use App\Filament\Resources\Notes\Pages\EditNote;
use App\Filament\Resources\Notes\Pages\ListNotes;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

test('markdown editor uses draft directory for file attachments on new note', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['is_admin' => true]);

    $instance = Livewire::actingAs($admin)
        ->test(CreateNote::class)
        ->instance();

    $editor = $instance->getSchema('form')->getComponentByStatePath('markdown_content');

    expect($editor->getFileAttachmentsDiskName())->toBe('public');
    expect($editor->getFileAttachmentsDirectory())->toBe('notes/draft');
});
