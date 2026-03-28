<?php

namespace App\Telegram\Conversations;

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\MediaWritingAgentTool;
use App\Ai\Tools\RequestConfirmation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Exceptions\AiException;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class TrackConversation extends Conversation
{
    protected ?string $aiConversationId = null;

    // Provides a ->id property for RemembersConversations. We use null so the DB stores
    // NULL for user_id — this avoids conflating Telegram user IDs with system user IDs.
    private function conversationUser(): object
    {
        return new class
        {
            public ?int $id = null;
        };
    }

    public function start(Nutgram $bot, string $text): void
    {
        // Pre-create the conversation so runAgentTurn() can always call continue() uniformly,
        // regardless of whether this is the first turn or a follow-up. This also avoids
        // spending tokens on a Haiku-generated title that is never shown to the user —
        // we use the first message text as the title instead.
        $this->aiConversationId = app(ConversationStore::class)
            ->storeConversation(null, Str::limit($text, 100, preserveWords: true));

        $this->runAgentTurn($bot, $text);
    }

    public function converse(Nutgram $bot): void
    {
        if ($bot->isCallbackQuery() && $bot->callbackQuery()?->data === 'end') {
            $bot->answerCallbackQuery();
            $bot->sendMessage('Conversation ended.');
            $this->end();

            return;
        }

        $this->runAgentTurn($bot, $bot->message()->text ?? '');
    }

    public function awaitConfirmation(Nutgram $bot): void
    {
        if (! $bot->isCallbackQuery()) {
            $bot->sendMessage('Please tap Confirm or Cancel.');
            $this->next('awaitConfirmation');

            return;
        }

        // We send the outcome as a chat message (rather than answerCallbackQuery text)
        // so that it's persistent in the conversation and easily assertable in tests.
        $bot->answerCallbackQuery();

        if ($bot->callbackQuery()?->data === 'confirm') {
            $writingTool = new MediaWritingAgentTool;
            $agent = (new MediaTrackingAgent(writingTool: $writingTool))
                ->continue($this->aiConversationId, $this->conversationUser());
            $response = $agent->prompt('The user confirmed. Execute the plan.');
            $bot->sendMessage($response->text, parse_mode: ParseMode::HTML);
        } else {
            $bot->sendMessage('Cancelled. Nothing was changed.');
        }

        $this->end();
    }

    private function runAgentTurn(Nutgram $bot, string $userText): void
    {
        try {
            // We resolve RequestConfirmation via the container (rather than new-ing it up)
            // so tests can swap in a pre-triggered instance via app()->bind(). We pass it
            // through the constructor so we can read its state after the agent turn ends.
            $confirmationTool = app(RequestConfirmation::class);

            // continue() requires a non-nullable object for the second arg even though the
            // middleware accesses it with ?->id. conversationUser() provides a null-id object
            // so the DB stores NULL for user_id (Telegram IDs ≠ system user IDs).
            $agent = (new MediaTrackingAgent($confirmationTool))
                ->continue($this->aiConversationId, $this->conversationUser());

            $response = $agent->prompt($userText);

            if ($confirmationTool->wasRequested()) {
                $bot->sendMessage(
                    $response->text,
                    reply_markup: InlineKeyboardMarkup::make()->addRow(
                        InlineKeyboardButton::make('✓ Confirm', callback_data: 'confirm'),
                        InlineKeyboardButton::make('✗ Cancel', callback_data: 'cancel'),
                    ),
                    parse_mode: ParseMode::HTML,
                );
                $this->next('awaitConfirmation');
            } else {
                $bot->sendMessage(
                    $response->text,
                    reply_markup: InlineKeyboardMarkup::make()->addRow(
                        InlineKeyboardButton::make('End', callback_data: 'end'),
                    ),
                    parse_mode: ParseMode::HTML,
                );
                $this->next('converse');
            }
        } catch (AiException $e) {
            // TODO: Retry with exponential backoff before giving up (consider adding to the agent itself).
            Log::error('MediaTrackingAgent failed', ['exception' => $e]);
            $bot->sendMessage("Error: {$e->getMessage()}");
            $this->end();
        }
    }
}
