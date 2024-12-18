<?php

namespace App\Actions\GoodreadsImport;

use DateTimeImmutable;
use League\Csv\Serializer\CastToDate;
use League\Csv\Serializer\CastToEnum;
use League\Csv\Serializer\CastToInt;
use League\Csv\Serializer\CastToString;
use League\Csv\Serializer\MapCell;

/**
 * A row from a Goodreads export CSV file.
 * DTO object intended to be consumed by League CSV
 */
class Row
{
    #[MapCell(
        column: 'Title',
        cast: CastToString::class,
        convertEmptyStringToNull: false,
        trimFieldValueBeforeCasting: true,
    )]
    public string $title;

    #[MapCell(
        column: 'Author',
        cast: CastToString::class,
        convertEmptyStringToNull: true,
        trimFieldValueBeforeCasting: true,
    )]
    public ?string $author;

    #[MapCell(
        column: 'Year Published',
        cast: CastToInt::class,
        trimFieldValueBeforeCasting: true,
    )]
    public ?int $publicationYear;

    #[MapCell(
        column: 'Exclusive Shelf',
        cast: CastToEnum::class,
        trimFieldValueBeforeCasting: false,
    )]
    public Shelf $shelf;

    #[MapCell(
        column: 'Date Added',
        cast: CastToDate::class,
        trimFieldValueBeforeCasting: true,
    )]
    public DateTimeImmutable $dateAdded;

    #[MapCell(
        column: 'Date Read',
        cast: CastToDate::class,
        trimFieldValueBeforeCasting: true,
    )]
    public ?DateTimeImmutable $dateRead;
}
