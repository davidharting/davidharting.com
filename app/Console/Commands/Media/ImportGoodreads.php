<?php

namespace App\Console\Commands\Media;

use App\Actions\GoodreadsImport\Importer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
     * Execute the console command.
     */
    public function handle()
    {
        $shouldSave = $this->option('force');
        $path = $this->argument('file');

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

        $importer = new Importer($path);
        $rowsProcessed = 0;
        $report = $importer->import(function () use (&$rowsProcessed) {
            $this->output->write('.');
            $rowsProcessed++;
        });

        $this->output->newLine();
        $this->info('Processed '.$rowsProcessed.' rows.');
        $this->info('Created:');
        $this->table(array_keys($report), [array_values($report)]);

        if ($shouldSave) {
            DB::commit();
            $this->info('Import completed. Committed transaction.');
        } else {
            $this->info('Dry run completed. Rolling back transaction.');
            DB::rollBack();
        }
    }
}
