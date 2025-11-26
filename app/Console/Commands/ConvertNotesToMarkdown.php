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
    protected $signature = 'notes:convert-to-markdown
                            {--force : Actually make changes (default is dry-run)}
                            {--overwrite : Reconvert ALL notes with content, even if markdown_content already exists}';

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
        $dryRun = ! $this->option('force');
        $overwrite = $this->option('overwrite');

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made (use --force to actually convert)');
        }

        if ($overwrite) {
            $this->info('Overwrite mode enabled - will reconvert all notes with content');
        }

        // Get notes to convert
        $query = Note::whereNotNull('content');

        if (! $overwrite) {
            $query->whereNull('markdown_content');
        }

        $notes = $query->get();

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
