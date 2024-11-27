<?php

namespace App\Console\Commands;

use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class ImportMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:import
        {file : The CSV file to import}
        {--force : Actually do the import. By default, it is a dry run}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Media and Creators from CSV';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->option('force');

        $csv = Reader::createFromPath($this->argument('file'));
        $csv->setHeaderOffset(0);
        $header = $csv->getHeader();
        $expectedHeader = ['title', 'year', 'creator', 'note'];

        if ($header !== $expectedHeader) {
            $this->fail('Invalid CSV file. Header must be: '.implode(', ', $expectedHeader));
        }

        $this->info($force ? 'Starting import' : 'Starting dry run');

        $records = collect($csv->getRecords($expectedHeader));

        // TODO: Constants / enum for MediaType names
        $bookMediaType = MediaType::where('name', 'book')->first();

        $foundCreators = 0;
        $newCreators = 0;
        $foundMedia = 0;
        $newMedia = 0;

        DB::beginTransaction();

        $this->withProgressBar($records, function ($row) use ($bookMediaType, &$foundCreators, &$newCreators, &$foundMedia, &$newMedia) {
            $creator = Creator::firstOrNew(['name' => $row['creator']]);
            if ($creator->exists) {
                $foundCreators++;
            } else {
                $newCreators++;
            }

            // if ($this->option('force')) {
            $creator->save();
            // }

            $media = Media::firstOrNew(['title' => $row['title']]);
            $media->fill([
                'year' => $row['year'] ?: null,
                'note' => $row['note'] ?: null,
                'media_type_id' => $bookMediaType->id,
            ]);
            $media->creator()->associate($creator);
            if ($media->exists) {
                $foundMedia++;
            } else {
                $newMedia++;
            }
            // if ($this->option('force')) {
            $media->save();
            // }
        });

        $this->info('✔︎');
        $this->info($force ? 'Import results' : 'Dry run results');

        $this->table(
            headers: ['Type', 'Found', 'Imported'],
            rows: [
                ['Creators', $foundCreators, $newCreators],
                ['Media', $foundMedia, $newMedia],
            ]

        );

        if ($force) {
            DB::commit();
        } else {
            DB::rollback();
        }
    }
}
