# Implementation plan 04 — Ricostruzione UnlimitedDB · Fase 6: admin espansioni e rotazioni

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> Dalla vecchia versione: "creare pagina admin di gestione espansioni e rotazioni". Serve perché `rotation`, `legal_date`, `confirmed` e `group_main_expansion` sono dati
> curati a mano da un admin (il job di import crea le espansioni nuove con valori approssimativi, `confirmed = false`).
>
> Prerequisito consigliato: aver applicato [`implementationPlan-03-ricostruzioneAdminErrori.md`](implementationPlan-03-ricostruzioneAdminErrori.md), Step 5.4.1 e 5.4.5,
> perché le viste di questa fase usano `<x-flash-message />` (oggi il file si chiama ancora `flash-massage`).

## Fase 6 — Admin espansioni

### Step 6.1 — Permesso `expansions.manage`

#### 6.1.1 — Seeder
In `database/seeders/PermissionSeeder.php` aggiungere `'expansions.manage'` all'array `$permissions` (oggi contiene 8 permessi e non questo). Il ruolo `admin` lo riceve dalla riga `$admin->givePermissionTo($permissions);` già presente.

#### 6.1.2 — Applicare
```bash
docker compose -f docker-compose.dev.yml exec app php artisan db:seed --class=PermissionSeeder
```

### Step 6.2 — Pagina `/admin/espansioni`
A differenza delle pagine utenti/errori (lista + pagina di modifica separata) qui basta una **riga con form inline per espansione**: sono poche decine e i campi sono 3 più una checkbox.

#### 6.2.1 — Cast sul modello `Expansion`
`app/Models/Expansion.php` non ha `$casts`: `legal_date` arriva come stringa e `confirmed` come intero, quindi `$expansion->legal_date?->format(...)` darebbe errore. Aggiungere:
```php
protected $casts = [
    'legal_date' => 'date',
    'confirmed' => 'boolean',
];
```

#### 6.2.2 — Controller
```
php artisan make:controller Admin/ExpansionController
```
```php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expansion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpansionController extends Controller
{
    /**
     * Lists every expansion, tokens included (T* codes are separate rows on purpose)
     * Elenca tutte le espansioni, token inclusi (i codici T* sono righe a sé, volutamente)
     */
    public function index(): View
    {
        $expansions = Expansion::orderBy('expansion')->get();

        return view('admin.expansions.index', compact('expansions'));
    }

    /**
     * Updates the manually curated fields of one expansion
     * Aggiorna i campi curati a mano di una singola espansione
     */
    public function update(Request $request, Expansion $expansion): RedirectResponse
    {
        $validated = $request->validate([
            'legal_date' => ['nullable', 'date'],
            'rotation' => ['required', 'string', 'max:1'],
            'group_main_expansion' => ['nullable', 'string', 'exists:expansions,expansion'],
        ]);
        $validated['confirmed'] = $request->boolean('confirmed'); // una checkbox non spuntata non arriva nel payload

        $expansion->update($validated);

        return back()
            ->with('status', "Espansione {$expansion->expansion} aggiornata.")
            ->with('status_level', 'success');
    }
}
```
`exists:expansions,expansion` blocca un `group_main_expansion` che non punta a un'espansione reale con un messaggio chiaro, invece di un errore SQL sulla FK.
La lunghezza massima di `rotation` (1 carattere) rispecchia la colonna `string(1)`.

#### 6.2.3 — Rotte
In `routes/web.php`, dopo i gruppi già presenti:
```php
use App\Http\Controllers\Admin\ExpansionController;

Route::middleware(['auth', 'verified', 'permission:expansions.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->controller(ExpansionController::class)
    ->group(function () {
        Route::get('/espansioni', 'index')->name('expansions.index');
        Route::put('/espansioni/{expansion}', 'update')->name('expansions.update');
    });
```
`{expansion}` fa route-model-binding sulla colonna `expansion` senza altro: `Expansion::$primaryKey = 'expansion'` è già impostato.

#### 6.2.4 — Vista `resources/views/admin/expansions/index.blade.php`
Scritta seguendo le "Convenzioni per le view" dell'indice (titolo nello slot `header`, flash message estratto, tabella scrollabile). Per avere markup valido i `<form>` stanno **fuori** dalla tabella
e gli input si collegano con l'attributo `form=`:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestione espansioni</h2>
    </x-slot>

    <div class="max-w-5xl mx-auto py-6 px-4">
        <x-flash-message />

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th class="px-3 py-2 border-b">Codice</th>
                        <th class="px-3 py-2 border-b">Legal date</th>
                        <th class="px-3 py-2 border-b">Rotation</th>
                        <th class="px-3 py-2 border-b">Gruppo</th>
                        <th class="px-3 py-2 border-b">Confermata</th>
                        <th class="px-3 py-2 border-b"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($expansions as $expansion)
                        <tr>
                            <td class="px-3 py-2 border-b">{{ $expansion->expansion }}</td>
                            <td class="px-3 py-2 border-b">
                                <x-text-input type="date" name="legal_date" form="exp-{{ $expansion->expansion }}"
                                    :value="$expansion->legal_date?->format('Y-m-d')" />
                            </td>
                            <td class="px-3 py-2 border-b">
                                <x-text-input type="text" name="rotation" form="exp-{{ $expansion->expansion }}"
                                    :value="$expansion->rotation" maxlength="1" class="w-14" />
                            </td>
                            <td class="px-3 py-2 border-b">
                                <select name="group_main_expansion" form="exp-{{ $expansion->expansion }}"
                                    class="rounded-md border-gray-300 shadow-sm">
                                    <option value="">—</option>
                                    @foreach ($expansions as $option)
                                        <option value="{{ $option->expansion }}"
                                            @selected($expansion->group_main_expansion === $option->expansion)>
                                            {{ $option->expansion }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2 border-b">
                                <input type="checkbox" name="confirmed" value="1" form="exp-{{ $expansion->expansion }}"
                                    @checked($expansion->confirmed)
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            </td>
                            <td class="px-3 py-2 border-b">
                                <x-primary-button form="exp-{{ $expansion->expansion }}">Salva</x-primary-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @foreach ($expansions as $expansion)
            <form id="exp-{{ $expansion->expansion }}" method="POST"
                action="{{ route('admin.expansions.update', $expansion) }}" class="hidden">
                @csrf
                @method('PUT')
            </form>
        @endforeach
    </div>
</x-app-layout>
```
Per mostrare gli errori di validazione della riga cambiata, aggiungere sotto la tabella `<x-input-error :messages="$errors->all()" class="mt-2" />`.

### Step 6.3 — Voce di navigazione
In `resources/views/layouts/navigation.blade.php`, **in entrambi i blocchi** (desktop e `<!-- Responsive Navigation Menu -->`), dopo "Gestione errori":
```blade
@can('expansions.manage')
    <x-nav-link :href="route('admin.expansions.index')" :active="request()->routeIs('admin.expansions.*')">
        {{ __('Gestione espansioni') }}
    </x-nav-link>
@endcan
```
Nel blocco responsive usare `<x-responsive-nav-link>` al posto di `<x-nav-link>`.

### Step 6.4 — Verifica
Con un utente admin: aprire `/admin/espansioni`, cambiare `rotation` di una riga e salvare (flash verde "Espansione XXX aggiornata."), poi provare un codice gruppo inesistente e verificare l'errore di validazione.
