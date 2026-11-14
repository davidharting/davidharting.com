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
 * @phpstan-type Card array{id: int, word: string, definition: string}
 * @phpstan-type Round array{cards: list<Card>, nextDueAt: ?string}
 */
class Practice extends Component
{
    /**
     * How many cards one round deals at most. When it runs out the browser
     * asks for another, so a big backlog comes in batches.
     */
    public const int ROUND_SIZE = 20;

    public function mount(): void
    {
        $this->authorize('viewAny', Flashcard::class);
    }

    /**
     * The cards due now, most overdue first, and when the next card falls
     * due, for the caught-up screen when there are none.
     *
     * @return Round
     */
    #[Renderless]
    public function dueRound(): array
    {
        $this->authorize('viewAny', Flashcard::class);

        return [
            'cards' => $this->deal(Flashcard::query()->dueNow()),
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

        return $this->deal(Flashcard::query());
    }

    /**
     * Save how well David recalled a card and reschedule it. Returns true,
     * because Livewire resolves a failed call with null rather than
     * rejecting it, so the browser can tell a saved grade from a lost one.
     */
    #[Renderless]
    public function grade(int $flashcardId, bool $gotIt): bool
    {
        $flashcard = Flashcard::query()->findOrFail($flashcardId);

        $this->authorize('practice', $flashcard);

        $flashcard->grade($gotIt ? Rating::Good : Rating::Again);

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
     * Word flashcards from $query in practice order, as the browser shows them.
     *
     * @param  Builder<Flashcard>  $query
     * @return list<Card>
     */
    private function deal(Builder $query): array
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
                ];
            })
            ->all();
    }
}
