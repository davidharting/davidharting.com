<?php

use App\Models\MediaEvent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

describe('viewAny()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        expect(Gate::allows('viewAny', MediaEvent::class))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        expect(Gate::denies('viewAny', MediaEvent::class))->toBeTrue();
    });
});

describe('view()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $mediaEvent = MediaEvent::factory()->create();
        $this->actingAs($admin);

        expect(Gate::allows('view', $mediaEvent))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);
        $mediaEvent = MediaEvent::factory()->create();
        $this->actingAs($user);

        expect(Gate::denies('view', $mediaEvent))->toBeTrue();
    });
});

describe('create()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        expect(Gate::allows('create', MediaEvent::class))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        expect(Gate::denies('create', MediaEvent::class))->toBeTrue();
    });
});

describe('update()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $mediaEvent = MediaEvent::factory()->create();
        $this->actingAs($admin);

        expect(Gate::allows('update', $mediaEvent))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);
        $mediaEvent = MediaEvent::factory()->create();
        $this->actingAs($user);

        expect(Gate::denies('update', $mediaEvent))->toBeTrue();
    });
});

describe('delete()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $mediaEvent = MediaEvent::factory()->create();
        $this->actingAs($admin);

        expect(Gate::allows('delete', $mediaEvent))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);
        $mediaEvent = MediaEvent::factory()->create();
        $this->actingAs($user);

        expect(Gate::denies('delete', $mediaEvent))->toBeTrue();
    });
});
