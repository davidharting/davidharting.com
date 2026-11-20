<?php

use App\Clients\MerriamWebster\MerriamWebster;
use App\Clients\MerriamWebster\MerriamWebsterFailed;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\LookUpWord;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Laravel\Mcp\Request;
use Tests\Support\MerriamWebsterFixtures;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    Http::preventStrayRequests();
    Sleep::fake();
    config(['services.merriam_webster.collegiate_key' => 'test-key']);
});

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $response = AdminServer::tool(LookUpWord::class, ['word' => 'bow']);

        $response->assertHasErrors(['Tool [look-up-word] not found']);
        Http::assertNothingSent();
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(LookUpWord::class, ['word' => 'bow']);

        $response->assertHasErrors(['Tool [look-up-word] not found']);
        Http::assertNothingSent();
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new LookUpWord)->handle(new Request(['word' => 'bow']), new MerriamWebster('test-key'));

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to look up words.');
        Http::assertNothingSent();
    });

    test('returns each entry\'s headword, part of speech and short definitions', function () {
        /** @var TestCase $this */
        Http::fake([MerriamWebsterFixtures::URL => Http::response([
            MerriamWebsterFixtures::entry('bow', 1, 'verb', ['to bend the head or body in respect']),
            MerriamWebsterFixtures::entry('bow', 2, 'noun', ['a weapon for shooting arrows']),
            MerriamWebsterFixtures::entry('rainbow', null, 'noun', ['an arc of colors in the sky']),
        ])]);

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(LookUpWord::class, ['word' => ' bow ']);

        $response->assertOk();
        $response->assertStructuredContent([
            'word' => 'bow',
            'source' => "Merriam-Webster's Collegiate Dictionary",
            'found' => true,
            'entries' => [
                ['headword' => 'bow', 'homograph' => 1, 'part_of_speech' => 'verb', 'short_definitions' => ['to bend the head or body in respect'], 'is_offensive' => false],
                ['headword' => 'bow', 'homograph' => 2, 'part_of_speech' => 'noun', 'short_definitions' => ['a weapon for shooting arrows'], 'is_offensive' => false],
                ['headword' => 'rainbow', 'homograph' => null, 'part_of_speech' => 'noun', 'short_definitions' => ['an arc of colors in the sky'], 'is_offensive' => false],
            ],
            'suggestions' => [],
        ]);
    });

    test('returns spelling suggestions when the dictionary does not know the word', function () {
        /** @var TestCase $this */
        Http::fake([MerriamWebsterFixtures::URL => Http::response(['limb', 'limn'])]);

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(LookUpWord::class, ['word' => 'limm']);

        $response->assertStructuredContent(function ($json) {
            $json->where('found', false)
                ->where('entries', [])
                ->where('suggestions', ['limb', 'limn'])
                ->etc();
        });
    });

    test('answers a repeat lookup without calling the dictionary again', function () {
        /** @var TestCase $this */
        Http::fake([MerriamWebsterFixtures::URL => Http::response([MerriamWebsterFixtures::entry('bow', 1, 'verb', ['to bend'])])]);
        $admin = User::factory()->admin()->create();

        AdminServer::actingAs($admin)->tool(LookUpWord::class, ['word' => 'bow'])->assertOk();
        AdminServer::actingAs($admin)->tool(LookUpWord::class, ['word' => 'bow'])->assertOk();

        Http::assertSentCount(1);
    });

    test('says the lookup failed, without details, when the dictionary keeps failing', function () {
        /** @var TestCase $this */
        Exceptions::fake();
        Http::fake([MerriamWebsterFixtures::URL => Http::response('Service Unavailable', 503)]);

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(LookUpWord::class, ['word' => 'bow']);

        $response->assertHasErrors(['The dictionary lookup failed. Try again later.']);
        Exceptions::assertReported(MerriamWebsterFailed::class);
    });

    test('refuses a blank word without calling the dictionary', function () {
        /** @var TestCase $this */
        Http::fake();

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(LookUpWord::class, ['word' => '   ']);

        $response->assertHasErrors(['The word field is required.']);
        Http::assertNothingSent();
    });
});
