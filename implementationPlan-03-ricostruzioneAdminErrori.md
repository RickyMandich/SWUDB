# Implementation plan 03 — Ricostruzione UnlimitedDB · Fase 5: log errori scan (rifiniture e correzioni)

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> Controller, rotte, permesso e prima stesura delle viste sono già nel codice; restano le correzioni alle viste e alle email di scan.
> Verificato riga per riga su `admin/errors/*`, `admin/users/*`, `components/`, `layouts/navigation.blade.php` e `ImportCardsFromSwuApiJob`.

## Fase 5 — Log errori scan (`system_errors`)

### Step 5.1 — Migration e modello ✅
Tabella `system_errors` e modello `SystemError` (costanti `STATUS_OPEN`/`STATUS_IGNORED`/`STATUS_RESOLVED`, cast `context` → array, `resolved_at` → datetime).

### Step 5.2 — Permesso `system.manage-errors` ✅
Presente in `PermissionSeeder` e assegnato al ruolo `admin`.

### Step 5.3 — Pagina admin `/admin/errori`

#### 5.3.1 — Controller ✅
`App\Http\Controllers\Admin\SystemErrorController` con `index`, `show`, `update`, `bulkUpdate`. Differenze volute rispetto alla prima bozza del piano: `resolved_at` si valorizza
**solo** quando lo stato diventa `resolved` (un errore ignorato non è "risolto", quindi resta `NULL`), e `bulkUpdate` imposta anche `status_level = success` per il flash message.

#### 5.3.2 — Rotte ✅
Gruppo `auth` + `verified` + `permission:system.manage-errors`, prefisso `admin`. **Ordine da rispettare**: `PATCH /errori/bulk` va registrata **prima** di `PATCH /errori/{systemError}`,
altrimenti "bulk" verrebbe interpretato come id del modello. Nel codice l'ordine è già quello giusto.

#### 5.3.3 — Vista lista: funziona ma è grezza
`admin/errors/index.blade.php` esiste e funziona; i problemi sono elencati negli Step 5.4.x.

#### 5.3.4 — Vista dettaglio: esiste in versione grezza
`admin/errors/show.blade.php` **non è vuota** (README e `todo.md` la davano per vuota): contiene la prima stesura, da rifinire con lo Step 5.4.10.

### Step 5.4 — Correzioni alle view già scritte
Ordine consigliato: prima i bug (5.4.3, 5.4.8, 5.4.9), poi i componenti (5.4.1, 5.4.5), poi lo stile (5.4.2, 5.4.6, 5.4.10, 5.4.11, 5.4.12), infine 5.4.13.
Stato di ogni punto: 5.4.4 e 5.4.7 sono già fatti, gli altri sono da fare.

#### 5.4.1 — Rinominare e sistemare `<x-flash-message>`
Il componente esiste ma il file si chiama **`flash-massage.blade.php`** (refuso), quindi si usa come `<x-flash-massage />`. Rinominarlo (`git mv resources/views/components/flash-massage.blade.php resources/views/components/flash-message.blade.php`)
e sostituire il contenuto: oggi il testo non ha padding né colore, quindi su sfondo colorato è illeggibile.
```blade
{{-- resources/views/components/flash-message.blade.php --}}
@if (session('status'))
    @php
        $color = match (session('status_level')) {
            'success' => 'bg-green-600',
            'error' => 'bg-red-600',
            'warning' => 'bg-yellow-600',
            default => 'bg-blue-600',
        };
    @endphp
    <div {{ $attributes->merge(['class' => "mb-4 px-4 py-2 rounded text-white {$color}"]) }}>
        {{ session('status') }}
    </div>
@endif
```
Poi sostituire `<x-flash-massage />` con `<x-flash-message />` in `admin/users/index.blade.php` e `admin/errors/index.blade.php`. Aggiungere `->with('status_level', 'success')` anche in
`SystemErrorController::update()` e in `UserManagementController::update()` (oggi solo `bulkUpdate()` lo imposta, gli altri mostrano il flash in blu).

#### 5.4.2 — Titolo nello slot `header`
`admin/users/index`, `admin/users/edit` e `admin/errors/show` hanno l'`<h1>` nel corpo; `admin/errors/index` non ha titolo. Spostarlo nello slot, come in `dashboard.blade.php`:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestione utenti</h2>
    </x-slot>
    <div class="max-w-4xl mx-auto py-6">
        ...
