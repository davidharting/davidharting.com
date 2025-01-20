<?php

use App\Livewire\AdminIndexPage;
use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test(AdminIndexPage::class)
        ->assertStatus(200);
});
