<?php

use App\Models\Memory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Insert a row straight into memory_photos, so the table's own rules are what
 * gets tested.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertMemoryPhoto(array $overrides = []): int
{
    return DB::table('memory_photos')->insertGetId([
        'memory_id' => $overrides['memory_id'] ?? Memory::factory()->create()->id,
        'position' => 0,
        'original_path' => 'memories/photos/'.fake()->uuid().'/original.jpg',
        'original_mime_type' => 'image/jpeg',
        'original_bytes' => 2_400_000,
        'created_at' => now(),
        'updated_at' => now(),
        ...$overrides,
    ]);
}

test('accepts a photo that is still processing', function () {
    /** @var TestCase $this */
    $id = insertMemoryPhoto();

    $this->assertDatabaseHas('memory_photos', ['id' => $id, 'variants' => null, 'failed_at' => null]);
});

test('accepts a ready photo with its copies', function () {
    /** @var TestCase $this */
    $id = insertMemoryPhoto(['variants' => json_encode(['web' => ['path' => 'web.jpg']])]);

    expect(json_decode(DB::table('memory_photos')->find($id)->variants, true))->toBe(['web' => ['path' => 'web.jpg']]);
});

test('accepts a failed photo with its reason', function () {
    /** @var TestCase $this */
    $id = insertMemoryPhoto(['failed_at' => now(), 'failure' => 'vips exited with 1']);

    $row = DB::table('memory_photos')->find($id);
    expect($row->failed_at)->not->toBeNull()
        ->and($row->failure)->toBe('vips exited with 1');
});

test('rejects a negative position', function () {
    /** @var TestCase $this */
    insertMemoryPhoto(['position' => -1]);
})->throws(QueryException::class, 'memory_photos_position_not_negative');

test('rejects an empty original', function () {
    /** @var TestCase $this */
    insertMemoryPhoto(['original_bytes' => 0]);
})->throws(QueryException::class, 'memory_photos_original_not_empty');

test('rejects two photos sharing one original file', function () {
    /** @var TestCase $this */
    insertMemoryPhoto(['original_path' => 'memories/photos/same/original.jpg']);

    insertMemoryPhoto(['original_path' => 'memories/photos/same/original.jpg']);
})->throws(QueryException::class, 'memory_photos_original_path_unique');

test('rejects a failure without a reason', function () {
    /** @var TestCase $this */
    insertMemoryPhoto(['failed_at' => now()]);
})->throws(QueryException::class, 'memory_photos_failure_has_reason');

test('rejects a reason without a failure time', function () {
    /** @var TestCase $this */
    insertMemoryPhoto(['failure' => 'vips exited with 1']);
})->throws(QueryException::class, 'memory_photos_failure_has_reason');

test('rejects a photo that is both ready and failed', function () {
    /** @var TestCase $this */
    insertMemoryPhoto([
        'variants' => json_encode(['web' => ['path' => 'web.jpg']]),
        'failed_at' => now(),
        'failure' => 'vips exited with 1',
    ]);
})->throws(QueryException::class, 'memory_photos_ready_or_failed');

test('keeps a memory from being deleted while it has photos', function () {
    /** @var TestCase $this */
    $memory = Memory::factory()->create();
    insertMemoryPhoto(['memory_id' => $memory->id]);

    $memory->delete();
})->throws(QueryException::class, 'memory_photos_memory_id_foreign');

test('stores its timestamps with a time zone', function () {
    /** @var TestCase $this */
    $types = collect(DB::select(
        "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'memory_photos' AND column_name IN ('created_at', 'updated_at', 'deleted_at', 'failed_at')"
    ))->pluck('data_type', 'column_name');

    expect($types)->toHaveCount(4)
        ->each->toBe('timestamp with time zone');
});
