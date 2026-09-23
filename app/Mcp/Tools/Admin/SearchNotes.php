<?php

namespace App\Mcp\Tools\Admin;

use App\Models\Note;
use App\Queries\SearchNotesQuery;
use App\Support\Excerpt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The admin counterpart of App\Mcp\Tools\SearchNotes, registered only on
 * AdminServer.
 *
 * Do not collapse the two into one class with a flag. A tool's name,
 * description and schemas come from class attributes and never see the request,
 * so a single class would have to describe both surfaces at once — and the
 * description is how the model learns whether drafts can match.
 */
#[IsReadOnly]
#[IsIdempotent]
#[Description(<<<'TEXT'
    Search David's notes (blog posts) on davidharting.com. The query is matched
    case-insensitively against each note's title, lead (subtitle), and full
    markdown content. Returns matching notes, most recently published first,
    each with its status and a short snippet of the content surrounding the
    match, so you can judge relevance. Pass a result's slug to the get-note tool
    to read the whole note.

    Only published notes are searched by default. Set include_drafts to true to
    also search unpublished drafts, which are not on the public website — a
    draft is work in progress, so never quote or summarise one as something
    David has published. Every result carries a status of "published" or
    "draft"; check it before relying on a note.

    Results are paginated — use the page and per_page arguments to walk through
    them.
    TEXT)]
class SearchNotes extends Tool
{
    private const MIN_QUERY_LENGTH = 4;

    private const DEFAULT_PER_PAGE = 250;

    private const MAX_PER_PAGE = 250;

    /**
     * Whether the caller is entitled to the admin surface at all. Failing this
     * hides the tool from tools/list and makes tools/call answer "Tool not
     * found": both resolve through the same filtered collection.
     *
     * handle() asks the narrower question of whether the caller may see these
     * particular rows. Neither check substitutes for the other.
     */
    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->can('administrate') ?? false;
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if ($user === null || $user->cannot('viewAny', Note::class)) {
            return Response::error('You are not authorized to read David\'s notes.');
        }

        $validated = $this->validatedArguments($request);
        $query = $validated['query'];

        $paginator = (new SearchNotesQuery(
            query: $query,
            includeDrafts: $validated['include_drafts'] ?? false,
        ))->paginate(
            perPage: $validated['per_page'] ?? self::DEFAULT_PER_PAGE,
            page: $validated['page'] ?? 1,
        );

        return Response::structured([
            'notes' => collect($paginator->items())->map(fn (Note $note): array => [
                'slug' => $note->slug,
                'title' => $note->title,
                'lead' => $note->lead,
                'published_at' => $note->published_at?->toIso8601String(),
                'url' => route('notes.show', $note->slug),
                'status' => $note->visible ? 'published' : 'draft',
                'snippet' => Excerpt::around($note->markdown_content, $query),
            ])->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ]);
    }

    /**
     * An invalid argument throws a ValidationException, which laravel/mcp turns
     * into an error result naming the failing field — the tool never sees it.
     *
     * @return array{
     *     query: string,
     *     include_drafts?: bool,
     *     page?: int,
     *     per_page?: int,
     * }
     */
    private function validatedArguments(Request $request): array
    {
        return $request->validate([
            'query' => ['required', 'string', 'min:'.self::MIN_QUERY_LENGTH],
            'include_drafts' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->required()
                ->min(self::MIN_QUERY_LENGTH)
                ->description(sprintf(
                    'The text to search for, at least %d characters long. Matched case-insensitively against note titles, leads, and markdown content.',
                    self::MIN_QUERY_LENGTH,
                )),
            'include_drafts' => $schema->boolean()
                ->description('Whether to also search unpublished drafts. Defaults to false, which searches only published notes.'),
            'page' => $schema->integer()
                ->min(1)
                ->description('The page of results to return. Defaults to 1.'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(self::MAX_PER_PAGE)
                ->description(sprintf(
                    'How many notes to return per page. Defaults to %d, maximum %d.',
                    self::DEFAULT_PER_PAGE,
                    self::MAX_PER_PAGE,
                )),
        ];
    }

    /**
     * Get the tool's output schema.
     *
     * @return array<string, JsonSchema>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'notes' => $schema->array()
                ->items($schema->object([
                    'slug' => $schema->string()->description('The unique slug identifying the note.'),
                    'title' => $schema->string()->nullable()->description('The note title. Short notes may have no title.'),
                    'lead' => $schema->string()->nullable()->description('The lead (subtitle) of the note.'),
                    'published_at' => $schema->string()->nullable()->description('The publication timestamp in ISO 8601 format.'),
                    'url' => $schema->string()->description('The canonical URL of the note on davidharting.com. A draft 404s there until it is published.'),
                    'status' => $schema->string()->enum(['published', 'draft'])->description('Whether the note is published on the website or is still an unpublished draft.'),
                    'snippet' => $schema->string()->nullable()->description('The content surrounding the first match in the note body. Null when the match was only in the title or lead.'),
                ]))
                ->description('The matching notes on this page, most recently published first.'),
            'total' => $schema->integer()->description('The total number of matching notes across all pages.'),
            'page' => $schema->integer()->description('The current page number.'),
            'per_page' => $schema->integer()->description('The number of notes per page.'),
            'has_more_pages' => $schema->boolean()->description('Whether more pages of matching notes are available.'),
        ];
    }
}
