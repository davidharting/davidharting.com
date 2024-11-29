<?php

namespace App\Console\Commands\Media;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\Csv\Reader;

use function Laravel\Prompts\progress;

class ImportGoodreads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:import-goodreads
            {file : The Goodreads export CSV file to import}
            {--force : Actually do the import. By default, it is a dry run}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import a Goodreads export CSV file into the media tracker';

    /**
     * The header format of Goodread CSV exports
     */
    private $header = ['Title', 'Author', 'Year', 'Rating', 'Status', 'Read At', 'Added At', 'Read Count', 'ISBN', 'ISBN13', 'My Review', 'Spoiler', 'Private Notes', 'Read Count', 'Recommended For', 'Recommended By', 'Owned', 'Original Purchase Date', 'Original Purchase Location', 'Condition', 'Condition Description', 'BCID', 'Bookshelves', 'Date Added', 'Date Updated', 'Read Date', 'Started Date', 'Date Read', 'Date Started'];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shouldSave = $this->option('force');
        $csv = $this->getCsv($this->argument('file'));

        if ($shouldSave) {
            $confirmation = $this->confirm('You used the --force option to actually import data. Are you sure?');
            if (! $confirmation) {
                $this->info('Aborting import');

                return;
            } else {
                $this->info('Starting import');
            }
        } else {
            $this->info('Starting dry run');
        }

        DB::beginTransaction();
        
        $progress = ;

        if ($shouldSave) {
            DB::commit();
        } else {
            DB::rollBack();
        }
    }

    private function getCsv(string $path): Reader
    {
        if (! file_exists($path)) {
            $msg = Str::of('File ')->append($path)->append(' not found');
            $this->fail($msg);
        }

        $csv = Reader::createFromPath($path);
        $csv->setHeaderOffset(0);
        $header = $csv->getHeader();

        if ($header !== $this->header) {
            $msg = Str::of('Invalid CSV file. Header must be: ')->append(implode(', ', $this->header));
            $this->fail($msg);
        }

        return $csv;
    }
}
