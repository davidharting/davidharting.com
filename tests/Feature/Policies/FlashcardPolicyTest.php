<?php

use App\Models\Flashcard;
use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

dataset('abilities', [
    'viewAny' => [fn () => ['viewAny', Flashcard::class]],
    'view' => [fn () => ['view', Word::factory()->create()->flashcard]],
    'practice' => [fn () => ['practice', Word::factory()->create()->flashcard]],
]);

test('guests may not', function (array $ability) {
    /** @var TestCase $this */
    expect(Gate::allows(...$ability))->toBeFalse();
})->with('abilities');

test('non-admins may not', function (array $ability) {
    /** @var TestCase $this */
    $this->actingAs(User::factory()->create(['is_admin' => false]));

    expect(Gate::allows(...$ability))->toBeFalse();
})->with('abilities');

test('admins may', function (array $ability) {
    /** @var TestCase $this */
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    expect(Gate::allows(...$ability))->toBeTrue();
})->with('abilities');
