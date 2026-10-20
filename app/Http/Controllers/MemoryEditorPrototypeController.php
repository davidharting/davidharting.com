<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Support\Month;
use App\View\Memories\MonthForViewer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * PROTOTYPE, throwaway (#272): three variants of the text-only memory
 * editor, switched with ?variant=, each forced into one state with ?state=.
 * Nothing is saved: autosave and Share are faked in the browser.
 */
class MemoryEditorPrototypeController extends Controller
{
    public const VARIANTS = [
        'A' => 'Own page, Share sheet',
        'B' => 'In place on the month page',
        'C' => 'Write, then review to share',
    ];

    public const STATES = [
        'fresh' => 'Fresh draft',
        'draft' => 'Draft with text',
        'saving' => 'Saving',
        'failed' => 'Failed save',
        'conflict' => 'Changed on another device',
        'sharing' => 'Sharing (confirm)',
        'waiting' => 'Shared, waiting',
        'revealed' => 'Shared, revealed',
        'editing-shared' => 'Editing after sharing',
    ];

    public function show(Request $request, string $month): View
    {
        $month = Month::parseYearMonth($month);
        $viewer = $request->user();
        $other = MemoryParticipant::otherThan($viewer);
        $monthForViewer = MonthForViewer::fromMemories(
            $month,
            $viewer,
            $other,
            Memory::query()->where('month', $month->toDateString())->get(),
        );

        $variant = array_key_exists($request->query('variant'), self::VARIANTS) ? $request->query('variant') : 'A';
        $state = array_key_exists($request->query('state'), self::STATES) ? $request->query('state') : 'draft';

        $body = $monthForViewer->viewerMemory?->body ?? '';
        if ($state === 'fresh') {
            $body = '';
        } elseif (trim($body) === '' || mb_strlen($body) < 200) {
            $body = self::SAMPLE_BODY;
        }

        $otherName = $other->displayName();
        $deadline = Month::dayLabel($month->revealDeadline());

        // The other person's state is forced to fit the viewer's state, so every
        // state reads the same whoever is logged in.
        $otherShared = in_array($state, ['revealed', 'editing-shared'], true)
            || ($state !== 'waiting' && (bool) $monthForViewer->otherMemory?->isShared());

        return view('memory-editor-prototype.show', [
            'variant' => $variant,
            'state' => $state,
            'month' => $month,
            'monthName' => Str::before($month->label(), ' '),
            'otherName' => $otherName,
            'deadline' => $deadline,
            'otherShared' => $otherShared,
            'otherLine' => $otherShared
                ? "{$otherName} has shared. Share yours to reveal both."
                : "{$otherName} started on Oct 3 and hasn't shared yet.",
            'body' => $body,
            'today' => Month::dayLabel(now()),
        ]);
    }

    private const SAMPLE_BODY = <<<'TEXT'
Things I want to remember:

The pumpkin patch, and how long it took to pick one. Forty minutes, three wagons, and in the end we went home with the first one we'd looked at.

Grandma's 90th. Everyone squeezed into the back room at the diner and she told the story about the train again, and this time I wrote it down.

The first cold morning when the heat kicked on and the whole house smelled like burnt dust for an hour.

We finally fixed the gate.
TEXT;
}
