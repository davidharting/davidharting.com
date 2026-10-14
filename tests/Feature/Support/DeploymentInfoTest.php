<?php

use App\Support\DeploymentInfo;
use Tests\Support\ProcessEnvironment;

afterEach(fn () => ProcessEnvironment::restore());

describe('pullRequestNumber()', function () {
    test('reads the number off a preview service name', function () {
        ProcessEnvironment::set([
            'IS_PULL_REQUEST' => 'true',
            'RENDER_SERVICE_NAME' => 'davidhartingdotcom-web-pr-259',
        ]);

        expect(DeploymentInfo::pullRequestNumber())->toBe(259);
    });

    test('is null outside a pull request preview', function () {
        ProcessEnvironment::set([
            'IS_PULL_REQUEST' => 'false',
            'RENDER_SERVICE_NAME' => 'davidhartingdotcom-web-pr-259',
        ]);

        expect(DeploymentInfo::pullRequestNumber())->toBeNull();
    });

    test('is null when the service name carries no pull request number', function () {
        ProcessEnvironment::set([
            'IS_PULL_REQUEST' => 'true',
            'RENDER_SERVICE_NAME' => 'davidhartingdotcom-web',
        ]);

        expect(DeploymentInfo::pullRequestNumber())->toBeNull();
    });
});
