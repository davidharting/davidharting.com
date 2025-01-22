<?php

use App\Livewire\AdminIndexPage;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

it('renders successfully', function () {
    Livewire::test(AdminIndexPage::class)
        ->assertStatus(200);
});

test('anonymous user cannot backup database', function () {

    Livewire::test(AdminIndexPage::class)
        ->call('backupDatabase')
        ->assertForbidden();
});

test('regular user cannot backup database', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user)->test(AdminIndexPage::class)
        ->call('backupDatabase')
        ->assertForbidden();
});

test('successful database backup', function () {
    /** @var TestCase $this */

    // Note, this test actually runs pg_dump. Required pg_dump installed and matching version of running database

    $this->travelTo('2024-02-17 01:05:13');

    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)->test(AdminIndexPage::class)
        ->call('backupDatabase')
        ->assertFileDownloaded('database-backup-2024-02-17-01-05-13.tar.gz');
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

    Livewire::actingAs($admin)->test(AdminIndexPage::class)
        ->call('backupDatabase')
        ->assertSeeText('Database backup failed: pg_dump version 14 does not match postgres version 15.1');
});
