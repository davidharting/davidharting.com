<?php

use App\Models\Page;
use App\Models\User;
use Tests\TestCase;

test('shows pages index', function () {
    /** @var TestCase $this */
    $response = $this->get('/pages');
    $response->assertSuccessful();
    $response->assertSee('Pages');
});

test('lists published pages ordered by updated_at desc', function () {
    /** @var TestCase $this */
    $page1 = Page::factory()->create([
        'title' => 'First Page',
        'is_published' => true,
        'updated_at' => now()->subDays(2),
    ]);

    $page2 = Page::factory()->create([
        'title' => 'Second Page',
        'is_published' => true,
        'updated_at' => now()->subDays(1),
    ]);

    $page3 = Page::factory()->create([
        'title' => 'Third Page',
        'is_published' => true,
        'updated_at' => now(),
    ]);

    $response = $this->get('/pages');
    $response->assertSuccessful();

    // Should see all published pages
    $response->assertSeeInOrder(['Third Page', 'Second Page', 'First Page']);
});

test('does not show unpublished pages', function () {
    /** @var TestCase $this */
    $publishedPage = Page::factory()->create([
        'title' => 'Published Page',
        'is_published' => true,
    ]);

    $unpublishedPage = Page::factory()->unpublished()->create([
        'title' => 'Unpublished Page',
    ]);

    $response = $this->get('/pages');
    $response->assertSuccessful();
    $response->assertSee('Published Page');
    $response->assertDontSee('Unpublished Page');
});

test('shows message when no pages exist', function () {
    /** @var TestCase $this */
    $response = $this->get('/pages');
    $response->assertSuccessful();
    $response->assertSee('No pages yet');
});

test('page links work', function () {
    /** @var TestCase $this */
    $page = Page::factory()->create([
        'title' => 'Test Page',
        'slug' => 'test-page',
        'is_published' => true,
    ]);

    $response = $this->get('/pages');
    $response->assertSuccessful();
    $response->assertSee(route('pages.show', $page->slug));
});

test('admin can see unpublished pages in index', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $publishedPage = Page::factory()->create([
        'title' => 'Published Page',
        'is_published' => true,
    ]);

    $unpublishedPage = Page::factory()->create([
        'title' => 'Unpublished Page',
        'is_published' => false,
    ]);

    $this->actingAs($admin);
    $response = $this->get('/pages');
    $response->assertSuccessful();
    $response->assertSee('Published Page');
    $response->assertSee('Unpublished Page');
});

test('unpublished pages show indicator for admin', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $unpublishedPage = Page::factory()->create([
        'title' => 'Draft Page',
        'is_published' => false,
    ]);

    $this->actingAs($admin);
    $response = $this->get('/pages');
    $response->assertSuccessful();
    $response->assertSee('Draft Page');
    $response->assertSee('Unpublished');
});

test('published pages do not show unpublished indicator', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $publishedPage = Page::factory()->create([
        'title' => 'Published Page',
        'is_published' => true,
    ]);

    $this->actingAs($admin);
    $response = $this->get('/pages');
    $response->assertSuccessful();
    $response->assertSee('Published Page');
    $response->assertDontSee('Unpublished');
});
