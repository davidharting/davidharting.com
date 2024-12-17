<?php

namespace App\Actions\SofaImport;

use League\Csv\Reader;

class Importer
{
    public function __construct() {}

    /**
     * return array{
     *    'creators' : int,
     *    'media' : int,
     *    'events' : int
     * }
     */
    public function import(): array
    {
        $path = app_path('Actions/SofaImport/data/edited-with-author-SofaCSVExport-15122024-113435.csv');
        $reader = Reader::createFromPath($path);
        $reader->setHeaderOffset(0);

        $rows = $reader->getRecordsAsObject(SofaRow::class);

        $report = [
            'creators' => 0,
            'media' => 0,
            'events' => 0,
        ];

        foreach ($rows as $row) {
            $rowReport = (new SofaRowHandler($row))->handle();
            $report['creators'] += $rowReport['creators'];
            $report['media'] += $rowReport['media'];
            $report['events'] += $rowReport['events'];
        }

        return $report;
    }
}
