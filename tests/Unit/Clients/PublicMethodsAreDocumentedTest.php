<?php

/*
 * Every public method of an API client says what it is for, in a summary
 * line at the top of its docblock. A docblock holding only tags, such as
 * @throws, doesn't count. Constructors are exempt: their promoted
 * properties document themselves.
 *
 * Pest's toHaveMethodsDocumented() would accept a tags-only docblock and
 * also demands docblocks on private methods, so this checks by reflection.
 */

/**
 * The summary line of a docblock: its first line that isn't empty or a tag.
 */
function docblockSummary(string|false $docComment): ?string
{
    if ($docComment === false) {
        return null;
    }

    foreach (preg_split('/\R/', $docComment) as $line) {
        $text = trim(ltrim(trim($line), '/*'));

        if ($text === '') {
            continue;
        }

        return str_starts_with($text, '@') ? null : $text;
    }

    return null;
}

/**
 * Every class under app/Clients, by fully qualified name.
 *
 * @return list<class-string>
 */
function clientClasses(): array
{
    $root = dirname(__DIR__, 3).'/app/Clients';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $classes = [];

    foreach ($files as $file) {
        if ($file->getExtension() === 'php') {
            $relative = substr($file->getPathname(), strlen($root) + 1, -4);
            $classes[] = 'App\\Clients\\'.str_replace('/', '\\', $relative);
        }
    }

    sort($classes);

    return $classes;
}

test('every public method in app/Clients has a summary line', function () {
    $undocumented = [];

    foreach (clientClasses() as $class) {
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor()) {
                continue;
            }

            if (docblockSummary($method->getDocComment()) === null) {
                $undocumented[] = "{$class}::{$method->getName()}()";
            }
        }
    }

    expect(clientClasses())->not->toBeEmpty()
        ->and($undocumented)->toBe([]);
});

test('a docblock holding only tags has no summary line', function () {
    expect(docblockSummary("/**\n * @throws RuntimeException\n */"))->toBeNull()
        ->and(docblockSummary(false))->toBeNull()
        ->and(docblockSummary("/**\n * Looks a word up.\n *\n * @throws RuntimeException\n */"))->toBe('Looks a word up.');
});
