<?php

namespace App\Jobs;

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\RequestConfirmation;
use App\Telegram\Conversations\TrackConversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Throwable;

/**
 * Runs one turn of the /track agent off the web request.
 *
 * A turn can take minutes — MediaTrackingAgent is a multi-step Sonnet run with web
 * search behind it — which is far longer than Telegram will wait for a webhook
 * response before redelivering the update. TrackConversation therefore parks itself
 * on its `working` step and hands the turn to this job, which sends the reply and
 * advances the conversation once the agent is done.
 *
 * This job runs on the queue worker, a container with no inbound Update, so every
 * Telegram call it makes has to name its chat explicitly and it cannot use the
 * Conversation::next()/end() helpers (they resolve ids from the current update).
 */
class RunTrackAgentTurn implements ShouldQueue
{
    use Queueable;

    /**
     * Never retry a turn. When $canWrite is true the agent commits media records and
     * events, and a second attempt would apply them again. A failed turn reports the
     * error and ends the conversation instead — see failed().
     *
     * This property wins over the worker's --tries=3 in render.yaml.
     */
    public int $tries = 1;

    /**
     * Generous enough for a multi-step agent run with web search. The database queue
     * driver re-reserves any job still running after its connection's retry_after,
     * so config/queue.php keeps retry_after above this value.
     */
    public int $timeout = 300;

    public function __construct(
        public int $chatId,
        public int $userId,
        public ?int $threadId,
        public string $aiConversationId,
        public string $turnId,
        public string $userText,
        public bool $canWrite = false,
    ) {}

    public function handle(Nutgram $bot): void
    {
        // Resolved from the container (rather than new-ed up) so tests can swap in a
        // pre-triggered instance via app()->bind(). Passed through the constructor so
        // we can read its state after the agent turn ends. On a write turn we leave it
        // null: the plan is already confirmed, so there is nothing left to ask about.
        $confirmationTool = $this->canWrite ? null : app(RequestConfirmation::class);

        $agent = (new MediaTrackingAgent($confirmationTool, canWrite: $this->canWrite))
            ->continue($this->aiConversationId, $this->conversationUser());

        $response = $agent->prompt($this->userText);

        $conversation = $this->conversationAwaitingThisTurn($bot);

        if ($conversation === null) {
            Log::info('Discarding a superseded /track turn', [
                'chat_id' => $this->chatId,
                'turn_id' => $this->turnId,
            ]);

            return;
        }

        if ($confirmationTool?->wasRequested()) {
            $this->send($bot, $response->text, InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('✓ Confirm', callback_data: 'confirm'),
                InlineKeyboardButton::make('End', callback_data: 'end'),
            ));

            $this->resume($bot, $conversation, 'awaitConfirmation');

            return;
        }

        // A write turn is the last one: the plan has been executed, so report and close.
        if ($this->canWrite) {
            $this->send($bot, $response->text);
            $bot->endConversation($this->userId, $this->chatId, $this->threadId);

            return;
        }

        $this->send($bot, $response->text, InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make('End', callback_data: 'end'),
        ));

        $this->resume($bot, $conversation, 'converse');
    }

    /**
     * Report the failure to the user and close the conversation, so a turn that blew
     * up does not leave TrackConversation parked on `working` forever.
     */
    public function failed(?Throwable $e): void
    {
        Log::error('MediaTrackingAgent turn failed', ['exception' => $e]);

        $bot = app(Nutgram::class);

        if ($this->conversationAwaitingThisTurn($bot) === null) {
            return;
        }

        // Plain text, not HTML: an exception message is arbitrary text and a stray
        // "<" or "&" in it would make Telegram reject the message outright.
        $this->send($bot, "Error: {$e?->getMessage()}", parseMode: null);
        $bot->endConversation($this->userId, $this->chatId, $this->threadId);
    }

    /**
     * The conversation this turn was started for, or null if it is no longer waiting
     * on us — because /end purged it, or because a newer turn superseded ours while
     * the agent was thinking. Either way the result is dropped rather than posted as
     * a stale reply on top of a turn the user has already moved past.
     */
    private function conversationAwaitingThisTurn(Nutgram $bot): ?TrackConversation
    {
        $conversation = $bot->currentConversation($this->userId, $this->chatId, $this->threadId);

        if (! $conversation instanceof TrackConversation || ! $conversation->ownsTurn($this->turnId)) {
            return null;
        }

        return $conversation;
    }

    private function resume(Nutgram $bot, TrackConversation $conversation, string $step): void
    {
        $conversation->resumeAt($step);

        $bot->stepConversation($conversation, $this->userId, $this->chatId, $this->threadId);
    }

    private function send(
        Nutgram $bot,
        string $text,
        ?InlineKeyboardMarkup $replyMarkup = null,
        ?ParseMode $parseMode = ParseMode::HTML,
    ): void {
        $bot->sendMessage(
            $text,
            chat_id: $this->chatId,
            message_thread_id: $this->threadId,
            parse_mode: $parseMode,
            reply_markup: $replyMarkup,
        );
    }

    /**
     * Provides a ->id property for RemembersConversations. We use null so the DB stores
     * NULL for user_id — this avoids conflating Telegram user IDs with system user IDs.
     */
    private function conversationUser(): object
    {
        return new class
        {
            public ?int $id = null;
        };
    }
}
