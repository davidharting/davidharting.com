<?php

namespace App\Livewire\Words;

use App\Fsrs\Rating;
use App\Models\Flashcard;
use App\Models\Word;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * The practice page: a stack of word flashcards. The browser holds the stack
 * and moves on the moment a card is swiped, while each grade is saved in the
 * background, so practice never waits on the network. Nothing here
 * re-renders after the first load: the browser asks for each new round.
 *
 * @phpstan-type Card array{id: int, word: string, definition: string, due: bool}
 * @phpstan-type Round array{cards: list<Card>, dueCount: int, nextDueAt: ?string}
 */
class Practice extends Component
{
    /**
     * How many cards one round draws. After a round David sees his score
     * and can keep going while more words are due.
     */
    public const int ROUND_SIZE = 5;

    public function mount(): void
    {
        $this->authorize('viewAny', Flashcard::class);
    }

    /**
     * The next round of cards due now, most overdue first, with how many are
     * due in all and when the next card falls due, for the screens between
     * rounds.
     *
     * @return Round
     */
    #[Renderless]
    public function dueRound(): array
    {
        $this->authorize('viewAny', Flashcard::class);

        return [
            'cards' => $this->draw(Flashcard::query()->dueNow()),
            'dueCount' => Flashcard::query()->dueNow()->count(),
            'nextDueAt' => Flashcard::query()->nextUp()->first()?->schedule->due->format(DATE_ATOM),
        ];
    }

    /**
     * Another round once caught up: whatever comes up next, due or not.
     *
     * @return list<Card>
     */
    #[Renderless]
    public function practiseAhead(): array
    {
        $this->authorize('viewAny', Flashcard::class);

        return $this->draw(Flashcard::query());
    }

    /**
     * Save how well David recalled a card and reschedule it. The browser
     * sends the rating's number: Missed it is Again, Kind of got it is Hard,
     * and Got it is Good. Returns true, because Livewire resolves a failed
     * call with null rather than rejecting it, so the browser can tell a
     * saved grade from a lost one.
     */
    #[Renderless]
    public function grade(int $flashcardId, Rating $rating): bool
    {
        $flashcard = Flashcard::query()->findOrFail($flashcardId);

        $this->authorize('practice', $flashcard);

        $flashcard->grade($rating);

        return true;
    }

    public function render(): View
    {
        return view('livewire.words.practice', [
            'firstRound' => $this->dueRound(),
            'hasWords' => Flashcard::query()->exists(),
        ]);
    }

    /**
     * Draw a round of word flashcards from $query in practice order, as the browser shows them.
     *
     * @param  Builder<Flashcard>  $query
     * @return list<Card>
     */
    private function draw(Builder $query): array
    {
        return $query->nextUp()
            ->where('quizzable_type', (new Word)->getMorphClass())
            ->limit(self::ROUND_SIZE)
            ->with('quizzable')
            ->get()
            ->map(function (Flashcard $flashcard): array {
                /** @var Word $word */
                $word = $flashcard->quizzable;

                return [
                    'id' => $flashcard->id,
                    'word' => $word->word,
                    'definition' => $word->short_definition,
                    // False once practice goes beyond what's due, so the page can say so.
                    'due' => $flashcard->schedule->due <= now(),
                ];
            })
            ->all();
    }
}
