<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Makes David and Katie the two participants, which enables the feature.
 */
function memoryPolicyTestParticipants(TestCase $test): void
{
    [$test->david, $test->katie] = User::factory()->count(2)->create();
    MemoryParticipant::create(['user_id' => $test->david->id]);
    MemoryParticipant::create(['user_id' => $test->katie->id]);
}

describe('viewAny()', function () {
    test('allows a participant while there are exactly two', function () {
        /** @var TestCase $this */
        memoryPolicyTestParticipants($this);

        expect(Gate::forUser($this->david)->allows('viewAny', Memory::class))->toBeTrue();
    });

    test('denies guests and non-participants, even admins, with a 404', function () {
        /** @var TestCase $this */
        memoryPolicyTestParticipants($this);

        $guest = Gate::inspect('viewAny', Memory::class);
        $adminNonParticipant = Gate::forUser(User::factory()->create(['is_admin' => true]))->inspect('viewAny', Memory::class);

        expect($guest->status())->toBe(404)
            ->and($adminNonParticipant->status())->toBe(404);
    });

    test('is not found for a participant while the feature is not enabled', function () {
        /** @var TestCase $this */
        $david = User::factory()->create();
        MemoryParticipant::create(['user_id' => $david->id]);

        expect(Gate::forUser($david)->inspect('viewAny', Memory::class)->status())->toBe(404);
    });
});

describe('viewMonth()', function () {
    test('allows participants and denies guests, like viewAny', function () {
        /** @var TestCase $this */
        memoryPolicyTestParticipants($this);

        expect(Gate::forUser($this->david)->allows('viewMonth', Memory::class))->toBeTrue()
            ->and(Gate::inspect('viewMonth', Memory::class)->status())->toBe(404);
    });
});

describe('create()', function () {
    test('allows participants and denies guests, like viewAny', function () {
        /** @var TestCase $this */
        memoryPolicyTestParticipants($this);

        expect(Gate::forUser($this->david)->allows('create', Memory::class))->toBeTrue()
            ->and(Gate::inspect('create', Memory::class)->status())->toBe(404);
    });
});

describe('update()', function () {
    test('allows the author', function () {
        /** @var TestCase $this */
        memoryPolicyTestParticipants($this);
        $memory = Memory::factory()->for($this->david)->create();

        expect(Gate::forUser($this->david)->allows('update', $memory))->toBeTrue();
    });

    test('is not found for the other participant', function () {
        /** @var TestCase $this */
        memoryPolicyTestParticipants($this);
        $memory = Memory::factory()->for($this->david)->create();

        expect(Gate::forUser($this->katie)->inspect('update', $memory)->status())->toBe(404);
    });

    test('is not found for the author while the feature is not enabled', function () {
        /** @var TestCase $this */
        $author = User::factory()->create();
        MemoryParticipant::create(['user_id' => $author->id]);
        $memory = Memory::factory()->for($author)->create();

        expect(Gate::forUser($author)->inspect('update', $memory)->status())->toBe(404);
    });
});
