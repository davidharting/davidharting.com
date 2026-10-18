<?php

use App\Models\MemoryParticipant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Tests\TestCase;

test('belongs to a user', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    MemoryParticipant::create(['user_id' => $user->id]);

    expect($user->memoryParticipant)->not->toBeNull()
        ->and($user->memoryParticipant->user->is($user))->toBeTrue();
});

test('lists a user at most once', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    MemoryParticipant::create(['user_id' => $user->id]);

    MemoryParticipant::create(['user_id' => $user->id]);
})->throws(QueryException::class, 'memory_participants_user_id_unique');

describe('featureIsEnabled()', function () {
    test('is true with exactly two participants', function () {
        /** @var TestCase $this */
        User::factory()->count(2)->create()
            ->each(fn (User $user) => MemoryParticipant::create(['user_id' => $user->id]));

        expect(MemoryParticipant::featureIsEnabled())->toBeTrue();
    });

    test('is false with any other number of participants', function (int $count) {
        /** @var TestCase $this */
        User::factory()->count($count)->create()
            ->each(fn (User $user) => MemoryParticipant::create(['user_id' => $user->id]));

        expect(MemoryParticipant::featureIsEnabled())->toBeFalse();
    })->with([0, 1, 3]);
});

describe('otherThan()', function () {
    test('is the other participant', function () {
        /** @var TestCase $this */
        [$david, $katie] = User::factory()->count(2)->create();
        User::factory()->create();
        MemoryParticipant::create(['user_id' => $david->id]);
        MemoryParticipant::create(['user_id' => $katie->id]);

        expect(MemoryParticipant::otherThan($david)?->is($katie))->toBeTrue()
            ->and(MemoryParticipant::otherThan($katie)?->is($david))->toBeTrue();
    });

    test('is null when there is no other participant', function () {
        /** @var TestCase $this */
        $david = User::factory()->create();
        MemoryParticipant::create(['user_id' => $david->id]);

        expect(MemoryParticipant::otherThan($david))->toBeNull();
    });
});
