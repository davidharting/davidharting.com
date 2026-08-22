<?php

use App\Support\DatabaseView;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

describe('definition()', function () {
    test('reads the select body from the versioned file', function () {
        $select = DatabaseView::definition('media_tracking_summary', 'v1');

        expect($select)->toContain('FROM media m');
        expect($select)->not->toEndWith(';');
    });

    test('throws when the version file does not exist', function () {
        expect(fn () => DatabaseView::definition('media_tracking_summary', 'v999'))
            ->toThrow(RuntimeException::class, 'v999');
    });

    describe('when the version file is empty', function () {
        beforeEach(function () {
            $this->path = DatabaseView::path('database_view_test_fixture', 'v_empty');
            File::put($this->path, "  \n");
        });

        afterEach(function () {
            File::delete($this->path);
        });

        test('throws', function () {
            expect(fn () => DatabaseView::definition('database_view_test_fixture', 'v_empty'))
                ->toThrow(RuntimeException::class, 'empty');
        });
    });
});

describe('drop()', function () {
    test('removes the view so blocked table changes can proceed', function () {
        /** @var TestCase $this */
        DatabaseView::drop('media_tracking_summary');

        $exists = DB::selectOne(
            "SELECT 1 AS found FROM pg_views WHERE viewname = 'media_tracking_summary'"
        );

        $this->assertNull($exists);
    });

    test('is a no-op when the view does not exist', function () {
        /** @var TestCase $this */
        DatabaseView::drop('media_tracking_summary');
        DatabaseView::drop('media_tracking_summary');

        $this->assertTrue(true);
    });
});

describe('apply()', function () {
    test('recreates the view from its definition file', function () {
        /** @var TestCase $this */
        DB::statement('DROP VIEW IF EXISTS media_tracking_summary');

        DatabaseView::apply('media_tracking_summary', 'v1');

        $exists = DB::selectOne(
            "SELECT 1 AS found FROM pg_views WHERE viewname = 'media_tracking_summary'"
        );

        $this->assertNotNull($exists);
    });

    test('replaces a view whose columns changed shape', function () {
        /** @var TestCase $this */
        DB::statement('DROP VIEW IF EXISTS media_tracking_summary');
        DB::statement('CREATE VIEW media_tracking_summary AS SELECT 1 AS something_else');

        DatabaseView::apply('media_tracking_summary', 'v1');

        $columns = DB::table('information_schema.columns')
            ->where('table_name', 'media_tracking_summary')
            ->pluck('column_name');

        $this->assertContains('media_id', $columns->all());
        $this->assertNotContains('something_else', $columns->all());
    });

    describe('round-tripping through versions', function () {
        afterEach(function () {
            DatabaseView::drop('database_view_test_fixture');
        });

        test('migrating up to v2 and back down to v1 restores each version\'s output', function () {
            /** @var TestCase $this */
            DatabaseView::apply('database_view_test_fixture', 'v1');
            $v1 = DB::table('database_view_test_fixture')->first();
            $this->assertSame('v1', $v1->label);
            $this->assertFalse(property_exists($v1, 'is_current'));

            DatabaseView::apply('database_view_test_fixture', 'v2');
            $v2 = DB::table('database_view_test_fixture')->first();
            $this->assertSame('v2', $v2->label);
            $this->assertTrue($v2->is_current);

            DatabaseView::apply('database_view_test_fixture', 'v1');
            $reverted = DB::table('database_view_test_fixture')->first();
            $this->assertSame('v1', $reverted->label);
            $this->assertFalse(property_exists($reverted, 'is_current'));
        });
    });
});
