<?php

use App\Models\User;
use Illuminate\Support\Facades\Process;

it('renders admin index successfully', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertSee('Admin');
});

test('anonymous user cannot access admin index', function () {
    $this->get(route('admin.index'))
        ->assertForbidden();
});

test('regular user cannot access admin index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertForbidden();
});

test('anonymous user cannot backup database', function () {
    $this->post(route('admin.backup'))
        ->assertForbidden();
});

test('regular user cannot backup database', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.backup'))
        ->assertForbidden();
});

test('successful database backup', function () {
    Process::fake([
        'pg_dump *' => Process::result(output: 'some tar file'),
        'gzip' => Process::result(output: 'compressed tar file'),
    ]);

    $this->travelTo('2024-02-17 01:05:13');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.backup'))
        ->assertDownload('database-backup-2024-02-17-01-05-13.tar.gz');
});

test('error backing up database', function () {
    Process::fake([
        'pg_dump *' => Process::result(
            output: 'error',
            errorOutput: 'pg_dump version 14 does not match postgres version 15.1',
            exitCode: 1
        ),
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.backup'))
        ->assertRedirect(route('admin.index'))
        ->assertSessionHas('backupError', 'Database backup failed: pg_dump version 14 does not match postgres version 15.1');
});
