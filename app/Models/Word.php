<?php

namespace App\Models;

use Database\Factories\WordFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A word David is learning and its short definition: brief, plain-words
 * definitions of the senses he cares about, separated by semicolons when
 * there is more than one. Full dictionary entries will live alongside, not
 * on the word.
 */
class Word extends Model
{
    /** @use HasFactory<WordFactory> */
    use HasFactory;

    protected $fillable = ['word', 'short_definition'];

    /**
     * Every word is practised through exactly one flashcard, made with it and
     * removed with it.
     */
    protected static function booted(): void
    {
        static::created(function (Word $word): void {
            $word->flashcard()->save(Flashcard::unreviewed());
        });

        static::deleting(function (Word $word): void {
            $word->flashcard()->delete();
        });
    }

    /**
     * @return MorphOne<Flashcard, $this>
     */
    public function flashcard(): MorphOne
    {
        return $this->morphOne(Flashcard::class, 'quizzable');
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
