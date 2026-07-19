<?php

namespace App\Ai\Tools;

use App\Enum\MediaSort;
use App\Enum\MediaTrackingStatus;
use App\Enum\MediaTypeName;
use App\Queries\Media\SearchMediaQuery;
use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The Telegram agent's window into the media library.
 *
 * Its public MCP twin, App\Mcp\Tools\QueryMedia, could be handed to the agent
 * directly — laravel/ai auto-wraps Laravel\Mcp\Server\Tool instances — and the
 * two already share their core through SearchMediaQuery. This native tool
 * exists because the agent needs a different surface than anonymous MCP
 * clients: creator_id in results (MediaWritingAgent passes it to CreateMedia),
 * case-insensitive enum arguments, and invalid arguments answered with error
 * strings the model can correct — a wrapped MCP tool's validate() throws
 * instead, aborting the whole agent run.
 */
class SearchMedia implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return <<<'TEXT'
            Search and browse media items in the library. All filters are optional
            and combine with AND: title and creator (case-insensitive partial
            match), media_type (album, book, movie, tv show, video game), status
            (backlog, started, finished, abandoned), year (the work's release
            year), started_year and finished_year (the calendar year David started
            or finished the item). Results can be sorted and are paginated.
            Returns matching records including the title, year, media type,
            creator, current tracking status, and the dates each status was
            reached. Use it to check whether an item is already in the library, or
            to answer questions about the library ("what did David finish in
            2024?" -> finished_year=2024; "what is on the backlog?" ->
            status=backlog).
            TEXT;
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $mediaType = $this->enumFromRequest($request, 'media_type', MediaTypeName::class, $error);
        if ($error !== null) {
            return $error;
        }

        $status = $this->enumFromRequest($request, 'status', MediaTrackingStatus::class, $error);
        if ($error !== null) {
            return $error;
        }

        $sort = $this->enumFromRequest($request, 'sort', MediaSort::class, $error);
        if ($error !== null) {
            return $error;
        }

        $query = SearchMediaQuery::fromArray([
            'title' => ((string) $request->string('title')) ?: null,
            'creator' => ((string) $request->string('creator')) ?: null,
            'media_type' => $mediaType,
            'status' => $status,
            'year' => $request->integer('year') ?: null,
            'started_year' => $request->integer('started_year') ?: null,
            'finished_year' => $request->integer('finished_year') ?: null,
            'sort' => $sort,
        ]);

        $paginator = $query->paginate(
            perPage: min($request->integer('limit') ?: SearchMediaQuery::DEFAULT_LIMIT, SearchMediaQuery::MAX_LIMIT),
            page: max($request->integer('page') ?: 1, 1),
        );

        return json_encode([
            'found' => $paginator->total() > 0,
            'results' => collect($paginator->items())->toArray(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Parse and normalize an optional backed-enum argument, setting $error to
     * a JSON error payload when the provided value is not a valid case.
     *
     * @param  class-string<BackedEnum>  $enum
     *
     * @param-out string|null $error
     */
    private function enumFromRequest(Request $request, string $key, string $enum, ?string &$error): ?string
    {
        $error = null;
        $raw = ((string) $request->string($key)) ?: null;

        if ($raw === null) {
            return null;
        }

        $value = $enum::tryFrom(strtolower($raw));

        if ($value === null) {
            $valid = implode(', ', array_column($enum::cases(), 'value'));
            $error = json_encode(
                ['error' => "Invalid {$key} \"{$raw}\". Must be one of: {$valid}."],
                JSON_THROW_ON_ERROR,
            );

            return null;
        }

        return $value->value;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return SearchMediaQuery::inputSchema($schema);
    }
}
