<?php

use App\Support\MerriamWebster\MerriamWebster;
use App\Support\MerriamWebster\MerriamWebsterFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

const LEARNERS_URL = 'https://www.dictionaryapi.com/api/v3/references/learners/json/*';

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.merriam_webster.learners_key' => 'test-key']);
});

/**
 * A trimmed entry in the shape Merriam-Webster returns: meta, headword,
 * functional label and short definitions. Real entries carry much more.
 *
 * @return array<string, mixed>
 */
function learnersEntry(string $id, string $partOfSpeech, array $shortDefinitions): array
{
    return [
        'meta' => ['id' => $id, 'stems' => [explode(':', $id)[0]], 'offensive' => false],
        'hwi' => ['hw' => explode(':', $id)[0]],
        'fl' => $partOfSpeech,
        'shortdef' => $shortDefinitions,
    ];
}

function merriamWebster(): MerriamWebster
{
    return app(MerriamWebster::class);
}

describe('lookUp()', function () {
    test('returns every entry for a word the dictionary knows', function () {
        $response = [
            learnersEntry('limn:1', 'verb', ['to draw or paint']),
            learnersEntry('limn:2', 'verb', ['to describe in words']),
        ];
        Http::fake([LEARNERS_URL => Http::response($response)]);

        $lookup = merriamWebster()->lookUp('limn');

        expect($lookup->term)->toBe('limn')
            ->and($lookup->isFound())->toBeTrue()
            ->and($lookup->entries())->toBe($response)
            ->and($lookup->suggestions())->toBe([])
            ->and($lookup->response)->toBe($response);
    });

    test('returns spelling suggestions for a word the dictionary does not know', function () {
        Http::fake([LEARNERS_URL => Http::response(['limb', 'limn', 'lime'])]);

        $lookup = merriamWebster()->lookUp('limm');

        expect($lookup->isFound())->toBeFalse()
            ->and($lookup->entries())->toBe([])
            ->and($lookup->suggestions())->toBe(['limb', 'limn', 'lime']);
    });

    test('treats an empty answer as not found with no suggestions', function () {
        Http::fake([LEARNERS_URL => Http::response([])]);

        $lookup = merriamWebster()->lookUp('qwxzv');

        expect($lookup->isFound())->toBeFalse()
            ->and($lookup->suggestions())->toBe([]);
    });

    test('sends the key and puts the term in the path, encoded', function () {
        Http::fake([LEARNERS_URL => Http::response([])]);

        merriamWebster()->lookUp('ad hoc');

        Http::assertSent(fn (Request $request) => $request->url()
            === 'https://www.dictionaryapi.com/api/v3/references/learners/json/ad%20hoc?key=test-key');
    });

    test('fails without calling the API when no key is configured', function () {
        config(['services.merriam_webster.learners_key' => null]);
        Http::fake();

        expect(fn () => merriamWebster()->lookUp('limn'))
            ->toThrow(MerriamWebsterFailed::class, 'MERRIAM_WEBSTER_LEARNERS_KEY');

        Http::assertNothingSent();
    });

    test('fails when the key is rejected, which the API reports as a 200 with plain text', function () {
        Http::fake([LEARNERS_URL => Http::response(
            'Invalid API key. Not subscribed for this reference.',
            200,
            ['Content-Type' => 'text/plain'],
        )]);

        expect(fn () => merriamWebster()->lookUp('limn'))
            ->toThrow(MerriamWebsterFailed::class, 'Invalid API key');
    });

    test('fails when the API answers with an error status', function () {
        Http::fake([LEARNERS_URL => Http::response('Service Unavailable', 503)]);

        expect(fn () => merriamWebster()->lookUp('limn'))
            ->toThrow(MerriamWebsterFailed::class, 'Looking up "limn" failed');
    });

    test('keeps the key out of the error when the connection fails', function () {
        /** @var TestCase $this */
        Http::fake([LEARNERS_URL => fn () => throw new ConnectionException(
            'cURL error 28: timed out for https://www.dictionaryapi.com/api/v3/references/learners/json/limn?key=test-key',
        )]);

        try {
            merriamWebster()->lookUp('limn');
            $this->fail('Expected the lookup to fail.');
        } catch (MerriamWebsterFailed $failure) {
            expect($failure->getMessage())->not->toContain('test-key')
                ->and($failure->getPrevious())->toBeNull();
        }
    });
});
