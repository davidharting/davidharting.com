<?php

use App\Models\MemoryParticipant;
use App\Models\User;
use Tests\TestCase;

test('makes an existing user a participant', function () {
    /** @var TestCase $this */
    $katie = User::factory()->create(['email' => 'katie@example.test']);

    $this->artisan('memories:add-participant', ['email' => 'katie@example.test'])
        ->expectsOutputToContain('katie@example.test is now a memory participant.')
        ->assertSuccessful();

    expect($katie->fresh()->memoryParticipant)->not->toBeNull();
});

test('refuses an email that belongs to no user', function () {
    /** @var TestCase $this */
    $this->artisan('memories:add-participant', ['email' => 'nobody@example.test'])
        ->expectsOutputToContain('No user with the email [nobody@example.test]. Create their account first with users:add.')
        ->assertFailed();

    expect(MemoryParticipant::count())->toBe(0);
});

describe('running it again for a participant', function () {
    test('does not add them twice', function () {
        /** @var TestCase $this */
        User::factory()->create(['email' => 'katie@example.test']);

        $this->artisan('memories:add-participant', ['email' => 'katie@example.test'])->assertSuccessful();
        $this->artisan('memories:add-participant', ['email' => 'katie@example.test'])
            ->expectsOutputToContain('katie@example.test is already a memory participant.')
            ->assertSuccessful();

        expect(MemoryParticipant::count())->toBe(1);
    });

    test('is not mistaken for a third participant when two already exist', function () {
        /** @var TestCase $this */
        MemoryParticipant::factory()->for(User::factory()->state(['email' => 'david@example.test']))->create();
        MemoryParticipant::factory()->for(User::factory()->state(['email' => 'katie@example.test']))->create();

        $this->artisan('memories:add-participant', ['email' => 'katie@example.test'])
            ->expectsOutputToContain('katie@example.test is already a memory participant.')
            ->assertSuccessful();

        expect(MemoryParticipant::count())->toBe(2);
    });
});

describe('a third participant', function () {
    test('is refused, naming the two who are already participants', function () {
        /** @var TestCase $this */
        MemoryParticipant::factory()->for(User::factory()->state(['email' => 'david@example.test']))->create();
        MemoryParticipant::factory()->for(User::factory()->state(['email' => 'katie@example.test']))->create();
        $frodo = User::factory()->create(['email' => 'frodo@example.test']);

        $this->artisan('memories:add-participant', ['email' => 'frodo@example.test'])
            ->expectsOutputToContain('Monthly memories already has two participants')
            ->expectsOutputToContain('david@example.test')
            ->expectsOutputToContain('katie@example.test')
            ->assertFailed();

        expect(MemoryParticipant::count())->toBe(2)
            ->and($frodo->fresh()->memoryParticipant)->toBeNull();
    });
});

describe('whether the feature is live', function () {
    test('says it is still waiting after the first participant', function () {
        /** @var TestCase $this */
        User::factory()->create(['email' => 'david@example.test']);

        $this->artisan('memories:add-participant', ['email' => 'david@example.test'])
            ->expectsOutputToContain('Monthly memories goes live once there is a second participant.')
            ->assertSuccessful();
    });

    test('says it is live once there are two', function () {
        /** @var TestCase $this */
        MemoryParticipant::factory()->for(User::factory()->state(['email' => 'david@example.test']))->create();
        User::factory()->create(['email' => 'katie@example.test']);

        $this->artisan('memories:add-participant', ['email' => 'katie@example.test'])
            ->expectsOutputToContain('Monthly memories is live for both participants.')
            ->assertSuccessful();
    });
});
