<?php

use App\Actions\GoodreadsImport\Row;
use App\Actions\GoodreadsImport\Shelf;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use Tests\TestCase;

test('example', function () {
    /** @var TestCase $this */
    Storage::fake('local');

    $headers = [
        'Book Id',
        'Title',
        'Author',
        'Author l-f',
        'Additional Authors',
        'ISBN',
        'ISBN13',
        'My Rating',
        'Average Rating',
        'Publisher',
        'Binding',
        'Number of Pages',
        'Year Published',
        'Original Publication Year',
        'Date Read',
        'Date Added',
        'Bookshelves',
        'Bookshelves with positions',
        'Exclusive Shelf',
        'My Review',
        'Spoiler',
        'Private Notes',
        'Read Count',
        'Owned Copies',

    ];

    Storage::disk('local')->put('goodreads.csv', Arr::join($headers, ','));
    Storage::disk('local')->append('goodreads.csv', '17332218,"Words of Radiance (The Stormlight Archive, #2)",Brandon Sanderson,"Sanderson, Brandon",,0765326361,9780765326362,5,4.76,Tor Books,Hardcover,1087,2014,2014,2023/07/06,2023/05/11,,,read,,,,1,0');
    Storage::disk('local')->append('goodreads.csv', '33514,The Elements of Style,William Strunk Jr.,"Jr., William Strunk",E.B. White,"=""""","=""""",0,4.18,Allyn & Bacon,Hardcover,105,1999,1918,,2023/07/05,to-read,to-read (#304),to-read,,,,0,0');

    $csv = Reader::createFromString(Storage::disk('local')->get('goodreads.csv'));

    $csv->setHeaderOffset(0);

    foreach ($csv->getRecordsAsObject(Row::class, $headers) as $offset => $row) {
        if ($offset === 1) {
            expect($row->title)->toBe('Words of Radiance (The Stormlight Archive, #2)');
            expect($row->author)->toBe('Brandon Sanderson');
            expect($row->publicationYear)->toBe(2014);
            expect($row->shelf)->toBe(Shelf::Read);
            expect($row->dateAdded->format('Y/m/d'))->toBe('2023/05/11');
            expect($row->dateRead->format('Y/m/d'))->toBe('2023/07/06');
        } elseif ($offset === 2) {
            expect($row->title)->toBe('The Elements of Style');
            expect($row->author)->toBe('William Strunk Jr.');
            expect($row->publicationYear)->toBe(1999);
            expect($row->shelf)->toBe(Shelf::Backlog);
            expect($row->dateAdded->format('Y/m/d'))->toBe('2023/07/05');
            expect($row->dateRead)->toBeNull();
        } else {
            $this->fail('Unexpected row offset'.$offset);
        }
    }
});
