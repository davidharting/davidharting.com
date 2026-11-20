<?php

use App\Clients\MerriamWebster\MerriamWebster;
use App\Clients\MerriamWebster\MerriamWebsterFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

const COLLEGIATE_URL = 'https://www.dictionaryapi.com/api/v3/references/collegiate/json/*';

beforeEach(function () {
    /** @var TestCase $this */
    Http::preventStrayRequests();
    Sleep::fake();
});

/**
 * A trimmed entry in the shape Merriam-Webster returns. Real entries carry
 * much more, such as pronunciations and the full nested definition.
 *
 * @param  list<string>  $shortDefinitions
 * @return array<string, mixed>
 */
function collegiateEntry(string $id, int $homograph, string $partOfSpeech, array $shortDefinitions): array
{
    $headword = explode(':', $id)[0];

    return [
        'meta' => ['id' => $id, 'stems' => [$headword, "{$headword}s"], 'offensive' => false],
        'hom' => $homograph,
        'hwi' => ['hw' => $headword],
        'fl' => $partOfSpeech,
        'shortdef' => $shortDefinitions,
    ];
}

function merriamWebster(?string $key = 'test-key'): MerriamWebster
{
    return new MerriamWebster($key);
}

describe('lookUp()', function () {
    test('returns one typed entry per headword for a word the dictionary knows', function () {
        /** @var TestCase $this */
        $response = [
            collegiateEntry('bow:1', 1, 'verb', ['to bend the head, body, or knee in respect', 'to submit']),
            collegiateEntry('bow:2', 2, 'noun', ['a weapon for shooting arrows']),
        ];
        Http::fake([COLLEGIATE_URL => Http::response($response)]);

        $lookup = merriamWebster()->lookUp('bow');

        expect($lookup->term)->toBe('bow')
            ->and($lookup->isFound())->toBeTrue()
            ->and($lookup->suggestions)->toBe([])
            ->and($lookup->response)->toBe($response)
            ->and($lookup->entries)->toHaveCount(2);

        expect($lookup->entries[0]->id)->toBe('bow:1')
            ->and($lookup->entries[0]->partOfSpeech)->toBe('verb')
            ->and($lookup->entries[1]->id)->toBe('bow:2')
            ->and($lookup->entries[1]->partOfSpeech)->toBe('noun');
    });

    test('returns spelling suggestions for a word the dictionary does not know', function () {
        /** @var TestCase $this */
        Http::fake([COLLEGIATE_URL => Http::response(['limb', 'limn', 'lime'])]);

        $lookup = merriamWebster()->lookUp('limm');

        expect($lookup->isFound())->toBeFalse()
            ->and($lookup->entries)->toBe([])
            ->and($lookup->suggestions)->toBe(['limb', 'limn', 'lime']);
    });

    test('treats an empty answer as not found with no suggestions', function () {
        /** @var TestCase $this */
        Http::fake([COLLEGIATE_URL => Http::response([])]);

        $lookup = merriamWebster()->lookUp('qwxzv');

        expect($lookup->isFound())->toBeFalse()
            ->and($lookup->suggestions)->toBe([]);
    });

    test('sends the key and puts the trimmed term in the path, encoded', function () {
        /** @var TestCase $this */
        Http::fake([COLLEGIATE_URL => Http::response([])]);

        merriamWebster()->lookUp(' ad hoc ');

        Http::assertSent(fn (Request $request) => $request->url()
            === 'https://www.dictionaryapi.com/api/v3/references/collegiate/json/ad%20hoc?key=test-key');
    });

    test('refuses a blank term without calling the API', function () {
        /** @var TestCase $this */
        Http::fake();

        expect(fn () => merriamWebster()->lookUp('   '))
            ->toThrow(InvalidArgumentException::class);

        Http::assertNothingSent();
    });

    test('fails without calling the API when no key is configured', function () {
        /** @var TestCase $this */
        Http::fake();

        expect(fn () => merriamWebster(key: null)->lookUp('limn'))
            ->toThrow(MerriamWebsterFailed::class, 'MERRIAM_WEBSTER_COLLEGIATE_KEY');

        Http::assertNothingSent();
    });

    test('fails when the key is rejected, which the API reports as a 200 with plain text', function () {
        /** @var TestCase $this */
        Http::fake([COLLEGIATE_URL => Http::response(
            'Invalid API key. Not subscribed for this reference.',
            200,
            ['Content-Type' => 'text/plain'],
        )]);

        expect(fn () => merriamWebster()->lookUp('limn'))
            ->toThrow(MerriamWebsterFailed::class, 'Invalid API key');
    });

    test('fails when the API answers with JSON that is not a list of entries', function () {
        /** @var TestCase $this */
        Http::fake([COLLEGIATE_URL => Http::response(['error' => 'Something went wrong'])]);

        expect(fn () => merriamWebster()->lookUp('limn'))
            ->toThrow(MerriamWebsterFailed::class, "didn't answer");
    });

    test('fails when an entry lacks its headword', function () {
        /** @var TestCase $this */
        Http::fake([COLLEGIATE_URL => Http::response([['meta' => ['id' => 'limn']]])]);

        expect(fn () => merriamWebster()->lookUp('limn'))
            ->toThrow(MerriamWebsterFailed::class, 'without an id or headword');
    });

    test('fails without the key in the error when the API keeps answering with an error status', function () {
        /** @var TestCase $this */
        Http::fake([COLLEGIATE_URL => Http::response('Service Unavailable', 503)]);

        expect(fn () => merriamWebster()->lookUp('limn'))
            ->toThrow(function (MerriamWebsterFailed $failure) {
                expect($failure->getMessage())->toContain('Looking up "limn" failed')
                    ->not->toContain('test-key');
            });
    });

    test('keeps the key out of the error when the connection fails', function () {
        /** @var TestCase $this */
        Http::fake([COLLEGIATE_URL => fn () => throw new ConnectionException(
            'cURL error 28: timed out for https://www.dictionaryapi.com/api/v3/references/collegiate/json/limn?key=test-key',
        )]);

        expect(fn () => merriamWebster()->lookUp('limn'))
            ->toThrow(function (MerriamWebsterFailed $failure) {
                expect($failure->getMessage())->not->toContain('test-key')
                    ->and($failure->getPrevious())->toBeNull();
            });
    });

    test('answers a repeat lookup from the cache without calling the API', function () {
        /** @var TestCase $this */
        Http::fake([COLLEGIATE_URL => Http::response([collegiateEntry('bow:1', 1, 'verb', ['to bend'])])]);

        merriamWebster()->lookUp('bow');
        $repeat = merriamWebster()->lookUp(' bow ');

        Http::assertSentCount(1);
        expect($repeat->entries[0]->id)->toBe('bow:1');
    });

    test('caches a word the dictionary does not know, since that costs a query too', function () {
        /** @var TestCase $this */
        Http::fake([COLLEGIATE_URL => Http::response(['limb'])]);

        merriamWebster()->lookUp('limm');
        merriamWebster()->lookUp('limm');

        Http::assertSentCount(1);
    });

    test('does not cache a failed lookup', function () {
        /** @var TestCase $this */
        Http::fake([COLLEGIATE_URL => Http::sequence()
            ->push('Invalid API key. Not subscribed for this reference.', 200, ['Content-Type' => 'text/plain'])
            ->push([collegiateEntry('bow:1', 1, 'verb', ['to bend'])]),
        ]);

        expect(fn () => merriamWebster()->lookUp('bow'))->toThrow(MerriamWebsterFailed::class);

        expect(merriamWebster()->lookUp('bow')->isFound())->toBeTrue();
    });

    test('retries a server error and returns the answer that follows', function () {
        /** @var TestCase $this */
        Http::fake([COLLEGIATE_URL => Http::sequence()
            ->push('Service Unavailable', 503)
            ->push('Too Many Requests', 429)
            ->push([collegiateEntry('bow:1', 1, 'verb', ['to bend'])]),
        ]);

        $lookup = merriamWebster()->lookUp('bow');

        expect($lookup->isFound())->toBeTrue();
        Http::assertSentCount(3);
    });

    test('does not retry an error that will not get better', function () {
        /** @var TestCase $this */
        Http::fake([COLLEGIATE_URL => Http::response('Forbidden', 403)]);

        expect(fn () => merriamWebster()->lookUp('bow'))->toThrow(MerriamWebsterFailed::class);

        Http::assertSentCount(1);
    });
});

describe('__construct()', function () {
    test('reads its key from the services config when resolved from the container', function () {
        /** @var TestCase $this */
        config(['services.merriam_webster.collegiate_key' => 'configured-key']);
        Http::fake([COLLEGIATE_URL => Http::response([])]);

        app(MerriamWebster::class)->lookUp('limn');

        Http::assertSent(fn (Request $request) => $request['key'] === 'configured-key');
    });
});
