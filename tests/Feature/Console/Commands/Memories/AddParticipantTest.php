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

test('sets the display name when one is given', function () {
    /** @var TestCase $this */
    $katie = User::factory()->create(['email' => 'katie@example.test', 'name' => 'Katie Harting']);

    $this->artisan('memories:add-participant', ['email' => 'katie@example.test', '--display-name' => 'Katie'])
        ->assertSuccessful();

    expect($katie->fresh()->displayName())->toBe('Katie');
});

test('leaves the display name alone when none is given', function () {
    /** @var TestCase $this */
    $katie = User::factory()->create(['email' => 'katie@example.test', 'display_name' => 'Katie']);

    $this->artisan('memories:add-participant', ['email' => 'katie@example.test'])
        ->assertSuccessful();

    expect($katie->fresh()->display_name)->toBe('Katie');
});

test('refuses an email that belongs to no user', function () {
    /** @var TestCase $this */
    $this->artisan('memories:add-participant', ['email' => 'nobody@example.test'])
        ->expectsOutputToContain('No user with the email [nobody@example.test].')
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

    test('updates the display name when one is given', function () {
        /** @var TestCase $this */
        $katie = User::factory()->create(['email' => 'katie@example.test', 'display_name' => 'Kate']);
        MemoryParticipant::factory()->for($katie)->create();

        $this->artisan('memories:add-participant', ['email' => 'katie@example.test', '--display-name' => 'Katie'])
            ->assertSuccessful();

        expect($katie->fresh()->display_name)->toBe('Katie');
    });

    test('still works when two participants already exist', function () {
        /** @var TestCase $this */
        $david = User::factory()->create(['email' => 'david@example.test']);
        $katie = User::factory()->create(['email' => 'katie@example.test']);
        MemoryParticipant::factory()->for($david)->create();
        MemoryParticipant::factory()->for($katie)->create();

        $this->artisan('memories:add-participant', ['email' => 'katie@example.test', '--display-name' => 'Katie'])
            ->assertSuccessful();

        expect($katie->fresh()->display_name)->toBe('Katie');
    });
});

describe('a third participant', function () {
    test('is refused, naming the two who are already participants', function () {
        /** @var TestCase $this */
        MemoryParticipant::factory()->for(User::factory()->state(['email' => 'david@example.test']))->create();
        MemoryParticipant::factory()->for(User::factory()->state(['email' => 'katie@example.test']))->create();
        $frodo = User::factory()->create(['email' => 'frodo@example.test', 'display_name' => null]);

        $this->artisan('memories:add-participant', ['email' => 'frodo@example.test', '--display-name' => 'Frodo'])
            ->expectsOutputToContain('Monthly memories already has two participants')
            ->expectsOutputToContain('david@example.test')
            ->expectsOutputToContain('katie@example.test')
            ->assertFailed();

        expect(MemoryParticipant::count())->toBe(2)
            ->and($frodo->fresh()->memoryParticipant)->toBeNull()
            ->and($frodo->fresh()->display_name)->toBeNull();
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
