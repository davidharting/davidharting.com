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
        'sqlite3 *' => Process::result(output: ''),
        'gzip *' => Process::result(output: 'compressed backup'),
    ]);

    $this->travelTo('2024-02-17 01:05:13');

    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)->post('/backend/backup');
    $response->assertDownload('database-backup-2024-02-17-01-05-13.tar.gz');
});

test('error backing up database', function () {
    /** @var TestCase $this */
    Process::fake([
        'sqlite3 *' => Process::result(
            output: '',
            errorOutput: 'sqlite3: unable to open database',
            exitCode: 1
        ),
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->followingRedirects()->post('/backend/backup');
    $response->assertSeeText('Database backup failed: sqlite3: unable to open database');
});
