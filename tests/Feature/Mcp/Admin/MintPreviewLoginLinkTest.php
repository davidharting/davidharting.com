<?php

use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\MintPreviewLoginLink;
use App\Models\User;
use Laravel\Mcp\Request;
use Tests\TestCase;

beforeEach(fn () => config(['services.preview_login.key' => 'test-preview-key']));

describe('shouldRegister()', function () {
    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(MintPreviewLoginLink::class, ['pull_request' => 414]);

        $response->assertHasErrors(['Tool [mint-preview-login-link] not found']);
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new MintPreviewLoginLink)->handle(new Request(['pull_request' => 414]));

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to mint preview login links.');
    });

    test('mints a link to the requested page on the pull request preview', function () {
        /** @var TestCase $this */
        $this->freezeTime();
        $admin = User::factory()->admin()->create();

        $response = AdminServer::actingAs($admin)->tool(MintPreviewLoginLink::class, [
            'pull_request' => 414,
            'as' => 'frodo@example.com',
            'to' => '/memories',
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('url', fn (string $url) => str_starts_with($url, 'https://davidhartingdotcom-web-pr-414.onrender.com/preview-login?as=frodo%40example.com&to=%2Fmemories&expires='))
                ->where('expires_at', now()->addHours(24)->toIso8601String());
        });
    });

    test('explains why a link cannot be minted', function () {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create();

        $response = AdminServer::actingAs($admin)->tool(MintPreviewLoginLink::class, [
            'pull_request' => 414,
            'to' => 'https://evil.test',
        ]);

        $response->assertHasErrors(['The destination must be a path on the site']);
    });
});
