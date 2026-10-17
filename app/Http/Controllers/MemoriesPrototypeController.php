<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use App\Models\User;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * PROTOTYPE, throwaway. Three structurally different takes on the memories
 * list and month views, switched with `?variant=A|B|C`, rendered against the
 * seed data. Read-only; the reveal rules are hand-rolled here only so the
 * variants have every state to show.
 */
class MemoriesPrototypeController extends Controller
{
    public const VARIANTS = [
        'A' => 'Index + side by side',
        'B' => 'Stream of letters',
        'C' => 'Tiles + one at a time',
        'D' => 'Column of tiles + stacked prose',
        'E' => 'Column of tiles + one person per page',
    ];

    public function index(Request $request): View
    {
        $variant = $this->variant($request);

        return view("memories-prototype.{$variant}.index", [
            'variant' => $variant,
            'months' => $this->months($request->user()),
        ]);
    }

    public function show(Request $request, string $month): View
    {
        $variant = $this->variant($request);
        $months = $this->months($request->user());
        $index = $months->search(fn (array $m) => $m['month']->toDateString() === $month.'-01');
        abort_if($index === false, 404);

        return view("memories-prototype.{$variant}.show", [
            'variant' => $variant,
            'm' => $months[$index],
            'newer' => $months[$index - 1] ?? null,
            'older' => $months[$index + 1] ?? null,
        ]);
    }

    private function variant(Request $request): string
    {
        $variant = strtoupper((string) $request->query('variant', 'A'));

        return array_key_exists($variant, self::VARIANTS) ? $variant : 'A';
    }

    /**
     * Every month from launch to the latest open one, newest first, with the
     * viewer's and the other participant's memory and the derived reveal.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function months(User $viewer): Collection
    {
        $other = User::query()->whereHas('memoryParticipant')->whereKeyNot($viewer->id)->firstOrFail();
        $memories = Memory::query()->with('reactions.user')->get()->groupBy(fn (Memory $m) => $m->month->toDateString());
        $launch = $memories->keys()->sort()->first();
        $now = CarbonImmutable::now();

        $months = collect();
        $month = Month::latestOpen();

        while ($month->toDateString() >= $launch) {
            $forMonth = $memories->get($month->toDateString(), collect());
            $mine = $forMonth->firstWhere('user_id', $viewer->id);
            $theirs = $forMonth->firstWhere('user_id', $other->id);

            $revealedAt = null;
            if ($mine?->isShared() && $theirs?->isShared()) {
                $revealedAt = CarbonImmutable::instance($mine->shared_at->max($theirs->shared_at))->min($month->revealDeadline());
            } elseif ($now->greaterThanOrEqualTo($month->revealDeadline())) {
                $revealedAt = $month->revealDeadline();
            }

            $months->push([
                ...$this->copy($month, $viewer, $other, $mine, $theirs, $revealedAt),
                'month' => $month,
                'slug' => substr($month->toDateString(), 0, 7),
                'viewer' => $viewer,
                'other' => $other,
                'mine' => $mine,
                'theirs' => $theirs,
                'revealed' => $revealedAt !== null,
                'revealedAt' => $revealedAt,
                'atDeadline' => $revealedAt !== null && $revealedAt->equalTo($month->revealDeadline()),
                'deadline' => $month->revealDeadline(),
                'mineLate' => $mine?->isShared() && $revealedAt && $mine->shared_at->greaterThan($revealedAt),
                'theirsLate' => $theirs?->isShared() && $revealedAt && $theirs->shared_at->greaterThan($revealedAt),
                'photos' => $this->fakePhotoCounts($mine, $theirs),
            ]);

            $month = $month->previous();
        }

        return $months;
    }

    /**
     * The status sentences every variant shares, so they only differ in layout.
     *
     * @return array{myStatus: string, theirStatus: string, revealLine: ?string, cta: ?string}
     */
    private function copy(Month $month, User $viewer, User $other, ?Memory $mine, ?Memory $theirs, ?CarbonImmutable $revealedAt): array
    {
        $date = fn ($at) => CarbonImmutable::instance($at)->tz(Month::TIMEZONE)->format('M j');
        $name = $other->displayName();
        $deadline = $date($month->revealDeadline());

        $myStatus = match (true) {
            $mine === null => 'Not started',
            $mine->isDraft() => 'Draft, started '.$date($mine->created_at),
            default => 'Shared '.$date($mine->shared_at),
        };

        if ($revealedAt === null) {
            $theirStatus = match (true) {
                $theirs === null => "{$name} hasn't started yet",
                $theirs->isDraft() => "{$name} started on ".$date($theirs->created_at),
                default => "{$name} shared on ".$date($theirs->shared_at),
            };
            $revealLine = match (true) {
                $theirs?->isShared() && ! $mine?->isShared() => 'Share yours to reveal both.',
                $mine?->isShared() => "Reveals when {$name} shares, or on {$deadline} at the latest.",
                default => "Reveals when you've both shared, or on {$deadline} at the latest.",
            };
        } else {
            $theirStatus = $theirs?->isShared() ? "Shared {$date($theirs->shared_at)}" : "{$name} didn't share one";
            $revealLine = $revealedAt->equalTo($month->revealDeadline())
                ? "Revealed {$date($revealedAt)}, at the deadline."
                : "Revealed {$date($revealedAt)}, when you'd both shared.";
        }

        $cta = match (true) {
            $mine === null => 'Write yours',
            $mine->isDraft() => 'Keep writing',
            default => null,
        };

        return compact('myStatus', 'theirStatus', 'revealLine', 'cta');
    }

    /**
     * No photos are seeded yet, so a few memories get grey placeholders to
     * show where photos would sit in each layout.
     *
     * @return array{mine: int, theirs: int}
     */
    private function fakePhotoCounts(?Memory $mine, ?Memory $theirs): array
    {
        $count = fn (?Memory $memory) => $memory ? [0, 3, 1, 0, 5, 2][$memory->id % 6] : 0;

        return ['mine' => $count($mine), 'theirs' => $count($theirs)];
    }
}
