<?php

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

describe('view', function () {
    it('allows anyone to view published pages', function () {
        /** @var TestCase $this */
        $page = Page::factory()->create(['is_published' => true]);

        // Guest user
        expect(Gate::allows('view', $page))->toBeTrue();

        // Regular user
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);
        expect(Gate::allows('view', $page))->toBeTrue();
    });

    it('returns 404 for unpublished pages to non-admins', function () {
        /** @var TestCase $this */
        $page = Page::factory()->unpublished()->create();

        // Guest user
        $response = Gate::inspect('view', $page);
        expect($response->denied())->toBeTrue();
        expect($response->status())->toBe(404);

        // Regular user
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);
        $response = Gate::inspect('view', $page);
        expect($response->denied())->toBeTrue();
        expect($response->status())->toBe(404);
    });

    it('allows admins to view unpublished pages', function () {
        /** @var TestCase $this */
        $page = Page::factory()->unpublished()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        expect(Gate::allows('view', $page))->toBeTrue();
    });
});

describe('before hook', function () {
    it('allows admins to bypass all checks', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $page = Page::factory()->create();

        $this->actingAs($admin);

        // Admins should be able to do everything
        expect(Gate::allows('view', $page))->toBeTrue();
        expect(Gate::allows('viewAny', Page::class))->toBeTrue();
        expect(Gate::allows('create', Page::class))->toBeTrue();
        expect(Gate::allows('update', $page))->toBeTrue();
        expect(Gate::allows('delete', $page))->toBeTrue();
    });
});

describe('restricted actions', function () {
    it('denies non-admins from creating pages', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user);
        expect(Gate::denies('create', Page::class))->toBeTrue();
    });

    it('denies non-admins from updating pages', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);
        $page = Page::factory()->create();

        $this->actingAs($user);
        expect(Gate::denies('update', $page))->toBeTrue();
    });

    it('denies non-admins from deleting pages', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);
        $page = Page::factory()->create();

        $this->actingAs($user);
        expect(Gate::denies('delete', $page))->toBeTrue();
    });
});