```
Titoli: "Gestione utenti", "Modifica {{ $user->name }}", "Log errori scan", "Errore #{{ $systemError->id }}". Si usa `h2` (non `h1`) come nella dashboard di Breeze. Il pezzo di 5.4.8 e 5.4.10 già lo include.

#### 5.4.3 — Bug: `$error->stack` non esiste
In `admin/errors/index.blade.php` il blocco `<pre>{{ $error->stack }}</pre>` legge una colonna inesistente (si chiama `stack_trace`), quindi resta sempre vuoto. Corretto, l'intero stack trace comparirebbe in ogni riga aperta:
toglierlo dalla lista (c'è già la pagina di dettaglio). Il rifacimento completo della lista è nello Step 5.4.8.

#### 5.4.4 — Link a `errors.show` ✅
Ogni riga ha già il pulsante "Mostra" verso `route('admin.errors.show', $error)`.

#### 5.4.5 — Componente `<x-badge>` per lo stato
Serve identico per gli aspetti carta (Step 10.1): un solo componente.
```blade
{{-- resources/views/components/badge.blade.php --}}
@props(['color' => '#9ca3af'])
<span {{ $attributes->merge(['class' => 'inline-block px-2 py-0.5 rounded text-xs text-white']) }}
    style="background-color: {{ $color }}">
    {{ $slot }}
</span>
```
Il colore per stato sta in un metodo del modello, così lista e dettaglio non duplicano il `match`. In `app/Models/SystemError.php`:
```php
public function statusColor(): string
{
    return match ($this->status) {
        self::STATUS_OPEN => '#ef4444',
        self::STATUS_RESOLVED => '#22c55e',
        default => '#6b7280',
    };
}
```

#### 5.4.6 — Pulsanti
I pulsanti colorati a mano della lista (verde/grigio/rosso) possono restare per mantenere la distinzione risolto/ignora/riapri. Negli altri form usare i componenti Breeze:
in `admin/users/edit.blade.php` sostituire `<button type="submit" class="mt-4">Salva</button>` con `<x-primary-button class="mt-4">Salva</x-primary-button>`.

#### 5.4.7 — Voci di navigazione responsive ✅
"Gestione utenti" e "Gestione errori" sono già presenti sia nel blocco desktop sia in `<!-- Responsive Navigation Menu -->` di `layouts/navigation.blade.php`.

#### 5.4.8 — Bug: la variabile `$errors` sovrascrive quella di Laravel
`SystemErrorController::index()` passa `compact('errors', 'status')`; in ogni vista Blade `$errors` è già il `ViewErrorBag` della validazione, e qui viene sostituito dal paginator.
Conseguenza: premendo "Risolvi selezionati" senza spuntare nulla la validazione fallisce e la vista non può mostrare l'errore. Correzione:

##### 5.4.8.1 — Controller
```php
$systemErrors = SystemError::when($status, fn ($q) => $q->where('status', $status))
    ->latest()
    ->paginate(30)
    ->withQueryString();

return view('admin.errors.index', compact('systemErrors', 'status'));
```

##### 5.4.8.2 — Vista lista completa (`resources/views/admin/errors/index.blade.php`)
Questa versione applica in un colpo solo 5.4.1, 5.4.2, 5.4.3, 5.4.5, 5.4.8, 5.4.9 e 5.4.11:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Log errori scan</h2>
    </x-slot>

    <div class="max-w-5xl mx-auto py-6 px-4">
        <x-flash-message />

        <form method="GET" action="{{ route('admin.errors.index') }}" class="mb-4">
            <select name="status" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm">
                <option value="">Tutti</option>
                <option value="open" @selected($status === 'open')>Aperti</option>
                <option value="resolved" @selected($status === 'resolved')>Risolti</option>
                <option value="ignored" @selected($status === 'ignored')>Ignorati</option>
            </select>
        </form>

        @if ($systemErrors->isEmpty())
            <div class="p-6 text-gray-500">Nessun errore.</div>
        @else
            <form method="POST" action="{{ route('admin.errors.bulk-update') }}">
                @csrf
                @method('PATCH')
                <x-input-error :messages="$errors->get('ids')" class="mb-2" />

                @foreach ($systemErrors as $error)
                    <div class="bg-white border border-gray-200 p-4 mb-3 rounded">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" name="ids[]" value="{{ $error->id }}"
                                class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <x-badge :color="$error->statusColor()">{{ $error->status }}</x-badge>
                                    <span class="font-semibold">{{ $error->source }}</span>
                                </div>
                                <p class="mt-1">{{ $error->message }}</p>

                                <div class="flex flex-wrap gap-2 mt-3">
                                    @if ($error->status === 'open')
                                        <button type="submit" form="resolve-{{ $error->id }}"
                                            class="px-3 py-1 bg-green-600 text-white rounded">Segna come risolto</button>
                                        <button type="submit" form="ignore-{{ $error->id }}"
                                            class="px-3 py-1 bg-gray-600 text-white rounded">Ignora</button>
                                    @else
                                        <button type="submit" form="reopen-{{ $error->id }}"
                                            class="px-3 py-1 bg-red-600 text-white rounded">Riapri</button>
                                    @endif
                                    <a href="{{ route('admin.errors.show', $error) }}"
                                        class="px-3 py-1 bg-blue-600 text-white rounded">Mostra</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="flex flex-wrap gap-2 mt-4">
                    <button type="submit" name="status" value="resolved"
                        class="px-3 py-1 bg-green-600 text-white rounded">Risolvi selezionati</button>
                    <button type="submit" name="status" value="ignored"
                        class="px-3 py-1 bg-gray-600 text-white rounded">Ignora selezionati</button>
                    <button type="submit" name="status" value="open"
                        class="px-3 py-1 bg-red-600 text-white rounded">Riapri selezionati</button>
                </div>
            </form>

            {{ $systemErrors->links() }}

            {{-- Form esterni per le azioni riga-per-riga (un <form> non può stare dentro un altro) --}}
            @foreach ($systemErrors as $error)
                @foreach (['resolve' => 'resolved', 'ignore' => 'ignored', 'reopen' => 'open'] as $prefix => $newStatus)
                    <form id="{{ $prefix }}-{{ $error->id }}" method="POST"
                        action="{{ route('admin.errors.update', $error) }}" class="hidden">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $newStatus }}">
                    </form>
                @endforeach
            @endforeach
        @endif
    </div>
</x-app-layout>
```

