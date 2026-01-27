<?php

use App\Http\Controllers\FileShareController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotesIndexController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScorecardController;
use App\Livewire\AdminIndexPage;
use App\Livewire\Media\MediaPage;
use App\Models\Scorecard;
use Illuminate\Support\Facades\Route;

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
});

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::livewire('/backend', AdminIndexPage::class)->name('admin.index')->middleware('can:administrate');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/scorecards/create', [ScorecardController::class, 'create'])->name('scorecards.create');
Route::get('/scorecards/{scorecard}', [ScorecardController::class, 'show'])->name('scorecards.show');

Route::get('/notes', NotesIndexController::class)->name('notes.index');
Route::get('/notes/{note}', [NoteController::class, 'show'])->name('notes.show')->can('view', 'note');

Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
Route::get('/pages/{page}', [PageController::class, 'show'])->name('pages.show')->can('view', 'page');

Route::livewire('/media', MediaPage::class)->name('media.index');
Route::get('/media/log', function () {
    return redirect()->route('media.index', request()->query());
});
Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show')->can('view', 'media');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/fileshare/create', [FileShareController::class, 'create'])->name('fileshare.create');
    Route::post('/fileshare', [FileShareController::class, 'store'])->name('fileshare.store');
    Route::get('/fileshare/{path}', [FileShareController::class, 'show'])->name('fileshare.show')->where('path', '.*');
});

if (env('APP_ENV') == 'local') {
    Route::get('/mail/scorecard/{scorecard}', function (Scorecard $scorecard) {
        return new App\Mail\ScorecardLink($scorecard);
    });
}

require __DIR__.'/auth.php';
