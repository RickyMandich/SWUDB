<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\ProfileController;
use App\Mail\AdminScanReportEmail;
use App\Mail\NewCardsEmail;
use App\Models\SystemError;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware(['auth', 'verified', 'permission:users.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->controller(UserManagementController::class)
    ->group(function (){
        Route::get('/utenti', 'index')->name('users.index');
        Route::get('/utenti/{user}/modifica', 'edit')->name('users.edit');
        Route::put('/utenti/{user}', 'update')->name('users.update');
    });

Route::middleware(['auth', 'verified', 'permission:mails.test'])->get('/render-mail/{type}', function (string $type){
    $errors = collect();
    $errors->push(
        new SystemError([
            'message' => 'Errore di test',
            'context' => ['error' => 'Errore di test'],
        ])
    );
    return match ($type) {
        'new-cards' => new NewCardsEmail(collect()),
        'admin-scan-report' => new AdminScanReportEmail($errors),
        default => null,
    };
});