<?php

namespace App\Mcp\Tools;

use App\Models\Note;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
#[Description(<<<'TEXT'
    Read one published note (blog post) from davidharting.com in full, as
    markdown. Identify the note by its slug, as returned by the list-notes or
    search-notes tools. The response includes the note's title, lead (subtitle),
    publication date, canonical URL, and complete markdown content.
    TEXT)]
class GetNote extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'slug' => ['required', 'string'],
        ]);

        $note = Note::query()
            ->where('slug', $validated['slug'])
            ->where('visible', true)
            ->first();

        if ($note === null) {
            return Response::error('Note not found.');
        }

        return Response::text($this->toMarkdown($note));
    }

    private function toMarkdown(Note $note): string
    {
        $lines = [];

        if ($note->title) {
            $lines[] = "# {$note->title}";
        }

        if ($note->lead) {
            $lines[] = "*{$note->lead}*";
        }

        $lines[] = 'Published: '.$note->published_at?->toDateString();
        $lines[] = 'URL: '.route('notes.show', $note->slug);

        if ($note->markdown_content) {
            $lines[] = '---';
            $lines[] = $note->markdown_content;
        }

        return implode("\n\n", $lines);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()
                ->required()
                ->description('The slug of the note to fetch, as returned by the list-notes or search-notes tools.'),
        ];
    }
}
