<?php

namespace App\Actions\SofaImport;

use League\Csv\Reader;

class Importer
{
    public function __construct() {}

    public function import()
    {
        $path = app_path('Actions/SofaImport/data/SofaCSVExport-15122024-113435.csv');
        $reader = Reader::createFromPath($path);
        $reader->setHeaderOffset(0);

        $rows = $reader->getRecordsAsObject(SofaRow::class);

        foreach ($rows as $row) {
            echo $row->title;
        }
    }
}
