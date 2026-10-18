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

describe('otherThan()', function () {
    test('is the other participant', function () {
        /** @var TestCase $this */
        [$david, $katie] = User::factory()->count(2)->create();
        MemoryParticipant::create(['user_id' => $david->id]);
        MemoryParticipant::create(['user_id' => $katie->id]);

        expect(MemoryParticipant::otherThan($david)?->is($katie))->toBeTrue()
            ->and(MemoryParticipant::otherThan($katie)?->is($david))->toBeTrue();
    });

    test('is null for someone who is not a participant', function () {
        /** @var TestCase $this */
        [$david, $katie, $stranger] = User::factory()->count(3)->create();
        MemoryParticipant::create(['user_id' => $david->id]);
        MemoryParticipant::create(['user_id' => $katie->id]);

        expect(MemoryParticipant::otherThan($stranger))->toBeNull();
    });

    test('is null unless there are exactly two participants', function (int $count) {
        /** @var TestCase $this */
        $users = User::factory()->count($count)->create();
        $users->each(fn (User $user) => MemoryParticipant::create(['user_id' => $user->id]));

        expect(MemoryParticipant::otherThan($users->first()))->toBeNull();
    })->with([1, 3]);
});
