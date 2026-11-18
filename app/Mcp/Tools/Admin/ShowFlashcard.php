<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Resources\FlashcardApp;
use App\Models\Flashcard;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Put an interactive flashcard in the chat, registered only on AdminServer.
 * The card itself fetches the word, flips to the definition and records
 * David's grade, so the model never sees the answer and never grades.
 *
 * Read-only and idempotent as a tool: it only counts what is due. The
 * grades are written by grade-flashcard, which the card calls.
 */
#[IsReadOnly]
#[IsIdempotent]
#[RendersApp(resource: FlashcardApp::class)]
#[Description(<<<'TEXT'
    Show David an interactive flashcard in the chat, as an alternative to
    quizzing him in text with next-flashcard. The card shows his next due
    word; he taps it to flip to the definition, then taps Got it or Missed
    it, and the card records that itself. It then offers him the next word.

    Use it when David asks to practise with a card. Don't call
    reveal-flashcard or grade-flashcard for a word shown on the card, and
    don't say its definition: he grades himself on the card.

    If David says he can't see a card, his app doesn't support interactive
    cards: practise in text with next-flashcard instead.
    TEXT)]
class ShowFlashcard extends Tool
{
    /**
     * Whether the caller is entitled to the admin surface at all. Failing this
     * hides the tool from tools/list and makes tools/call answer "Tool not
     * found": both resolve through the same filtered collection.
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
        if ($request->user()?->cannot('viewAny', Flashcard::class) ?? true) {
            return Response::error('You are not authorized to practise David\'s flashcards.');
        }

        $dueCount = Flashcard::query()->forWords()->dueNow()->count();

        return Response::text(sprintf(
            'Showing David a flashcard to flip and grade himself. %d %s due now.',
            $dueCount,
            $dueCount === 1 ? 'word is' : 'words are',
        ));
    }
}
