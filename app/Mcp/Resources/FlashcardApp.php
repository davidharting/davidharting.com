<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\AppMeta;
use Laravel\Mcp\Server\Attributes\Description;

/**
 * An interactive flashcard rendered inside the chat by MCP Apps hosts,
 * registered only on AdminServer. The HTML holds no data: once the host
 * renders it, it calls next-flashcard, reveal-flashcard and
 * grade-flashcard itself, so every read and grade goes through the same
 * tools and policies as a text practice session.
 */
#[Description('A flashcard from David\'s vocabulary list that he can flip and grade himself.')]
#[AppMeta(prefersBorder: false)]
class FlashcardApp extends AppResource
{
    /**
     * Whether the caller is entitled to the admin surface at all.
     */
    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->can('administrate') ?? false;
    }

    /**
     * Handle the app resource request.
     */
    public function handle(Request $request): Response
    {
        return Response::view('mcp.flashcard-app', [
            'title' => $this->title(),
        ]);
    }
}
