<?php

use App\Filament\Resources\Words\Pages\CreateWord;
use App\Filament\Resources\Words\Pages\EditWord;
use App\Filament\Resources\Words\Pages\ListWords;
use App\Filament\Resources\Words\WordResource;
use App\Models\User;
use App\Models\Word;
use Livewire\Livewire;
use Tests\TestCase;

test('lists words, newest first', function () {
    /** @var TestCase $this */
    $older = Word::factory()->create(['word' => 'ephemeral', 'created_at' => now()->subDay()]);
    $newer = Word::factory()->create(['word' => 'laconic']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(ListWords::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
});

test('searching the list matches words case-insensitively', function () {
    /** @var TestCase $this */
    $ephemeral = Word::factory()->create(['word' => 'ephemeral']);
    $laconic = Word::factory()->create(['word' => 'laconic']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(ListWords::class)
        ->searchTable('EPHEM')
        ->assertCanSeeTableRecords([$ephemeral])
        ->assertCanNotSeeTableRecords([$laconic]);
});

test('records a word with its senses and a short definition', function () {
    /** @var TestCase $this */
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CreateWord::class)
        ->fillForm([
            'word' => 'ephemeral',
            'short_definition' => 'here today, gone tomorrow',
            'senses' => [
                ['part_of_speech' => 'adjective', 'definition' => 'lasting a very short time', 'examples' => [['example' => 'an ephemeral fashion']]],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $word = Word::sole();
    expect($word->word)->toBe('ephemeral')
        ->and($word->short_definition)->toBe('here today, gone tomorrow')
        ->and($word->senses)->toBe([
            ['part_of_speech' => 'adjective', 'definition' => 'lasting a very short time', 'examples' => ['an ephemeral fashion']],
        ]);
});

test('refuses a word already on the list in another case', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CreateWord::class)
        ->fillForm(['word' => 'Ephemeral'])
        ->call('create')
        ->assertHasFormErrors(['word']);

    expect(Word::count())->toBe(1);
});

test('edits a word, keeping its own spelling available to it and its senses intact', function () {
    /** @var TestCase $this */
    $senses = [['part_of_speech' => 'adjective', 'definition' => 'lasting a very short time', 'examples' => ['an ephemeral fashion']]];
    $word = Word::factory()->create(['word' => 'ephemeral', 'short_definition' => 'brief', 'senses' => $senses]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(EditWord::class, ['record' => $word->id])
        ->fillForm(['word' => 'Ephemeral', 'short_definition' => 'here today, gone tomorrow'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($word->refresh())
        ->word->toBe('Ephemeral')
        ->short_definition->toBe('here today, gone tomorrow')
        ->senses->toBe($senses);
});

test('refuses respelling a word onto another word on the list', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);
    $word = Word::factory()->create(['word' => 'ephemerel']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(EditWord::class, ['record' => $word->id])
        ->fillForm(['word' => 'EPHEMERAL'])
        ->call('save')
        ->assertHasFormErrors(['word']);

    expect($word->refresh()->word)->toBe('ephemerel');
});

test('a non-admin cannot open the word list', function () {
    /** @var TestCase $this */
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->get(WordResource::getUrl('index'))
        ->assertForbidden();
});
