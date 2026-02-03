<?php

use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Benchmark tests for the media index page.
 *
 * Run with: php artisan test tests/Feature/Http/Media/MediaIndexBenchmarkTest.php
 *
 * These tests measure render performance with realistic data volumes.
 * The assertions are intentionally loose - the goal is to observe timing via --profile flag.
 */
const ITEM_COUNT = 300;
const ITERATIONS = 3;

describe('Media Index Benchmark', function () {
    beforeEach(function () {
        // Seed realistic data volume
        $creators = Creator::factory()->count(30)->create();

        // Create finished media items (simulates a real media log)
        for ($i = 0; $i < ITEM_COUNT; $i++) {
            $finishedAt = Carbon::now()->subDays(rand(1, 2000));

            Media::factory()
                ->for($creators->random())
                ->has(
                    MediaEvent::factory()
                        ->finished()
                        ->at($finishedAt),
                    'events'
                )
                ->create([
                    'note' => $i % 3 === 0 ? "Note for item {$i}" : null,
                ]);
        }
    });

    test('benchmark: finished list as guest (x'.ITERATIONS.')', function () {
        $times = [];

        for ($i = 0; $i < ITERATIONS; $i++) {
            $start = microtime(true);
            $response = $this->get('/media');
            $times[] = (microtime(true) - $start) * 1000;
            $response->assertStatus(200);
        }

        $avg = array_sum($times) / count($times);
        $min = min($times);
        $max = max($times);

        echo "\n┌─────────────────────────────────────────────────────────┐\n";
        echo '│ BENCHMARK: Guest - Finished list ('.ITEM_COUNT." items)            │\n";
        echo "├─────────────────────────────────────────────────────────┤\n";
        echo sprintf("│ Avg: %7.2fms | Min: %7.2fms | Max: %7.2fms       │\n", $avg, $min, $max);
        echo "└─────────────────────────────────────────────────────────┘\n";

        expect($avg)->toBeLessThan(5000);
    });

    test('benchmark: finished list as admin (x'.ITERATIONS.')', function () {
        $admin = User::factory(['is_admin' => true])->create();
        $this->actingAs($admin);

        $times = [];

        for ($i = 0; $i < ITERATIONS; $i++) {
            $start = microtime(true);
            $response = $this->get('/media');
            $times[] = (microtime(true) - $start) * 1000;
            $response->assertStatus(200);
        }

        $avg = array_sum($times) / count($times);
        $min = min($times);
        $max = max($times);

        echo "\n┌─────────────────────────────────────────────────────────┐\n";
        echo '│ BENCHMARK: Admin - Finished list ('.ITEM_COUNT." items)            │\n";
        echo "├─────────────────────────────────────────────────────────┤\n";
        echo sprintf("│ Avg: %7.2fms | Min: %7.2fms | Max: %7.2fms       │\n", $avg, $min, $max);
        echo "└─────────────────────────────────────────────────────────┘\n";

        expect($avg)->toBeLessThan(5000);
    });
});
