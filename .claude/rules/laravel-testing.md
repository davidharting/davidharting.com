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

## Test naming and describe blocks

Do not include the class under test in test string names — that is implied by the test file name.

When a test file covers multiple methods, group tests into `describe` blocks named after the method being tested.

```php
// ❌ Bad
test('SearchMedia returns found=false when no results', function () { ... });

// ✓ Good
describe('handle()', function () {
    test('returns found=false when no results', function () { ... });
});

describe('schema()', function () {
    test('enumerates valid media_type values', function () { ... });
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
