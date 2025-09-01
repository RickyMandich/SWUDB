<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CardsController;
use App\Http\Controllers\DecksController;
use App\Http\Controllers\JobController;

use App\Http\Controllers\UsersController;

use App\Jobs\ExecuteArtisanCommand;

use Illuminate\Support\Facades\Route;



Route::get('/', function(){return view('index');})->name("index");

Route::get("query", [AdminController::class, 'query'])->name("admin.query")->middleware('auth');
// Alias per compatibilità
Route::get("admin/query", [AdminController::class, 'query'])->name("query")->middleware('auth');

Route::get('/carte', [CardsController::class, 'index'])->name("carte");

Route::get('/carta/{espansione}/{numero}', [CardsController::class, 'show'])->name("carta");

Route::get('/update', [CardsController::class, 'startImport'])->name("carte.update");

Route::get('/scanAPI', [CardsController::class, 'scanAPI'])->name("carte.scanAPI");

Route::get('/optimizedImport', [CardsController::class, 'optimizedImport'])->name("carte.optimizedImport");

Route::get('/processNewCards', [CardsController::class, 'processNewCards'])->name("carte.processNewCards");

Route::get('/insertCards', [CardsController::class, 'insertCards'])->name("carte.insertCards");

Route::get('/checkScanStatus/{threadId}', [CardsController::class, 'checkScanStatus'])->name("carte.checkScanStatus");

Route::get('/cleanupCheckpoint', [CardsController::class, 'cleanupCheckpoint'])->name("carte.cleanupCheckpoint");

Route::get('/mazzi', [DecksController::class, 'index'])->name("mazzi");

Route::get('/collezione', [DecksController::class, 'collezione'])->name('collezione')->middleware('auth');

Route::post('/collezione/update', [DecksController::class, 'updateCollezione'])->name('collezione.update')->middleware('auth');

Route::get('/mazzo/{user}/{mazzo}', [DecksController::class, 'show'])->name("mazzo");

Route::post('/mazzo/{user}/{mazzo}/save', [DecksController::class, 'store'])->name("mazzo.save");

Route::post('/mazzo/create', [DecksController::class, 'create'])->name("mazzo.create");

Route::delete('/mazzo/{user}/{mazzo}', [DecksController::class, 'destroy'])->name("mazzo.delete")->middleware('auth');

Route::patch('/mazzo/{user}/{mazzo}/visibility', [DecksController::class, 'toggleVisibility'])->name("mazzo.toggle.visibility")->middleware('auth');

Route::patch('/mazzo/{user}/{mazzo}/rename', [DecksController::class, 'rename'])->name("mazzo.rename")->middleware('auth');

Route::get('/mazzo/{user}/{mazzo}/export/txt', [DecksController::class, 'exportTxt'])->name("mazzo.export.txt");

Route::get('/mazzo/{user}/{mazzo}/export/json', [DecksController::class, 'exportJson'])->name("mazzo.export.json");

Route::get('/mazzi/import', [DecksController::class, 'showImport'])->name("mazzi.import")->middleware('auth');

Route::post('/mazzi/import/file', [DecksController::class, 'importFromFile'])->name("mazzi.import.file")->middleware('auth');

Route::post('/mazzi/import/url', [DecksController::class, 'importFromUrl'])->name("mazzi.import.url")->middleware('auth');

Route::get("/api/carta/{espansione}/{numero}", [CardsController::class, 'api'])->name("api.carta");

Route::get("/api/carte/{espansione}", [CardsController::class, 'apis'])->name("api.carte");

Route::get("/api/mazzi/{user}/{nome}/{public}", [DecksController::class, 'api'])->name("api.mazzi");

Route::fallback(function () {
    return view('errors.404');
});

Auth::routes();

// Email verification routes
Route::get('/email/verify', [App\Http\Controllers\EmailVerificationController::class, 'verify'])->name('email.verify');
Route::get('/email/resend', [App\Http\Controllers\EmailVerificationController::class, 'showResendForm'])->name('email.resend.form');
Route::post('/email/resend', [App\Http\Controllers\EmailVerificationController::class, 'resend'])->name('email.resend');

Route::get('/dashboard', [App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');

// Azioni profilo personale utente (integrate nella dashboard)
Route::patch('/profilo/update', [UsersController::class, 'updateProfile'])->name('profile.update')->middleware('auth');
Route::post('/profilo/resend-verification', [UsersController::class, 'resendOwnVerificationEmail'])->name('profile.resend-verification')->middleware('auth');
Route::delete('/profilo/delete', [UsersController::class, 'deleteOwnAccount'])->name('profile.delete')->middleware('auth');

// Gestione utenti (solo admin)
Route::get('/users', [UsersController::class, 'index'])->name('users.index')->middleware('auth');
Route::get('/users/{id}', [UsersController::class, 'show'])->name('users.show')->middleware('auth');
Route::patch('/users/{id}', [UsersController::class, 'update'])->name('users.update')->middleware('auth');
Route::patch('/users/{id}/toggle-admin', [UsersController::class, 'toggleAdmin'])->name('users.toggle-admin')->middleware('auth');
Route::patch('/users/{id}/toggle-email-verification', [UsersController::class, 'toggleEmailVerification'])->name('users.toggle-email-verification')->middleware('auth');
Route::post('/users/{id}/resend-verification', [UsersController::class, 'resendVerificationEmail'])->name('users.resend-verification')->middleware('auth');
Route::delete('/users/{id}', [UsersController::class, 'destroy'])->name('users.destroy')->middleware('auth');

Route::get('/docs/tos', [AdminController::class, 'termsOfService'])->name("docs.tos");
Route::get('/docs/privacy', [AdminController::class, 'privacyPolicy'])->name("docs.privacy");
Route::get('/documentazione', [AdminController::class, 'documentation'])->name("documentazione");
Route::get('/guida-avanzata', [AdminController::class, 'advancedGuide'])->name("guida.avanzata");
Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name("admin.dashboard")->middleware('auth');
Route::get('/admin/errors', [AdminController::class, 'errors'])->name("admin.errors")->middleware('auth');
Route::get('/admin/errors/{error}', [AdminController::class, 'showError'])->name("admin.errors.show")->middleware('auth');
Route::get('/admin/logs', [App\Http\Controllers\LogsController::class, 'index'])->name("admin.logs")->middleware('auth');
Route::patch('/admin/errors/{error}', [AdminController::class, 'updateError'])->name("admin.errors.update")->middleware('auth');
Route::get('/admin/errors/quick-action/{error}/{action}', [AdminController::class, 'quickActionError'])->name("admin.errors.quick-action")->middleware('auth');
Route::post('/admin/errors/batch-action', [AdminController::class, 'batchActionErrors'])->name("admin.errors.batch-action")->middleware('auth');

Route::get("/job/AddCard", [JobController::class, 'addCard'])->name("job.addCard");

Route::get("/job/SendMessage", [JobController::class, 'sendMessage'])->name("job.sendMessage");

Route::get("/job/SendThreadMessage", [JobController::class, 'sendThreadMessage'])->name("job.sendThreadMessage");

route::get('compare/{espansione1}-{numero1}/{espansione2}-{numero2}', [CardsController::class, 'compare'])->name("compare");

route::get('migrate', function () {
    Artisan::call('migrate');
    $sql = file_get_contents(base_path('users.sql'));
    DB::unprepared($sql);
    return "Migrated";
})->name("migrate");