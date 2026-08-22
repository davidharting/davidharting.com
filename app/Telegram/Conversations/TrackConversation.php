<?php

namespace App\Telegram\Conversations;

use App\Jobs\RunTrackAgentTurn;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatAction;

/**
 * Routes /track messages to the agent and back.
 *
 * Every step here runs inside the Telegram webhook request, so none of them may block:
 * the agent turn itself is handed to RunTrackAgentTurn on the queue, and the job sends
 * the reply and moves us on to the next step when it finishes.
 */
class TrackConversation extends Conversation
{
    protected ?string $aiConversationId = null;

    /**
     * Identifies the agent turn currently running on the queue. RunTrackAgentTurn
     * carries a copy and discards its result unless this still matches, so a turn that
     * has been superseded — by /end, or by a newer /track — cannot post a stale reply
     * over a fresher one.
     */
    protected ?string $turnId = null;

    public function start(Nutgram $bot, string $text): void
    {
        // Pre-create the conversation so every turn can call continue() uniformly,
        // regardless of whether this is the first turn or a follow-up. This also avoids
        // spending tokens on a Haiku-generated title that is never shown to the user —
        // we use the first message text as the title instead.
        $this->aiConversationId = app(ConversationStore::class)
            ->storeConversation(null, Str::limit($text, 100, preserveWords: true));

        $this->dispatchTurn($bot, $text);
    }

    public function converse(Nutgram $bot): void
    {
        if ($this->endedByButton($bot)) {
            return;
        }

        $this->dispatchTurn($bot, $bot->message()->text ?? '');
    }

    /**
     * Holds the conversation while an agent turn runs on the queue.
     *
     * Nutgram only rewrites the cached conversation when next() is called, so returning
     * without stepping parks us here until RunTrackAgentTurn advances us. That is what
     * keeps a second message from kicking off a concurrent agent run against the same
     * conversation history.
     */
    public function working(Nutgram $bot): void
    {
        if ($this->endedByButton($bot)) {
            return;
        }

        if ($bot->isCallbackQuery()) {
            $bot->answerCallbackQuery(text: 'Still working on your last message.');

            return;
        }

        $bot->sendMessage('Still working on your last message — one moment.');
    }

    public function awaitConfirmation(Nutgram $bot): void
    {
        if (! $bot->isCallbackQuery()) {
            $this->dispatchTurn($bot, $bot->message()->text ?? '');

            return;
        }

        // We send the outcome as a chat message (rather than answerCallbackQuery text)
        // so that it's persistent in the conversation and easily assertable in tests.
        $bot->answerCallbackQuery();
        $bot->editMessageReplyMarkup();

        if ($bot->callbackQuery()?->data === 'confirm') {
            $bot->sendMessage('On it! I\'ll report back when it\'s done.');
            $this->dispatchTurn($bot, 'The user confirmed. Execute the plan.', canWrite: true);

            return;
        }

        $bot->sendMessage('Conversation ended.');
        $this->end();
    }

    /**
     * Whether RunTrackAgentTurn still owns the turn it was dispatched for.
     */
    public function ownsTurn(string $turnId): bool
    {
        return $this->turnId !== null && hash_equals($this->turnId, $turnId);
    }

    /**
     * Park the conversation on $step, ready for the user's next message.
     *
     * Persisting is the caller's job. RunTrackAgentTurn runs without an Update, so it
     * has to call Nutgram::stepConversation() with explicit ids rather than going
     * through next(), which resolves those ids from the update being handled.
     */
    public function resumeAt(string $step): void
    {
        $this->step = $step;
        $this->turnId = null;
    }

    private function dispatchTurn(Nutgram $bot, string $userText, bool $canWrite = false): void
    {
        $this->turnId = (string) Str::uuid();

        // Park on working() and persist *before* dispatching. The queue worker is a
        // separate process that can pick the job up as soon as the row lands, and its
        // write to the conversation cache must not be clobbered by this one.
        $this->next('working');

        $bot->sendChatAction(ChatAction::TYPING);

        RunTrackAgentTurn::dispatch(
            chatId: $bot->chatId(),
            userId: $bot->userId(),
            threadId: $bot->messageThreadId(),
            aiConversationId: $this->aiConversationId,
            turnId: $this->turnId,
            userText: $userText,
            canWrite: $canWrite,
        );
    }

    private function endedByButton(Nutgram $bot): bool
    {
        if (! $bot->isCallbackQuery() || $bot->callbackQuery()?->data !== 'end') {
            return false;
        }

        $bot->answerCallbackQuery();
        $bot->sendMessage('Conversation ended.');
        $this->end();

        return true;
    }
}
