<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\FileShareController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaIndexController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotesIndexController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\PreventRequestForgery;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spatie\MarkdownResponse\Middleware\ProvideMarkdownResponse;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::feeds();

Route::get('/healthz', function () {
    return response('OK', 200);
})->withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
]);

Route::get('/', function () {
    return view('welcome');
})->name('home')->middleware(ProvideMarkdownResponse::class);

Route::withHead(robots: 'noindex, nofollow')->group(function () {
    Route::get('/backend', [AdminController::class, 'index'])->name('admin.index')->middleware('can:administrate')->withHead(title: 'Admin');
    Route::post('/backend/backup', [AdminController::class, 'backupDatabase'])->name('admin.backup')->middleware('can:administrate');

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->middleware(['auth', 'verified'])->name('dashboard')->withHead(title: 'Dashboard');

    Route::get('/kitchen-sink', function () {
        return view('kitchen-sink');
    })->name('kitchen-sink')->withHead(
        title: 'Kitchen Sink',
        description: 'Component showcase page',
    );
});

Route::get('/notes', NotesIndexController::class)->name('notes.index')->middleware(ProvideMarkdownResponse::class)->withHead(
    title: "David's Notes",
    description: 'Notes from David',
);
Route::get('/notes/{note}', [NoteController::class, 'show'])->name('notes.show')->can('view', 'note')->middleware(ProvideMarkdownResponse::class);

Route::get('/pages', [PageController::class, 'index'])->name('pages.index')->middleware(ProvideMarkdownResponse::class)->withHead(
    title: 'Pages',
    description: 'One-off pages on davidharting.com',
);
Route::get('/pages/{page}', [PageController::class, 'show'])->name('pages.show')->can('view', 'page')->middleware(ProvideMarkdownResponse::class);

Route::get('/media', MediaIndexController::class)->name('media.index')->middleware(ProvideMarkdownResponse::class)->withHead(
    title: "David's Media Log",
    description: 'I track what I read, watch, and play here!',
);
Route::get('/media/log', function () {
    return redirect()->route('media.index', request()->query());
});
Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show')->can('view', 'media')->whereNumber('media');

Route::withHead(robots: 'noindex, nofollow')->middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit')->withHead(title: 'Profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/fileshare/create', [FileShareController::class, 'create'])->name('fileshare.create');
    Route::post('/fileshare', [FileShareController::class, 'store'])->name('fileshare.store');
    Route::get('/fileshare/{path}', [FileShareController::class, 'show'])->name('fileshare.show')->where('path', '.*');
});

require __DIR__.'/auth.php';
