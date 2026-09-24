<?php

use App\Models\Creator;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

describe('viewAny()', function () {
    it('allows guests', function () {
        expect(Gate::allows('viewAny', Creator::class))->toBeTrue();
    });

    it('allows non-admins', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        expect(Gate::allows('viewAny', Creator::class))->toBeTrue();
    });

    it('allows admins', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        expect(Gate::allows('viewAny', Creator::class))->toBeTrue();
    });
});

describe('view()', function () {
    it('allows guests', function () {
        $creator = Creator::factory()->create();

        expect(Gate::allows('view', $creator))->toBeTrue();
    });

    it('allows non-admins', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);
        $creator = Creator::factory()->create();
        $this->actingAs($user);

        expect(Gate::allows('view', $creator))->toBeTrue();
    });

    it('allows admins', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $creator = Creator::factory()->create();
        $this->actingAs($admin);

        expect(Gate::allows('view', $creator))->toBeTrue();
    });
});

describe('create()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        expect(Gate::allows('create', Creator::class))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        expect(Gate::denies('create', Creator::class))->toBeTrue();
    });
});

describe('update()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $creator = Creator::factory()->create();
        $this->actingAs($admin);

        expect(Gate::allows('update', $creator))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);
        $creator = Creator::factory()->create();
        $this->actingAs($user);

        expect(Gate::denies('update', $creator))->toBeTrue();
    });
});

describe('delete()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $creator = Creator::factory()->create();
        $this->actingAs($admin);

        expect(Gate::allows('delete', $creator))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);
        $creator = Creator::factory()->create();
        $this->actingAs($user);

        expect(Gate::denies('delete', $creator))->toBeTrue();
    });
});
