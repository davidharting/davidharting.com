---
globs: tests/**/*Test.php
---

# Laravel Testing Rules

## Pest tests that use `$this`

Always add `/** @var TestCase $this */` as the first line inside every Pest test closure. This enables IDE type resolution for `$this` and makes it easy to use `$this` assertions at any point without needing to add the annotation later.

```php
test('example', function () {
    /** @var TestCase $this */
    $this->assertDatabaseHas('notes', ['slug' => 'foo']);
});
```

## assertSee vs assertSeeText

Use the appropriate assertion based on what you're testing:

- **`assertSeeText`**: Use when asserting about visible text content (what the user sees)
- **`assertSee`**: Use when asserting about HTML content (attributes, tags, raw markup)

### Examples

```php
// Checking visible link text - use assertSeeText
$response->assertSeeText('Notes');

// Checking href attribute value - use assertSee
$response->assertSee(route('notes.index'));

// Checking HTML structure - use assertSee or assertSeeHtml
$response->assertSeeHtml('<link rel="alternate" type="application/atom+xml"');
```
