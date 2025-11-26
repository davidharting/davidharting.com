<?php

use App\Models\Note;
use Tests\TestCase;

describe('notes:convert-to-markdown command', function () {
    it('converts HTML content to Markdown with --force flag', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create([
            'content' => '<h1>Test Title</h1><p>Test paragraph.</p>',
            'markdown_content' => null,
        ]);

        $this->artisan('notes:convert-to-markdown --force')
            ->expectsOutput('Found 1 note(s) to convert')
            ->assertSuccessful();

        $note->refresh();
        expect($note->markdown_content)->not->toBeNull();
        expect($note->markdown_content)->toContain('Test Title');
        expect($note->markdown_content)->toContain('Test paragraph.');
    });

    it('skips notes that already have markdown_content', function () {
        /** @var TestCase $this */
        Note::factory()->create([
            'content' => '<p>HTML</p>',
            'markdown_content' => '**Markdown**',
        ]);

        $this->artisan('notes:convert-to-markdown')
            ->expectsOutput('No notes found to convert. All notes are already using Markdown!')
            ->assertSuccessful();
    });

    it('skips notes without content', function () {
        /** @var TestCase $this */
        Note::factory()->create([
            'title' => 'Just a title',
            'content' => null,
            'markdown_content' => null,
        ]);

        $this->artisan('notes:convert-to-markdown')
            ->expectsOutput('No notes found to convert. All notes are already using Markdown!')
            ->assertSuccessful();
    });

    it('does not modify data in dry-run mode (default)', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create([
            'content' => '<p>Test content</p>',
            'markdown_content' => null,
        ]);

        $this->artisan('notes:convert-to-markdown')
            ->expectsOutputToContain('DRY RUN mode')
            ->expectsOutputToContain('Would convert 1 note(s)')
            ->assertSuccessful();

        $note->refresh();
        expect($note->markdown_content)->toBeNull();
    });

    it('converts multiple notes with --force flag', function () {
        /** @var TestCase $this */
        Note::factory()->count(3)->create([
            'content' => '<p>HTML content</p>',
            'markdown_content' => null,
        ]);

        $this->artisan('notes:convert-to-markdown --force')
            ->expectsOutput('Found 3 note(s) to convert')
            ->expectsOutputToContain('Successfully converted 3 note(s)')
            ->assertSuccessful();

        expect(Note::whereNotNull('markdown_content')->count())->toBe(3);
    });

    it('preserves original content field with --force flag', function () {
        /** @var TestCase $this */
        $originalContent = '<h2>Original HTML</h2>';
        $note = Note::factory()->create([
            'content' => $originalContent,
            'markdown_content' => null,
        ]);

        $this->artisan('notes:convert-to-markdown --force')->assertSuccessful();

        $note->refresh();
        expect($note->content)->toBe($originalContent);
        expect($note->markdown_content)->not->toBeNull();
    });

    it('overwrites existing markdown_content with --overwrite flag', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create([
            'content' => '<h1>Updated HTML</h1><p>New content</p>',
            'markdown_content' => '# Old Markdown',
        ]);

        $this->artisan('notes:convert-to-markdown --force --overwrite')
            ->expectsOutputToContain('Overwrite mode enabled')
            ->expectsOutputToContain('Successfully converted 1 note(s)')
            ->assertSuccessful();

        $note->refresh();
        expect($note->markdown_content)->toContain('Updated HTML');
        expect($note->markdown_content)->not->toContain('Old Markdown');
    });

    it('reconverts all notes with --overwrite flag', function () {
        /** @var TestCase $this */
        // Create notes with existing markdown_content
        Note::factory()->count(2)->create([
            'content' => '<p>HTML content</p>',
            'markdown_content' => '**Old markdown**',
        ]);

        // Create note without markdown_content
        Note::factory()->create([
            'content' => '<p>New HTML</p>',
            'markdown_content' => null,
        ]);

        $this->artisan('notes:convert-to-markdown --force --overwrite')
            ->expectsOutput('Found 3 note(s) to convert')
            ->expectsOutputToContain('Overwrite mode enabled')
            ->assertSuccessful();

        expect(Note::whereNotNull('markdown_content')->count())->toBe(3);
    });
});
