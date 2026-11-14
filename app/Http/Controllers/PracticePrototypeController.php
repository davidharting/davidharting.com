<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * PROTOTYPE, throw me away. Serves the in-app practice variants with a fixed
 * sample deck rather than real flashcards, so every preview shows real words
 * of varied length, and grading saves nothing. ?empty=1 starts with nothing due.
 */
class PracticePrototypeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $cards = $request->boolean('empty') ? [] : array_map(fn (array $card): array => [
            'word' => $card[0],
            'short' => $card[1],
            'full' => Str::markdown($card[2]),
        ], self::SAMPLE_DECK);

        return view('prototype.practice.index', ['cards' => $cards]);
    }

    /**
     * Word, short definition, full definition (markdown). Definitions are
     * written for this prototype, not taken from a dictionary.
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const array SAMPLE_DECK = [
        ['ephemeral', 'lasting a very short time', "**adjective**\n\n1. Lasting a very short time.\n   - _The ephemeral glow of the sunset faded before he found his camera._"],
        ['obstreperous', 'noisy and hard to control', "**adjective**\n\n1. Noisy and hard to control; unruly.\n   - _An obstreperous toddler at the next table._"],
        ['sesquipedalian', 'given to using long words; (of a word) long, with many syllables', "**adjective**\n\n1. Given to using long words.\n2. (Of a word) long, with many syllables.\n   - _A sesquipedalian lecture that lost half the room._\n\n**noun**\n\n1. A long word."],
        ['petrichor', 'the earthy smell when rain falls on dry ground', "**noun**\n\n1. The earthy smell when rain falls on dry ground.\n   - _The first storm of summer filled the street with petrichor._"],
        ['defenestrate', 'to throw someone out of a window; to remove someone from power abruptly', "**verb**\n\n1. To throw someone out of a window.\n2. (Informal) To remove someone from power or a position abruptly.\n   - _The board defenestrated its chief executive within a week._"],
        ['quotidian', 'ordinary; of the everyday', "**adjective**\n\n1. Ordinary or everyday; mundane.\n2. Occurring every day.\n   - _The quotidian rhythm of school runs and dishes._"],
        ['apophenia', 'seeing meaningful patterns or connections in random things', "**noun**\n\n1. The tendency to perceive meaningful patterns or connections in random or unrelated things.\n   - _Seeing faces in electrical outlets is a harmless kind of apophenia._"],
        ['limerence', 'the involuntary, obsessive longing of being infatuated with someone', "**noun**\n\n1. The involuntary state of being infatuated with someone, with obsessive thoughts and a longing to have the feeling returned.\n   - _What felt like love that spring was mostly limerence._"],
    ];
}
