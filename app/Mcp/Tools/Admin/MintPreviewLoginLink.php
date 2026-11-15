<?php

namespace App\Mcp\Tools\Admin;

use App\Support\PreviewLoginLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use RuntimeException;

/**
 * Mints a signed one-tap login link for a PR preview, registered only on
 * AdminServer. It stores nothing, but it isn't marked read-only: the link it
 * hands out logs into a preview, so clients shouldn't auto-approve it.
 */
#[IsDestructive(false)]
#[Description(<<<'TEXT'
    Make a link that logs David into a davidharting.com PR preview as a
    seeded test user and opens a page there, in one tap. Use it whenever you
    ask David to look at a preview, and send him the link instead of the bare
    preview URL.

    pull_request is the PR number whose preview to open. as is the seeded
    @example.com user to log in as, defaulting to admin@example.com; others
    are frodo@example.com (non-admin), david@example.com (admin) and
    katie@example.com (non-admin), the two memories participants. to is the
    path to open, starting with a single /, e.g. /admin or /media?type=book;
    leave it out to land on the user's usual landing page.

    The link only works on that PR's preview and expires after 24 hours.
    TEXT)]
class MintPreviewLoginLink extends Tool
{
    /**
     * Whether the caller is entitled to the admin surface at all. Failing this
     * hides the tool from tools/list and makes tools/call answer "Tool not
     * found".
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
        if ($request->user()?->cannot('administrate') ?? true) {
            return Response::error('You are not authorized to mint preview login links.');
        }

        $validated = $request->validate([
            'pull_request' => ['required', 'integer', 'min:1'],
            'as' => ['sometimes', 'string', 'email'],
            'to' => ['sometimes', 'string'],
        ]);

        try {
            $link = PreviewLoginLink::mint(
                (int) $validated['pull_request'],
                $validated['as'] ?? PreviewLoginLink::DEFAULT_EMAIL,
                $validated['to'] ?? '',
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured([
            'url' => $link->url,
            'expires_at' => $link->expiresAt->toIso8601String(),
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
            'pull_request' => $schema->integer()
                ->required()
                ->description('The PR number whose preview to open.'),
            'as' => $schema->string()
                ->description('The seeded @example.com user to log in as. Defaults to admin@example.com.'),
            'to' => $schema->string()
                ->description('The path to open, starting with a single /. Defaults to the user\'s landing page.'),
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
            'url' => $schema->string()->description('The one-tap login link to send David.'),
            'expires_at' => $schema->string()->description('When the link stops working, in ISO 8601.'),
        ];
    }
}
