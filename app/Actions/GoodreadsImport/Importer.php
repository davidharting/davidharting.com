<?php

namespace App\Actions\GoodreadsImport;

use League\Csv\Reader;

class Importer
{
    private readonly string $filePath;

    private array $report;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->report = [
            'media' => 0,
            'creator' => 0,
            'events' => 0,
        ];
    }

    /**
     * Imports data from a file and processes each row.
     *
     * @return array{media: int, creator: int, events: int} An associative array containing the import report.
     */
    public function import(callable $callback): array
    {
        $reader = Reader::createFromPath($this->filePath);
        $reader->setHeaderOffset(0);

        $rows = $reader->getRecordsAsObject(Row::class);

        foreach ($rows as $row) {
            $handler = new RowHandler($row);
            $report = $handler->handle();
            $callback();
            $this->tally($report);
        }

        return $this->report;
    }

    private function tally(array $report): void
    {
        $this->report['media'] += $report['media'];
        $this->report['creator'] += $report['creator'];
        $this->report['events'] += $report['events'];
    }
}
