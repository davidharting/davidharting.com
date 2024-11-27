<?php

namespace App\Console\Commands;

use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaType;
use Illuminate\Console\Command;
use League\Csv\Reader;

use function Laravel\Prompts\progress;

class ImportMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:import
        {file : The CSV file to import}
        {--force=false : Actually do the import. By default, it is a dry run}
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
        $csv = Reader::createFromPath($this->argument('file'));
        $csv->setHeaderOffset(0);
        $header = $csv->getHeader();
        $expectedHeader = ['title', 'year', 'creator', 'note'];

        if ($header !== $expectedHeader) {
            $this->fail('Invalid CSV file. Header must be: '.implode(', ', $expectedHeader));
        }

        $records = $csv->getRecords($expectedHeader);

        // TODO: Constants / enum for MediaType names
        $bookMediaType = MediaType::where('name', 'book')->first();

        $progress = progress(label: 'Importing records...', steps: $csv->count());

        $foundCreators = 0;
        $newCreators = 0;
        $foundMedia = 0;
        $newMedia = 0;

        foreach ($records as $offset => $row) {
            echo $offset;

            $creator = Creator::firstOrNew(['name' => $row['creator']]);
            if ($creator->exists) {
                $foundCreators++;
            } else {
                $newCreators++;
            }

            if ($this->option('force')) {
                $creator->save();
            }

            $media = Media::firstOrNew(['title' => $row['title']]);
            $media->fill(['year' => $row['year'], 'note' => $row['note'], 'media_type_id' => $bookMediaType->id]);
            $media->creator()->associate($creator);
            if ($media->exists) {
                $foundMedia++;
            } else {
                $newMedia++;
            }
            if ($this->option('force')) {
                $media->save();
            }

            $progress->advance();
        }

        $progress->finish();

        $this->info('Imported:');
        $this->info("Creators: $newCreators new, $foundCreators found");
        $this->info("Media: $newMedia new, $foundMedia found");
    }
}
