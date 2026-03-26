<?php

namespace App\Telegram\Conversations;

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\RequestConfirmation;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class TrackConversation extends Conversation
{
    /** @var Message[] */
    protected array $messageHistory = [];

    public function start(Nutgram $bot, string $text): void
    {
        $this->runAgentTurn($bot, $text);
    }

    public function converse(Nutgram $bot): void
    {
        $this->runAgentTurn($bot, $bot->message()->text ?? '');
    }

    public function awaitConfirmation(Nutgram $bot): void
    {
        if (! $bot->isCallbackQuery()) {
            $bot->sendMessage('Please tap Confirm or Cancel.');
            $this->next('awaitConfirmation');

            return;
        }

        $bot->answerCallbackQuery();

        if ($bot->callbackQuery()?->data === 'confirm') {
            $bot->sendMessage('✓ Done. (DB writes coming in next milestone)');
        } else {
            $bot->sendMessage('Cancelled. Nothing was changed.');
        }

        $this->end();
    }

    private function runAgentTurn(Nutgram $bot, string $userText): void
    {
        try {
            $confirmationTool = app(RequestConfirmation::class);
            $agent = new MediaTrackingAgent($this->messageHistory, $confirmationTool);
            $response = $agent->prompt($userText);

            $this->messageHistory[] = new Message(MessageRole::User, $userText);
            $this->messageHistory[] = new Message(MessageRole::Assistant, $response->text);

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
                $bot->sendMessage($response->text, parse_mode: ParseMode::HTML);
                $this->next('converse');
            }
        } catch (AiException $e) {
            Log::error('MediaTrackingAgent failed', ['exception' => $e]);
            $bot->sendMessage("Error: {$e->getMessage()}");
            $this->end();
        }
    }
}
