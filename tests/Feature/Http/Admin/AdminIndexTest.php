<?php

use App\Models\User;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

test('anonymous user not allowed', function () {
    /** @var TestCase $this */
    $response = $this->get('/backend');
    $response->assertStatus(403);
    $response->assertSeeText('Forbidden');
});

test('regular user not allowed', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get('/backend');
    $response->assertStatus(403);
    $response->assertSeeText('Forbidden');
});

test('Visible to admin', function () {
    /** @var TestCase $this */
    $user = User::factory()->admin()->create();
    $response = $this->actingAs($user)->get('/backend');
    $response->assertStatus(200);
    $response->assertSeeTextInOrder([
        'Admin',
        'Filament Admin',
        'Backup database',
    ]);
});

test('anonymous user cannot backup database', function () {
    /** @var TestCase $this */
    $response = $this->post('/backend/backup');
    $response->assertStatus(403);
});

test('regular user cannot backup database', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $response = $this->actingAs($user)->post('/backend/backup');
    $response->assertStatus(403);
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
            errorOutput: 'pg_dump version 14 does not match postgres version 15.1',
            exitCode: 1
        ),
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post('/backend/backup');
    $response->assertRedirect('/backend');
    $response->assertSessionHasErrors('backup');
    expect(session('errors')->get('backup')[0])->toContain('Database backup failed: pg_dump version 14 does not match postgres version 15.1');
});
