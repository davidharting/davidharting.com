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
