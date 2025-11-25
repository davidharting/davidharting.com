<?php

namespace App\Console\Commands;

use App\Models\Note;
use Illuminate\Console\Command;
use League\HTMLToMarkdown\HtmlConverter;

class ConvertNotesToMarkdown extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notes:convert-to-markdown {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert HTML note content to Markdown';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }

        // Get all notes that have content but no markdown_content yet
        $notes = Note::whereNotNull('content')
            ->whereNull('markdown_content')
            ->get();

        if ($notes->isEmpty()) {
            $this->info('No notes found to convert. All notes are already using Markdown!');

            return Command::SUCCESS;
        }

        $this->info("Found {$notes->count()} note(s) to convert");

        $converter = new HtmlConverter;
        $progressBar = $this->output->createProgressBar($notes->count());
        $progressBar->start();

        $converted = 0;
        $failed = 0;

        foreach ($notes as $note) {
            try {
                $markdown = $converter->convert($note->content);

                if (! $dryRun) {
                    $note->update(['markdown_content' => $markdown]);
                }

                $converted++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to convert note #{$note->id} ({$note->slug}): {$e->getMessage()}");
                $failed++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("Dry run complete! Would convert {$converted} note(s)");
        } else {
            $this->info("Successfully converted {$converted} note(s) to Markdown");
        }

        if ($failed > 0) {
            $this->warn("Failed to convert {$failed} note(s)");
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
