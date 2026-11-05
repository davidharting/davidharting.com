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

test('records a word with its short definition', function () {
    /** @var TestCase $this */
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CreateWord::class)
        ->fillForm([
            'word' => 'ephemeral',
            'short_definition' => 'lasting a very short time; a short-lived plant',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Word::sole())
        ->word->toBe('ephemeral')
        ->short_definition->toBe('lasting a very short time; a short-lived plant');
});

test('requires a short definition', function () {
    /** @var TestCase $this */
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CreateWord::class)
        ->fillForm(['word' => 'ephemeral'])
        ->call('create')
        ->assertHasFormErrors(['short_definition' => 'required']);

    expect(Word::count())->toBe(0);
});

test('refuses a word already on the list in another case', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CreateWord::class)
        ->fillForm(['word' => 'Ephemeral', 'short_definition' => 'brief'])
        ->call('create')
        ->assertHasFormErrors(['word']);

    expect(Word::count())->toBe(1);
});

test('edits a word, keeping its own spelling available to it', function () {
    /** @var TestCase $this */
    $word = Word::factory()->create(['word' => 'ephemeral', 'short_definition' => 'brief']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(EditWord::class, ['record' => $word->id])
        ->fillForm(['word' => 'Ephemeral', 'short_definition' => 'here today, gone tomorrow'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($word->refresh())
        ->word->toBe('Ephemeral')
        ->short_definition->toBe('here today, gone tomorrow');
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
