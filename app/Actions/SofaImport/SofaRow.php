<?php

namespace App\Actions\SofaImport;

use DateTimeImmutable;
use League\Csv\Serializer\CastToDate;
use League\Csv\Serializer\CastToEnum;
use League\Csv\Serializer\CastToString;
use League\Csv\Serializer\MapCell;

class SofaRow
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    #[MapCell(
        column: 'Item Title',
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
    public ?string $creator;

    #[MapCell(
        column: 'List Name',
        cast: CastToEnum::class,
        convertEmptyStringToNull: true,
        trimFieldValueBeforeCasting: true,
    )]
    public SofaList $listName;

    #[MapCell(
        column: 'Group',
        cast: CastToEnum::class,
        convertEmptyStringToNull: true,
        trimFieldValueBeforeCasting: true,
    )]
    public ListGroup $group;

    #[MapCell(
        column: 'Date Added',
        cast: CastToDate::class,
        convertEmptyStringToNull: true,
        trimFieldValueBeforeCasting: true,
    )]
    public DateTimeImmutable $dateAdded;

    #[MapCell(
        column: 'Date Edited',
        cast: CastToDate::class,
        convertEmptyStringToNull: true,
        trimFieldValueBeforeCasting: true,
    )]
    public DateTimeImmutable $dateEdited;

    #[MapCell(
        column: 'Category',
        cast: CastToEnum::class,
        convertEmptyStringToNull: true,
        trimFieldValueBeforeCasting: true,
    )]
    public Category $category; // Make enum

    #[MapCell(
        column: 'Notes',
        cast: CastToString::class,
        convertEmptyStringToNull: true,
        trimFieldValueBeforeCasting: true,
    )]
    public ?string $notes;
}
