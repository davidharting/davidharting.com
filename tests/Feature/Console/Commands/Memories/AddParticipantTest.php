<?php

use App\Models\MemoryParticipant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
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

describe('an email with no account yet', function () {
    test('creates the account and makes it a participant', function () {
        /** @var TestCase $this */
        Notification::fake();

        $this->artisan('memories:add-participant', [
            'email' => 'katie@example.test',
            '--name' => 'Katie Harting',
            '--display-name' => 'Katie',
        ])
            ->expectsOutputToContain('Created an account for katie@example.test.')
            ->expectsOutputToContain('katie@example.test is now a memory participant.')
            ->assertSuccessful();

        $katie = User::where('email', 'katie@example.test')->sole();

        expect($katie->name)->toBe('Katie Harting')
            ->and($katie->displayName())->toBe('Katie')
            ->and($katie->is_admin)->toBeFalse()
            ->and($katie->hasVerifiedEmail())->toBeFalse()
            ->and($katie->memoryParticipant)->not->toBeNull();
    });

    test('emails them a link to set their password', function () {
        /** @var TestCase $this */
        Notification::fake();

        $this->artisan('memories:add-participant', ['email' => 'katie@example.test', '--name' => 'Katie Harting'])
            ->expectsOutputToContain('Emailed katie@example.test a link to set their password')
            ->assertSuccessful();

        Notification::assertSentTo(User::where('email', 'katie@example.test')->sole(), ResetPassword::class);
    });

    test('emails them a link to verify their email', function () {
        /** @var TestCase $this */
        Notification::fake();

        $this->artisan('memories:add-participant', ['email' => 'katie@example.test', '--name' => 'Katie Harting'])
            ->assertSuccessful();

        Notification::assertSentTo(User::where('email', 'katie@example.test')->sole(), VerifyEmail::class);
    });

    test('the password link lets them choose a password and log in', function () {
        /** @var TestCase $this */
        Notification::fake();

        $this->artisan('memories:add-participant', ['email' => 'katie@example.test', '--name' => 'Katie Harting'])
            ->assertSuccessful();

        $katie = User::where('email', 'katie@example.test')->sole();

        Notification::assertSentTo($katie, ResetPassword::class, function (ResetPassword $notification) {
            /** @var TestCase $this */
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => 'katie@example.test',
                'password' => 'a-password-katie-chose',
                'password_confirmation' => 'a-password-katie-chose',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->post('/login', ['email' => 'katie@example.test', 'password' => 'a-password-katie-chose']);

        $this->assertAuthenticatedAs($katie);
    });

    test('needs --name, and creates nothing without it', function () {
        /** @var TestCase $this */
        Notification::fake();

        $this->artisan('memories:add-participant', ['email' => 'katie@example.test'])
            ->expectsOutputToContain('No user with the email [katie@example.test]. Pass --name to create their account.')
            ->assertFailed();

        expect(User::count())->toBe(0)
            ->and(MemoryParticipant::count())->toBe(0);

        Notification::assertNothingSent();
    });

    test('sends no emails to an existing user', function () {
        /** @var TestCase $this */
        Notification::fake();
        User::factory()->create(['email' => 'katie@example.test']);

        $this->artisan('memories:add-participant', ['email' => 'katie@example.test'])->assertSuccessful();

        Notification::assertNothingSent();
    });
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

    test('is refused before an account is created for them', function () {
        /** @var TestCase $this */
        Notification::fake();
        MemoryParticipant::factory()->count(2)->create();

        $this->artisan('memories:add-participant', ['email' => 'frodo@example.test', '--name' => 'Frodo Baggins'])
            ->assertFailed();

        expect(User::where('email', 'frodo@example.test')->exists())->toBeFalse();

        Notification::assertNothingSent();
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
