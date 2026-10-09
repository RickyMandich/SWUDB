<?php

use App\Http\Controllers\Admin\ExpansionController;
use App\Http\Controllers\Admin\SystemErrorController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\DeckController;
use App\Http\Controllers\ProfileController;
use App\Mail\AdminScanReportEmail;
use App\Mail\NewCardsEmail;
use App\Models\SystemError;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
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
    ->group(function () {
        Route::get('/utenti', 'index')->name('users.index');
        Route::get('/utenti/{user}/modifica', 'edit')->name('users.edit');
        Route::put('/utenti/{user}', 'update')->name('users.update');
    });

Route::middleware(['auth', 'verified', 'permission:system.manage-errors'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/errori', [SystemErrorController::class, 'index'])->name('errors.index');
        Route::get('/errori/{systemError}', [SystemErrorController::class, 'show'])->name('errors.show');
        Route::patch('/errori/bulk', [SystemErrorController::class, 'bulkUpdate'])->name('errors.bulk-update');
        Route::patch('/errori/{systemError}', [SystemErrorController::class, 'update'])->name('errors.update');
    });

Route::middleware(['auth', 'verified', 'permission:mails.test'])->get('/render-mail/{type}', function (string $type) {
    $errors = collect();
    $errors->push(
        (new SystemError)->forceFill([
            'id' => 1,
            'message' => 'Errore di test',
            'context' => ['error_message' => 'Errore di test'],
        ])
    );

    return match ($type) {
        'new-cards' => new NewCardsEmail(collect()),
        'admin-scan-report' => new AdminScanReportEmail($errors),
        default => null,
    };
});

Route::middleware(['auth', 'verified', 'permission:expansions.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->controller(ExpansionController::class)
    ->group(function () {
        Route::get('/espansioni', 'index')->name('expansions.index');
        Route::put('/espansioni/{expansion}', 'update')->name('expansions.update');
    });

Route::get('/carte', [CardController::class, 'index'])->name('cards.index');
Route::get('/carte/{expansion}/{number}', [CardController::class, 'show'])->name('cards.show');
Route::get('/nuove-uscite', [CardController::class, 'newReleases'])->name('cards.new-releases');

Route::prefix('/mazzi')->group(function () {
    // Creazione e lista mazzi
    Route::get('/', [DeckController::class, 'index'])->name('decks.index'); // pubblica: mazzi pubblici + propri se loggato
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/crea', [DeckController::class, 'create'])->name('decks.create');
        Route::post('/', [DeckController::class, 'store'])->name('decks.store');
    });
});

// Modifica mazzo e azioni di deck-building (solo proprietario)
Route::middleware(['auth', 'verified'])->prefix('mazzo/modifica/{username}/{deckname}')->group(function () {
    Route::get('/', [DeckController::class, 'edit'])->name('decks.edit');
    Route::put('/carte', [DeckController::class, 'syncCards'])->name('decks.sync-cards'); // Flusso principale batch: aggiunge, aggiorna e rimuove in un'unica operazione
    Route::post('/carte', [DeckController::class, 'addCard'])->name('decks.add-card'); // Singola aggiunta (fallback)
    Route::delete('/carte/{card}', [DeckController::class, 'removeCard'])->name('decks.remove-card'); // Singola rimozione (fallback)
    Route::patch('/assembla', [DeckController::class, 'toggleAssembled'])->name('decks.toggle-assembled');
    Route::post('/versione', [DeckController::class, 'createVersion'])->name('decks.create-version');
});

// Visualizzazione mazzo (pubblica se is_public, oppure proprietario).
// Registrata DOPO il gruppo `mazzo/modifica/...` e con `{version?}` numerico: così `/mazzo/modifica/alice/2` (mazzo "2" di alice) non viene scambiato per il mazzo "alice" dell'utente "modifica".
Route::get('/mazzo/{username}/{deckname}/versioni', [DeckController::class, 'versions'])->name('decks.versions');
Route::get('/mazzo/{username}/{deckname}/{version?}', [DeckController::class, 'show'])->whereNumber('version')->name('decks.show');
