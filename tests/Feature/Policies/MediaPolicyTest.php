<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

describe('viewAny()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        expect(Gate::allows('viewAny', Media::class))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        expect(Gate::denies('viewAny', Media::class))->toBeTrue();
    });

    it('denies guests', function () {
        expect(Gate::denies('viewAny', Media::class))->toBeTrue();
    });
});

describe('view()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        expect(Gate::allows('view', $media))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        expect(Gate::denies('view', $media))->toBeTrue();
    });

    it('denies guests', function () {
        $media = Media::factory()->create();
        expect(Gate::denies('view', $media))->toBeTrue();
    });
});

describe('create()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        expect(Gate::allows('create', Media::class))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        expect(Gate::denies('create', Media::class))->toBeTrue();
    });

    it('denies guests', function () {
        expect(Gate::denies('create', Media::class))->toBeTrue();
    });
});

describe('update()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        expect(Gate::allows('update', $media))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        expect(Gate::denies('update', $media))->toBeTrue();
    });

    it('denies guests', function () {
        $media = Media::factory()->create();
        expect(Gate::denies('update', $media))->toBeTrue();
    });
});

describe('delete()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        expect(Gate::allows('delete', $media))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        expect(Gate::denies('delete', $media))->toBeTrue();
    });

    it('denies guests', function () {
        $media = Media::factory()->create();
        expect(Gate::denies('delete', $media))->toBeTrue();
    });
});

describe('restore()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        expect(Gate::allows('restore', $media))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        expect(Gate::denies('restore', $media))->toBeTrue();
    });

    it('denies guests', function () {
        $media = Media::factory()->create();
        expect(Gate::denies('restore', $media))->toBeTrue();
    });
});

describe('forceDelete()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        expect(Gate::allows('forceDelete', $media))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        expect(Gate::denies('forceDelete', $media))->toBeTrue();
    });

    it('denies guests', function () {
        $media = Media::factory()->create();
        expect(Gate::denies('forceDelete', $media))->toBeTrue();
    });
});

describe('seeNote()', function () {
    it('allows admins', function () {
        /** @var TestCase $this */
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        expect(Gate::allows('seeNote', Media::class))->toBeTrue();
    });

    it('denies non-admins', function () {
        /** @var TestCase $this */
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        expect(Gate::denies('seeNote', Media::class))->toBeTrue();
    });

    it('denies guests', function () {
        expect(Gate::denies('seeNote', Media::class))->toBeTrue();
    });
});
