<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A word's senses: a jsonb list of {part_of_speech, definition, examples}.
 *
 * Every read and write is normalised to that shape, so readers never have to
 * guess which keys a sense has. Postgres reorders jsonb keys and reformats
 * the text, so the stored string never matches what was written; senses are
 * therefore compared decoded, or every save would rewrite them.
 *
 * @phpstan-type Sense array{part_of_speech: ?string, definition: string, examples: list<string>}
 *
 * @implements CastsAttributes<list<Sense>, array<array-key, array<string, mixed>>>
 */
class Senses implements CastsAttributes, ComparesCastableAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<Sense>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return self::normalise(json_decode($value ?? '[]', true));
    }

    /**
     * @param  array<array-key, array<string, mixed>>|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return json_encode(self::normalise($value ?? []));
    }

    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return self::normalise(json_decode($firstValue ?? '[]', true))
            === self::normalise(json_decode($secondValue ?? '[]', true));
    }

    /**
     * @param  array<array-key, array<string, mixed>>  $senses
     * @return list<Sense>
     */
    private static function normalise(array $senses): array
    {
        return array_map(
            fn (array $sense): array => [
                'part_of_speech' => filled($sense['part_of_speech'] ?? null) ? $sense['part_of_speech'] : null,
                'definition' => (string) ($sense['definition'] ?? ''),
                'examples' => array_values(array_filter(
                    $sense['examples'] ?? [],
                    fn (?string $example): bool => filled($example),
                )),
            ],
            array_values($senses),
        );
    }
}
