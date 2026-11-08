<?php

namespace App\Models;

use Database\Factories\WordFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A word David is learning. The short definition is what he practises
 * against: brief, plain-words definitions of the senses he cares about,
 * separated by semicolons. The optional full definition is markdown with
 * every sense and example, for when he wants the whole entry.
 */
class Word extends Model
{
    /** @use HasFactory<WordFactory> */
    use HasFactory;

    protected $fillable = ['word', 'short_definition', 'full_definition'];

    /**
     * Find the word that recording $word would duplicate, so a caller can
     * refuse it before saving rather than hit the unique index. Two
     * spellings count as the same word when they differ only in case or
     * surrounding whitespace.
     *
     * When editing, pass the word being edited as $ignoring so it is not
     * reported as a duplicate of itself.
     */
    public static function findDuplicate(string $word, ?Word $ignoring = null): ?Word
    {
        return static::query()
            ->named($word)
            ->when($ignoring?->exists, fn (Builder $query) => $query->whereKeyNot($ignoring->id))
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
