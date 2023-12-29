<?php

use App\Livewire\Intervals\IntervalsPage;

test('Renders', function () {
    expect(3)->toBeNumeric();
    Livewire::test(IntervalsPage::class)->assertSee('Interval timer');
});
