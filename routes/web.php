<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScorecardController;
use App\Livewire\Media\Backlog;
use App\Livewire\Media\Logbook;
use App\Livewire\Notes\NotesIndexPage;
use App\Livewire\Notes\ShowNotePage;
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

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/scorecards/create', [ScorecardController::class, 'create'])->name('scorecards.create');
Route::get('/scorecards/{scorecard}', [ScorecardController::class, 'show'])->name('scorecards.show');

Route::get('/notes', NotesIndexPage::class)->name('notes.index');
Route::get('/notes/{note}', ShowNotePage::class)->name('notes.show')->can('view', 'note');

Route::get('/media/log', Logbook::class)->name('media.logbook.show');
Route::get('/media/backlog', Backlog::class)->name('media.backlog.show')->middleware('can:view-backlog');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

if (env('APP_ENV') == 'local') {
    Route::get('/mail/scorecard/{scorecard}', function (Scorecard $scorecard) {
        return new App\Mail\ScorecardLink($scorecard);
    });
}

require __DIR__.'/auth.php';
