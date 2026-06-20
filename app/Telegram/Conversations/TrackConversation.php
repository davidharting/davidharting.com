<?php

namespace App\Telegram\Conversations;

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\MediaWritingAgentTool;
use App\Ai\Tools\RequestConfirmation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Throwable;

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
            $this->runAgentTurn($bot, $bot->message()->text ?? '');

            return;
        }

        // We send the outcome as a chat message (rather than answerCallbackQuery text)
        // so that it's persistent in the conversation and easily assertable in tests.
        $bot->answerCallbackQuery();
        $bot->editMessageReplyMarkup();

        if ($bot->callbackQuery()?->data === 'confirm') {
            $bot->sendMessage('On it! I\'ll report back when it\'s done.');

            try {
                $writingTool = new MediaWritingAgentTool;
                $agent = (new MediaTrackingAgent(writingTool: $writingTool))
                    ->continue($this->aiConversationId, $this->conversationUser());
                $response = $this->promptWithRetry($agent, 'The user confirmed. Execute the plan.');
                $bot->sendMessage(
                    $this->textOr($response->text, '✓ Done.'),
                    parse_mode: ParseMode::HTML,
                );
            } catch (AiException $e) {
                Log::error('MediaTrackingAgent execution failed', ['exception' => $e]);
                $bot->sendMessage("Error: {$e->getMessage()}");
            } catch (Throwable $e) {
                Log::error('TrackConversation execution failed unexpectedly', ['exception' => $e]);
                $bot->sendMessage('Sorry, something went wrong while saving that. Please try again.');
            }
        } else {
            $bot->sendMessage('Conversation ended.');
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

            $response = $this->promptWithRetry($agent, $userText);

            if ($confirmationTool->wasRequested()) {
                // The agent is instructed to write the confirmation summary as its
                // response text when it calls RequestConfirmation, but it sometimes
                // emits only the tool call with no text. Fall back so we never send
                // Telegram an empty message (which fails with "message text is empty"
                // and 500s the webhook, triggering a retry storm).
                $bot->sendMessage(
                    $this->textOr($response->text, 'Ready to log this. Confirm?'),
                    reply_markup: InlineKeyboardMarkup::make()->addRow(
                        InlineKeyboardButton::make('✓ Confirm', callback_data: 'confirm'),
                        InlineKeyboardButton::make('End', callback_data: 'end'),
                    ),
                    parse_mode: ParseMode::HTML,
                );
                $this->next('awaitConfirmation');
            } else {
                $bot->sendMessage(
                    $this->textOr($response->text, "Sorry, I didn't catch that. Could you say it another way?"),
                    reply_markup: InlineKeyboardMarkup::make()->addRow(
                        InlineKeyboardButton::make('End', callback_data: 'end'),
                    ),
                    parse_mode: ParseMode::HTML,
                );
                $this->next('converse');
            }
        } catch (AiException $e) {
            // Provider failures that survived promptWithRetry (e.g. insufficient
            // credits, or exhausted transient retries). Surface the reason so David
            // knows what happened, and end the turn with a 200 so Telegram does not
            // retry the whole expensive agent run.
            Log::error('MediaTrackingAgent failed', ['exception' => $e]);
            $bot->sendMessage("Error: {$e->getMessage()}");
            $this->end();
        } catch (Throwable $e) {
            // Any other unexpected failure (a bug, a Telegram API rejection, etc.).
            // Reply so David isn't left hanging and return normally (200) to stop
            // Telegram from retrying. Genuine infrastructure failures happen outside
            // this handler and still 500, so Telegram can redeliver those.
            Log::error('TrackConversation turn failed unexpectedly', ['exception' => $e]);
            $bot->sendMessage('Sorry, something went wrong on my end. Please try again.');
            $this->end();
        }
    }

    /**
     * Run an agent prompt, retrying transient provider failures (overloaded or
     * rate limited) a few times with exponential backoff before giving up.
     * Deterministic failures (e.g. insufficient credits) are not retried.
     */
    private function promptWithRetry(MediaTrackingAgent $agent, string $prompt, int $maxAttempts = 3): mixed
    {
        $attempt = 1;

        while (true) {
            try {
                return $agent->prompt($prompt);
            } catch (ProviderOverloadedException|RateLimitedException $e) {
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                Log::warning('MediaTrackingAgent transient failure; retrying', [
                    'attempt' => $attempt,
                    'exception' => $e->getMessage(),
                ]);

                // Backoff of 1s then 2s, kept short so the synchronous webhook stays
                // well within Telegram's timeout on top of the agent's own latency.
                Sleep::for(2 ** ($attempt - 1))->seconds();

                $attempt++;
            }
        }
    }

    /**
     * Return the given text, or a fallback when it is empty, so we never hand
     * Telegram a blank message (which it rejects with "message text is empty").
     */
    private function textOr(?string $text, string $fallback): string
    {
        return filled($text) ? $text : $fallback;
    }
}
