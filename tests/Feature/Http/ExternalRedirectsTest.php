<?php

use Tests\TestCase;

test('redirects short paths to external profiles and repositories', function (string $path, string $destination) {
    /** @var TestCase $this */
    $this->get($path)
        ->assertFound()
        ->assertRedirect($destination);
})->with([
    'skills' => ['/skills', 'https://github.com/davidharting/skills'],
    'dotfiles' => ['/dotfiles', 'https://github.com/davidharting/dotfiles'],
    'github' => ['/github', 'https://github.com/davidharting'],
    'linkedin' => ['/linkedin', 'https://www.linkedin.com/in/davidharting'],
    'bluesky' => ['/bluesky', 'https://bsky.app/profile/davidharting.com'],
]);
