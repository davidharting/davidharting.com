<?php

use App\Models\User;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

test('anonymous user cannot backup database', function () {
    /** @var TestCase $this */
    $response = $this->post('/backend/backup');

    $response->assertForbidden();
});

test('regular user cannot backup database', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/backend/backup');

    $response->assertForbidden();
});

test('successful database backup', function () {
    /** @var TestCase $this */
    Process::fake([
        'pg_dump *' => Process::result(output: 'some tar file'),
        'gzip' => Process::result(output: 'compressed tar file'),
    ]);

    $this->travelTo('2024-02-17 01:05:13');

    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)->post('/backend/backup');

    $response->assertDownload('database-backup-2024-02-17-01-05-13.tar.gz');
});

test('error backing up database', function () {
    /** @var TestCase $this */
    Process::fake([
        'pg_dump *' => Process::result(
            output: 'error',
            errorOutput: "pg_dump version 14 does not match postgres version 15.1\n",
            exitCode: 1
        ),
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post('/backend/backup');

    $response->assertRedirect(route('admin.index'));
    $response->assertSessionHas('backupError', "Database backup failed: pg_dump version 14 does not match postgres version 15.1\n");
});