#### 5.4.9 — Paginazione mancante
Coperto dallo Step 5.4.8.2: `{{ $systemErrors->links() }}` va **fuori** dal `<form>` del bulk.

#### 5.4.10 — Rifare la vista dettaglio (`resources/views/admin/errors/show.blade.php`)
Problemi della prima stesura: commento Blade copiato dal piano, classi `dark:*` (il resto dell'app non ha il dark mode), etichette in inglese, nessun `source`/`status`/date, `@if` dentro i `<pre>` che aggiungono a capo,
`json_encode` senza flag (accenti e `/` escapati), contenitore diverso dalle altre pagine admin. Sostituire l'intero file:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Errore #{{ $systemError->id }}</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto py-6 px-4">
        <x-flash-message />
        <a href="{{ route('admin.errors.index') }}" class="text-sm text-indigo-600 hover:underline">← Torna alla lista</a>

        <div class="bg-white shadow-sm rounded-lg p-6 my-4 text-gray-700">
            <dl class="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-2">
                <dt class="font-semibold">Sorgente</dt>
                <dd>{{ $systemError->source }}</dd>
                <dt class="font-semibold">Stato</dt>
                <dd><x-badge :color="$systemError->statusColor()">{{ $systemError->status }}</x-badge></dd>
                <dt class="font-semibold">Creato il</dt>
                <dd>{{ $systemError->created_at?->format('d/m/Y H:i') }}</dd>
                <dt class="font-semibold">Risolto il</dt>
                <dd>{{ $systemError->resolved_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                <dt class="font-semibold">Messaggio</dt>
                <dd>{{ $systemError->message }}</dd>
            </dl>

            <div class="flex flex-wrap gap-2 mt-4">
                @foreach (['resolved' => 'Segna come risolto', 'ignored' => 'Ignora', 'open' => 'Riapri'] as $newStatus => $label)
                    @if ($systemError->status !== $newStatus)
                        <form method="POST" action="{{ route('admin.errors.update', $systemError) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $newStatus }}">
                            <x-secondary-button type="submit">{{ $label }}</x-secondary-button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg p-6 mb-6 text-gray-700">
            <h3 class="text-lg font-semibold mb-2">Stack trace</h3>
            <pre class="bg-gray-100 p-4 rounded text-sm overflow-x-auto">{{ $systemError->stack_trace ?? 'Nessuno stack trace per questo errore.' }}</pre>
        </div>

        <div class="bg-white shadow-sm rounded-lg p-6 text-gray-700">
            <h3 class="text-lg font-semibold mb-2">Contesto</h3>
            <pre class="bg-gray-100 p-4 rounded text-sm overflow-x-auto">{{ $systemError->context ? json_encode($systemError->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'Nessun contesto salvato per questo errore.' }}</pre>
        </div>
    </div>
</x-app-layout>
```
Il `update()` del controller fa `back()`, quindi dopo il cambio di stato si resta sulla pagina.

#### 5.4.11 — Pulsanti di stato contestuali
Coperto dallo Step 5.4.8.2: un errore aperto mostra "Segna come risolto" e "Ignora", uno risolto o ignorato mostra solo "Riapri". Anche la pagina di dettaglio (5.4.10) nasconde la transizione verso lo stato corrente.

#### 5.4.12 — Tabelle e stile di `admin/users/*`

##### 5.4.12.1 — `admin/users/index.blade.php`
Avvolgere la tabella in `<div class="overflow-x-auto">` e dare a `<th>`/`<td>` padding e bordo (`px-3 py-2 border-b`). Fare lo stesso su ogni tabella admin futura (Fase 6 inclusa), non solo qui.

##### 5.4.12.2 — `admin/users/edit.blade.php`
Checkbox con le classi Breeze `rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500`, link "Annulla" verso `route('admin.users.index')` accanto al pulsante Salva,
e sotto ogni gruppo `<x-input-error :messages="$errors->get('roles')" class="mt-2" />` / `$errors->get('permissions')` (il controller valida ma la vista non mostra gli errori).

#### 5.4.13 — Evitare l'auto-lockout in `UserManagementController::update`
Se un admin toglie a se stesso il ruolo `admin`, nessuno può più aprire `/admin/utenti`. Prima dei `sync`:
```php
if ($user->is($request->user()) && ! in_array('admin', $validated['roles'] ?? [], true)) {
    return back()->withErrors(['roles' => 'Non puoi togliere a te stesso il ruolo admin.']);
}
```
L'errore è visibile grazie all'`<x-input-error>` dello Step 5.4.12.2.

### Step 5.5 — Correzioni alle email di scan
Trovato leggendo `ImportCardsFromSwuApiJob` insieme ai template: l'email agli admin si rompe proprio sul caso più comune ("carta già presente").

#### 5.5.1 — Bug: `context['error']` non esiste per gli errori "già presente"
`resources/views/emails/admin-scan-report.blade.php` legge `$error->context['error']`, ma per i `SystemError` "Carta {cid} gia' presente" il job salva `context = ['card' => $card]`, senza chiave `error`:
`Undefined array key "error"` mentre la mail viene renderizzata dal worker, e la mail non parte. Sostituire nel template `{{ $error->context['error'] }}` con `{{ $error->context['error'] ?? '—' }}`.

#### 5.5.2 — Bug: l'eccezione nel `context` diventa `{}`
Nel `catch` di `processCard()` il job salva `'context' => ['raw' => $cardData, 'error' => $e]`. Un oggetto `Throwable` serializzato in JSON non ha proprietà pubbliche: nel database finisce `{}`, quindi il dettaglio dell'errore va perso.
Salvare il messaggio:
```php
'context' => ['raw' => $cardData, 'error' => $e->getMessage()],
```

#### 5.5.3 — Risoluzione: volume degli errori "già presente" (Opzione A adottata)
**Decisione confermata: Opzione (a).**
Ad ogni scan periodico, le carte già presenti nel database non devono essere salvate come `SystemError` (evitando di intasare la tabella `system_errors` con migliaia di righe a stato `ignored` e di gonfiare inutilmente il report email agli admin).
- In `app/Jobs/ImportCardsFromSwuApiJob.php`:
  - Rimuovere la chiamata a `SystemError::create(...)` nel ramo `else` di `! $existed`.
  - Gestire una Collection in memoria (es. `$existingCards = collect()`) in cui inserire per ogni carta già presente una struttura con espansione e numero:
    ```php
    $existingCards->push([
        'expansion' => $card->expansion,
        'number' => $card->number,
    ]);
    ```
    In questo modo si ha a disposizione sia il totale (`$existingCards->count()`), sia l'elenco esatto di quali carte erano già presenti nel DB.
  - Riportare il numero di carte già presenti/aggiornate nel log di processo e nel messaggio riepilogativo di Telegram (`"Scan completato: X carte lette, Y nuove, Z già presenti/aggiornate, W errori"`), con la possibilità di ispezionare o loggare la collection se necessario.
  - La tabella `system_errors` e l'invio dell'email admin vengono attivati esclusivamente in caso di veri errori o eccezioni (nel blocco `catch`).


#### 5.5.4 — Test
In `tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php` aggiungere un test che esegue lo scan con una carta già presente e verifica che il report si renderizzi:
```php
it('renderizza il report admin anche con errori senza chiave error nel contesto', function () {
    $error = SystemError::create([
        'source' => 'test',
        'message' => "Carta X gia' presente, dati aggiornati",
        'status' => SystemError::STATUS_IGNORED,
        'context' => ['card' => ['name' => 'X']],
    ]);

    $html = (new AdminScanReportEmail(collect([$error])))->render();

    expect($html)->toContain('Carta X');
});
```
Serve `use App\Mail\AdminScanReportEmail;` (già importato nel file di test) e le rotte `admin.errors.show` (già esistenti).
