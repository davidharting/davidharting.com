<?php

use App\Support\DatabaseView;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

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
});
