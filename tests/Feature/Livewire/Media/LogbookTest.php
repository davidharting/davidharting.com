<?php

// This test could be moved.
// It used to test a full-page livewire component.
// Now the route just redirects

use Tests\TestCase;

test('redirects', function () {
    /** @var TestCase $this */
    $this->get('/media/log?year=2024')
        ->assertRedirect('/media?year=2024');
});
