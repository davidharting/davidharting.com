<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Passport\AuthCode;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Livewire\Features\SupportTesting\Testable;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

/*
|--------------------------------------------------------------------------
| Test Impact Analysis
|--------------------------------------------------------------------------
|
| Opt in with --tia: the first run records which source files each test
| touches, and later runs replay all but the tests a change affects. It needs
| the PCOV coverage driver. Naming the default branch saves clones without
| origin/HEAD from stopping to ask.
|
*/

pest()->tia()->defaultBranch('main');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

// expect()->extend('toBeOne', function () {
//    return $this->toBe(1);
// });

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Issue a real Passport bearer token. Passport::actingAs() would skip TokenGuard,
 * so the guard and scope checks would never run.
 *
 * @param  string[]  $scopes
 */
function accessTokenFor(User $user, array $scopes = []): string
{
    app(ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');

    return $user->createToken('test-token', $scopes)->accessToken;
}

/**
 * Issue a real bearer token for the user with a refresh token row beside it,
 * the shape an authorization code flow (Claude.ai's connector) leaves behind.
 * Personal access tokens never get a refresh token, so one is added by hand.
 *
 * Every token comes from the same personal access client, created on first
 * use. Use oauthClientWithToken() when a test needs a second client.
 *
 * @return array{bearer: string, token: Token, refreshToken: RefreshToken}
 */
function bearerTokenWithRefreshToken(User $user): array
{
    $hasPersonalAccessClient = Passport::client()->newQuery()->where('revoked', false)->get()
        ->contains(fn (Client $client): bool => $client->hasGrantType('personal_access'));

    if (! $hasPersonalAccessClient) {
        app(ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
    }

    $result = $user->createToken('test-token', ['mcp:use']);

    return [
        'bearer' => $result->accessToken,
        'token' => $result->token,
        'refreshToken' => refreshTokenFor($result->token),
    ];
}

/**
 * A separate client with a token and refresh token of its own. The token is
 * a bare row, not a usable bearer: tests use it to check it is left alone.
 *
 * @return array{client: Client, token: Token, refreshToken: RefreshToken}
 */
function oauthClientWithToken(User $user): array
{
    $client = Client::factory()->create();

    $token = Passport::token()->forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->getKey(),
        'client_id' => $client->getKey(),
        'scopes' => ['mcp:use'],
        'revoked' => false,
        'expires_at' => now()->addYear(),
    ]);

    return ['client' => $client, 'token' => $token, 'refreshToken' => refreshTokenFor($token)];
}

function refreshTokenFor(Token $token): RefreshToken
{
    return Passport::refreshToken()->forceCreate([
        'id' => Str::random(80),
        'access_token_id' => $token->getKey(),
        'revoked' => false,
        'expires_at' => now()->addYear(),
    ]);
}

/**
 * A pending authorization code for the client: a dependent row that belongs
 * to the client rather than to any token.
 */
function oauthAuthCodeFor(Client $client, User $user): AuthCode
{
    return Passport::authCode()->forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->getKey(),
        'client_id' => $client->getKey(),
        // AuthCode has no array cast on scopes, unlike Token, so store the JSON.
        'scopes' => json_encode(['mcp:use']),
        'revoked' => false,
        'expires_at' => now()->addMinutes(10),
    ]);
}

/**
 * A JSON-RPC tools/list call, the cheapest request that proves a bearer token
 * still gets through to /mcp/admin.
 *
 * @return array<string, mixed>
 */
function mcpToolsListRequest(): array
{
    return ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'];
}

/**
 * Paste a file into a Filament form's markdown editor the way the browser
 * does: upload it to Livewire, then ask the editor to save it.
 *
 * @return string|null The URL the editor would insert into the markdown, or null when the save failed.
 */
function pasteIntoMarkdownEditor(Testable $form, UploadedFile $file, string $field = 'markdown_content'): ?string
{
    $form->set("componentFileAttachments.data.{$field}", $file);
    $editor = $form->instance()->getSchema('form')->getComponentByStatePath($field);

    return $form->instance()->callSchemaComponentMethod($editor->getKey(), 'saveUploadedFileAttachmentAndGetUrl');
}
