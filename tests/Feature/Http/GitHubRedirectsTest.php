<?php

use Tests\TestCase;

test('redirects to the matching GitHub repository', function (string $path, string $repository) {
    /** @var TestCase $this */
    $this->get($path)
        ->assertFound()
        ->assertRedirect("https://github.com/davidharting/{$repository}");
})->with([
    'skills' => ['/skills', 'skills'],
    'dotfiles' => ['/dotfiles', 'dotfiles'],
]);
