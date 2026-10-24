<?php

use App\Models\Memory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

test('holds every month from January 2016 to December 2096', function () {
    /** @var TestCase $this */
    $months = DB::table('months')->orderBy('id')->pluck('id');

    expect($months)->toHaveCount(81 * 12)
        ->and($months->first())->toBe('2016-01-01')
        ->and($months->last())->toBe('2096-12-01');
});

test('only holds first-of-month dates', function () {
    /** @var TestCase $this */
    DB::table('months')->insert(['id' => '2097-01-15']);
})->throws(QueryException::class, 'months_id_is_first_of_month');

test('refuses a memory for a month outside the table', function () {
    /** @var TestCase $this */
    DB::table('memories')->insert([
        'user_id' => User::factory()->create()->id,
        'month_id' => '2097-01-01',
        'body' => 'Too far ahead',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class, 'memories_month_id_foreign');

test('can\'t lose a month that has memories', function () {
    /** @var TestCase $this */
    $memory = Memory::factory()->create();

    DB::table('months')->where('id', $memory->month_id)->delete();
})->throws(QueryException::class, 'memories_month_id_foreign');
