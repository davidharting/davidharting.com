<?php

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\PhotoInspector;
use Tests\TestCase;

test('admin can list pages', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    Page::factory()->count(3)->create();

    Livewire::actingAs($admin)
        ->test(ListPages::class)
        ->assertSuccessful();
});

test('admin can create a page', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(CreatePage::class)
        ->fillForm([
            'slug' => 'test-page',
            'title' => 'Test Page',
            'markdown_content' => 'This is a test page',
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('pages', [
        'slug' => 'test-page',
        'title' => 'Test Page',
        'markdown_content' => 'This is a test page',
        'is_published' => true,
    ]);
});

test('admin can create a page without slug and it auto-generates', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(CreatePage::class)
        ->fillForm([
            'title' => 'Auto Slug Page',
            'markdown_content' => 'Content here',
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('pages', [
        'slug' => 'auto-slug-page',
        'title' => 'Auto Slug Page',
    ]);
});

test('admin can edit a page', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);
    $page = Page::factory()->create([
        'title' => 'Original Title',
        'markdown_content' => 'Original content',
    ]);

    Livewire::actingAs($admin)
        ->test(EditPage::class, [
            'record' => $page->slug,
        ])
        ->fillForm([
            'title' => 'Updated Title',
            'markdown_content' => 'Updated content',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('pages', [
        'slug' => $page->slug,
        'title' => 'Updated Title',
        'markdown_content' => 'Updated content',
    ]);
});

test('admin can toggle page publication status', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);
    $page = Page::factory()->create(['is_published' => true]);

    Livewire::actingAs($admin)
        ->test(EditPage::class, [
            'record' => $page->slug,
        ])
        ->fillForm([
            'is_published' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('pages', [
        'slug' => $page->slug,
        'is_published' => false,
    ]);
});

test('markdown editor uses public disk and page slug directory for file attachments', function () {
    /** @var TestCase $this */
    Storage::fake('public');

    $admin = User::factory()->create(['is_admin' => true]);
    $page = Page::factory()->create(['slug' => 'my-page']);

    $instance = Livewire::actingAs($admin)
        ->test(EditPage::class, ['record' => $page->slug])
        ->instance();

    $editor = $instance->getSchema('form')->getComponentByStatePath('markdown_content');

    expect($editor->getFileAttachmentsDiskName())->toBe('public');
    expect($editor->getFileAttachmentsDirectory())->toBe('pages/my-page');
});

test('markdown editor uses date-based directory for file attachments on new page', function () {
    /** @var TestCase $this */
    Storage::fake('public');

    $admin = User::factory()->create(['is_admin' => true]);

    $instance = Livewire::actingAs($admin)
        ->test(CreatePage::class)
        ->instance();

    $editor = $instance->getSchema('form')->getComponentByStatePath('markdown_content');

    expect($editor->getFileAttachmentsDiskName())->toBe('public');
    expect($editor->getFileAttachmentsDirectory())->toBe('pages/'.now()->toDateString());
});

test('a photo pasted into the markdown editor is saved as a WebP in the page directory', function () {
    /** @var TestCase $this */
    Storage::fake('public');
    Storage::fake('tmp-for-tests');

    $admin = User::factory()->create(['is_admin' => true]);
    $page = Page::factory()->create(['slug' => 'about']);
    $photo = UploadedFile::fake()->createWithContent('iphone.jpg', file_get_contents(PhotoInspector::fixture('iphone.jpg')));

    $form = Livewire::actingAs($admin)
        ->test(EditPage::class, ['record' => $page->slug])
        ->set('componentFileAttachments.data.markdown_content', $photo);
    $editor = $form->instance()->getSchema('form')->getComponentByStatePath('markdown_content');
    $form->instance()->callSchemaComponentMethod($editor->getKey(), 'saveUploadedFileAttachmentAndGetUrl');

    expect(Storage::disk('public')->allFiles())->sequence(
        fn ($path) => $path->toStartWith('pages/about/')->toEndWith('.webp'),
    );
});
