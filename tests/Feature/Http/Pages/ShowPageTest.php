<?php

use App\Models\Page;
use App\Models\User;
use Tests\TestCase;

test('404 if page not found', function () {
    /** @var TestCase $this */
    $response = $this->get('/pages/some-fake-slug');
    $response->assertNotFound();
});

test('404 if page not published for non-admin', function () {
    /** @var TestCase $this */
    $page = Page::factory()->unpublished()->create();
    $response = $this->get('/pages/'.$page->slug);
    $response->assertNotFound();
});

test('404 if page not published for guest', function () {
    /** @var TestCase $this */
    $page = Page::factory()->unpublished()->create();
    $response = $this->get('/pages/'.$page->slug);
    $response->assertNotFound();
});

test('admin can view unpublished pages', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);
    $page = Page::factory()->unpublished()->create([
        'title' => 'Draft Page',
        'markdown_content' => 'This is a draft',
    ]);

    $this->actingAs($admin);
    $response = $this->get('/pages/'.$page->slug);
    $response->assertSuccessful();
    $response->assertSee('Draft Page');
    $response->assertSee('This is a draft');
});

test('show published page', function () {
    /** @var TestCase $this */
    $page = Page::factory()->create([
        'is_published' => true,
        'title' => 'Test Page Title',
        'markdown_content' => '**Bold text** and *italic text*',
    ]);

    $response = $this->get('/pages/'.$page->slug);
    $response->assertSuccessful();
    $response->assertSee('Test Page Title');
    $response->assertSee('<strong>Bold text</strong>', false);
    $response->assertSee('<em>italic text</em>', false);
});

test('show page includes back link', function () {
    /** @var TestCase $this */
    $page = Page::factory()->create([
        'is_published' => true,
        'title' => 'Test Page',
    ]);

    $response = $this->get('/pages/'.$page->slug);
    $response->assertSuccessful();
    $response->assertSee('Back to all pages');
    $response->assertSee(route('pages.index'));
});

test('show page has correct meta tags', function () {
    /** @var TestCase $this */
    $page = Page::factory()->create([
        'is_published' => true,
        'title' => 'About Us',
        'markdown_content' => 'Learn more about our company',
    ]);

    $response = $this->get('/pages/'.$page->slug);
    $response->assertSuccessful();
    $response->assertSeeHtml('<title>About Us</title>');
});

test('code blocks are rendered for syntax highlighting', function () {
    /** @var TestCase $this */
    $page = Page::factory()->create([
        'is_published' => true,
        'title' => 'Code example',
        'markdown_content' => <<<'MD'
Here is some JavaScript:

```javascript
const greeting = 'hello';
console.log(greeting);
```
MD,
    ]);

    $response = $this->get('/pages/'.$page->slug);
    $response->assertSuccessful();
    $response->assertSee('<pre><code class="language-javascript">', false);
    $response->assertSeeText("const greeting");
});
