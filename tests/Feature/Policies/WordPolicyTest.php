<?php

use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

dataset('abilities', [
    'viewAny' => [fn () => ['viewAny', Word::class]],
    'view' => [fn () => ['view', Word::factory()->create()]],
    'create' => [fn () => ['create', Word::class]],
    'update' => [fn () => ['update', Word::factory()->create()]],
    'delete' => [fn () => ['delete', Word::factory()->create()]],
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

test('follows the administrate gate, not the admin flag directly', function () {
    /** @var TestCase $this */
    Gate::define('administrate', fn (User $user) => false);
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    expect(Gate::allows('viewAny', Word::class))->toBeFalse();
});
