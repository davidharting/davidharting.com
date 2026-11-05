<?php

namespace App\Models;

use App\Casts\Senses;
use Database\Factories\WordFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A word David is learning, with the dictionary senses he is learning it in.
 *
 * senses holds only that curated list (see the Senses cast). A raw dictionary
 * response, when lookup arrives, belongs in a column of its own.
 *
 * @phpstan-import-type Sense from Senses
 *
 * @property list<Sense> $senses
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'senses' => Senses::class,
        ];
    }

    /**
     * The word already on the list with this spelling, ignoring case and
     * surrounding whitespace, other than $except.
     */
    public static function spelledAs(string $word, ?Word $except = null): ?Word
    {
        return static::query()
            ->named($word)
            ->when($except?->exists, fn (Builder $query) => $query->whereKeyNot($except->id))
            ->first();
    }

    /**
     * Match a word case-insensitively, so "ephemeral" finds "Ephemeral".
     *
     * @param  Builder<Word>  $query
     */
    #[Scope]
    protected function named(Builder $query, string $word): void
    {
        $query->whereRaw('lower(word) = lower(?)', [trim($word)]);
    }

    /**
     * Stored trimmed, so a stray space cannot slip past the one-row-per-word
     * index on lower(word).
     *
     * @return Attribute<string, string>
     */
    protected function word(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => trim($value),
        );
    }
}
