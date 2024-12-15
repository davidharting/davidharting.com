<?php

namespace App\Console\Commands\Media;

use App\Actions\SofaImport\Importer;
use Illuminate\Console\Command;

class ImportSofa extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:import-sofa
            {--force : Actually do the import. By default, it is a dry run}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the Sofa export CSV file into the media tracker';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shouldSave = $this->option('force');

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

        $importer = new Importer;
        $report = $importer->import();
    }
}
