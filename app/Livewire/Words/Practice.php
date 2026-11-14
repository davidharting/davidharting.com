<?php

namespace App\Livewire\Words;

use App\Fsrs\Rating;
use App\Models\Flashcard;
use App\Models\Word;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * The practice page: a stack of word flashcards. The browser holds the stack
 * and moves on the moment a card is swiped, while each grade is saved in the
 * background, so practice never waits on the network. Nothing here
 * re-renders after the first load.
 */
class Practice extends Component
{
    /**
     * How many cards one round deals at most.
     */
    public const int ROUND_SIZE = 20;

    public function mount(): void
    {
        $this->authorize('viewAny', Flashcard::class);
    }

    /**
     * The first round: the cards due now, most overdue first. Compared to
     * the microsecond, as due is stored, so a word added a moment ago counts.
     *
     * @return list<array{id: int, word: string, definition: string}>
     */
    #[Computed]
    public function dueCards(): array
    {
        return $this->deal(Flashcard::query()->where('due', '<=', now()->format('Y-m-d H:i:s.uP')));
    }

    /**
     * Whether there is anything to practise at all, so the page can tell an
     * empty word list apart from being caught up.
     */
    #[Computed]
    public function hasWords(): bool
    {
        return Flashcard::query()->exists();
    }

    /**
     * Another round once caught up: whatever comes up next, due or not.
     *
     * @return list<array{id: int, word: string, definition: string}>
     */
    #[Renderless]
    public function practiseAhead(): array
    {
        $this->authorize('viewAny', Flashcard::class);

        return $this->deal(Flashcard::query());
    }

    /**
     * Save a grade and reschedule the card. Returns when the next card is
     * due afterwards, for the caught-up screen.
     */
    #[Renderless]
    public function grade(int $flashcardId, bool $gotIt): ?string
    {
        $flashcard = Flashcard::query()->findOrFail($flashcardId);

        $this->authorize('practice', $flashcard);

        $flashcard->grade($gotIt ? Rating::Good : Rating::Again);

        return $this->nextDue();
    }

    /**
     * When the next card falls due, e.g. "in 10 minutes", or null when there
     * are no cards.
     */
    #[Computed]
    public function nextDue(): ?string
    {
        $next = Flashcard::query()->nextUp()->first();

        return $next === null ? null : Carbon::instance($next->schedule->due)->diffForHumans();
    }

    public function render(): View
    {
        return view('livewire.words.practice');
    }

    /**
     * @param  Builder<Flashcard>  $query
     * @return list<array{id: int, word: string, definition: string}>
     */
    private function deal(Builder $query): array
    {
        return $query->nextUp()
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
