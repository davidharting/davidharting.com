<?php

namespace App\Console\Commands\Words;

use App\Models\Flashcard;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Message\LinkPreviewOptions;

/**
 * Messages David on Telegram with how many words are due, and a link to
 * practise them. Sends nothing when he's caught up, so a message always means
 * there's something to do.
 *
 * Runs once a day from a Render cron job, as his afternoon brain break.
 */
class SendPracticeNudge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'words:send-practice-nudge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Message David on Telegram when words are due for practice';

    /**
     * Execute the console command.
     */
    public function handle(Nutgram $bot): int
    {
        $davidUserId = config('nutgram.owner_user_id');

        if (blank($davidUserId)) {
            $this->components->error('nutgram.owner_user_id (TELEGRAM_DAVID_ID) is not set, so there is no one to nudge.');

            return self::FAILURE;
        }

        $dueCount = Flashcard::query()->dueNow()->count();

        if ($dueCount === 0) {
            $this->components->info('No words are due, so no nudge.');

            return self::SUCCESS;
        }

        $dueWords = str('word')->plural($dueCount, prependCount: true);

        $bot->sendMessage(
            text: "{$dueWords} due\n".route('words.practice'),
            chat_id: $davidUserId,
            link_preview_options: LinkPreviewOptions::make(is_disabled: true),
        );

        $this->components->info("Nudged David about {$dueWords}.");

        return self::SUCCESS;
    }
}
