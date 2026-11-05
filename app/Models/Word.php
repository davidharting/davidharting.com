<?php

namespace App\Models;

use Database\Factories\WordFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A word David is learning, with the dictionary senses he is learning it in.
 *
 * Each sense is stored as {part_of_speech, definition, examples}, the shape a
 * dictionary entry breaks down into. Every write is normalised to that shape,
 * so readers never have to guess which keys a sense has.
 *
 * @property list<array{part_of_speech: ?string, definition: string, examples: list<string>}> $senses
 */
class Word extends Model
{
    /** @use HasFactory<WordFactory> */
    use HasFactory;

    protected $fillable = ['word', 'senses', 'short_definition'];

    protected $attributes = [
        'senses' => '[]',
    ];

    /**
     * Match a word case-insensitively, so "ephemeral" finds "Ephemeral".
     *
     * @param  Builder<Word>  $query
     */
    #[Scope]
    protected function named(Builder $query, string $word): void
    {
        $query->whereRaw('lower(word) = lower(?)', [$word]);
    }

    /**
     * @return Attribute<list<array{part_of_speech: ?string, definition: string, examples: list<string>}>, list<array<string, mixed>>>
     */
    protected function senses(): Attribute
    {
        // Postgres reorders jsonb keys, so senses are normalised on the way
        // out as well as on the way in.
        return Attribute::make(
            get: fn (?string $value): array => self::normaliseSenses(json_decode($value ?? '[]', true)),
            set: fn (?array $value): string => json_encode(self::normaliseSenses($value ?? [])),
        );
    }

    /**
     * @param  array<array-key, array<string, mixed>>  $senses
     * @return list<array{part_of_speech: ?string, definition: string, examples: list<string>}>
     */
    private static function normaliseSenses(array $senses): array
    {
        return array_map(
            fn (array $sense): array => [
                'part_of_speech' => filled($sense['part_of_speech'] ?? null) ? $sense['part_of_speech'] : null,
                'definition' => $sense['definition'],
                'examples' => array_values(array_filter(
                    $sense['examples'] ?? [],
                    fn (?string $example): bool => filled($example),
                )),
            ],
            array_values($senses),
        );
    }
}
