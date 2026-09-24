<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Every MediaPolicy ability has the same rule, admins only, so each one is
 * checked against every kind of caller rather than written out per method.
 * An ability that ever needs a different rule belongs in its own test.
 */
test('allows admins and denies everyone else', function (string $ability, bool $takesModel, ?bool $isAdmin) {
    /** @var TestCase $this */
    if ($isAdmin !== null) {
        $this->actingAs(User::factory()->create(['is_admin' => $isAdmin]));
    }

    $argument = $takesModel ? Media::factory()->create() : Media::class;

    expect(Gate::allows($ability, $argument))->toBe($isAdmin === true);
})->with([
    'viewAny' => ['viewAny', false],
    'view' => ['view', true],
    'create' => ['create', false],
    'update' => ['update', true],
    'delete' => ['delete', true],
    'restore' => ['restore', true],
    'forceDelete' => ['forceDelete', true],
    'seeNote' => ['seeNote', false],
])->with([
    'admin' => [true],
    'non-admin' => [false],
    'guest' => [null],
]);
