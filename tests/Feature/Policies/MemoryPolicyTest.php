<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

describe('viewAny', function () {
    test('allows a participant while there are exactly two', function () {
        /** @var TestCase $this */
        [$david, $katie] = User::factory()->count(2)->create();
        MemoryParticipant::create(['user_id' => $david->id]);
        MemoryParticipant::create(['user_id' => $katie->id]);

        expect(Gate::forUser($david)->allows('viewAny', Memory::class))->toBeTrue();
    });

    test('is not found for guests and anyone who is not a participant', function () {
        /** @var TestCase $this */
        [$david, $katie] = User::factory()->count(2)->create();
        MemoryParticipant::create(['user_id' => $david->id]);
        MemoryParticipant::create(['user_id' => $katie->id]);

        $guest = Gate::inspect('viewAny', Memory::class);
        $stranger = Gate::forUser(User::factory()->create(['is_admin' => true]))->inspect('viewAny', Memory::class);

        expect($guest->status())->toBe(404)
            ->and($stranger->status())->toBe(404);
    });

    test('is not found for a participant while the feature is not enabled', function () {
        /** @var TestCase $this */
        $david = User::factory()->create();
        MemoryParticipant::create(['user_id' => $david->id]);

        expect(Gate::forUser($david)->inspect('viewAny', Memory::class)->status())->toBe(404);
    });
});
