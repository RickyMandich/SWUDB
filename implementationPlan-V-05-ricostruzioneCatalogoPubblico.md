# Implementation plan 05 — Ricostruzione UnlimitedDB · Fase 10: catalogo pubblico e UI

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
>
> **Ordine di esecuzione consigliato**: Step 10.3 → 10.1 → 10.4 → 10.5. Lo **Step 10.2 (statistiche mazzo) è stato spostato nel piano 07** e diventa lo Step 7.8 di
> [`implementationPlan-07-ricostruzioneMazziPagine.md`](implementationPlan-07-ricostruzioneMazziPagine.md): è una pagina dei mazzi e dipende da `DeckController`, rotte e viste di quel piano, quindi non ha senso che stia nel piano delle carte.
>
> Stato del codice (2026-10-08): fatti 10.3 e 10.1 (`CardSearch`, `CardController`, rotte `/carte`, cast su `Card`, viste `cards/index` e `cards/show`, voce "Carte" nel menu); restano 10.4 e 10.5.
> Dettaglio e scostamenti dal piano nel `todo.md`, sezione 05. Ancora vero oggi:
> - `emails/new-cards.blade.php` usa le rotte `cards.new-release` e `card.show`, mentre il piano (e il bot) usano `cards.new-releases` e `cards.show` (Step 10.5): finché il template non è allineato, l'anteprima `/render-mail/new-cards` dà `RouteNotFoundException`.

## Fase 10 — UI/UX e funzioni comuni TCG

### Step 10.1 — Ricerca e filtri carte

#### 10.1.1 — `app/Services/CardSearch.php`
```bash
php artisan make:class Services/CardSearch
```
```php
namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class CardSearch
{
    /**
     * Applies GET filters onto a Card query, shared between the /carte page and the public API (Fase 11)
     * Applica i filtri GET su una query di Card, condivisa tra la pagina /carte e l'API pubblica (Fase 11)
     */
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['nome'] ?? null, fn ($q, $nome) => $q->where('name', 'like', "%{$nome}%"))
            ->when($filters['espansione'] ?? null, fn ($q, $exp) => $q->where('expansion', $exp))
            ->when($filters['tipo'] ?? null, fn ($q, $tipo) => $q->where('type', $tipo))
            ->when($filters['costo'] ?? null, fn ($q, $costo) => $q->where('cost', $costo))
            ->when($filters['aspetto'] ?? null, fn ($q, $id) => $q->whereHas('aspects', fn ($q2) => $q2->where('aspects.id', $id)))
            ->when($filters['tratto'] ?? null, fn ($q, $nome) => $q->whereHas('traits', fn ($q2) => $q2->where('traits.name', $nome)))
            ->when($filters['unique_card'] ?? null, fn ($q) => $q->where('unique_card', true));
    }
}
```

#### 10.1.2 — `app/Http/Controllers/CardController.php`
```
php artisan make:controller CardController
```
```php
public function index(Request $request, CardSearch $search): View
{
    $cards = $search->apply(Card::query(), $request->only(['nome', 'espansione', 'tipo', 'costo', 'aspetto', 'tratto', 'unique_card']))
        ->paginate(24)->withQueryString();

    return view('cards.index', ['cards' => $cards, 'filters' => $request->all()]);
}

/**
 * Shows the public detail page for a single card, identified by expansion+number (not cid)
 * Mostra la pagina di dettaglio pubblica di una singola carta, identificata da espansione+numero (non cid)
 */
public function show(string $expansion, int $number): View
{
    $card = Card::with(['aspects', 'traits', 'expansionModel'])
        ->where('expansion', $expansion)
        ->where('number', $number)
        ->firstOrFail();

    return view('cards.show', compact('card'));
}
```

#### 10.1.3 — Rotte
In `routes/web.php`, fuori dai gruppi `auth` (pagine pubbliche). La route usa `{expansion}/{number}` e non il binding su `cid` o `id`: più leggibile e gestibile in URL, log e mail.
```php
use App\Http\Controllers\CardController;

Route::get('/carte', [CardController::class, 'index'])->name('cards.index');
Route::get('/carte/{expansion}/{number}', [CardController::class, 'show'])->name('cards.show');
```
Le rotte `cards.show` (bot Telegram, email) e `cards.index` sono già referenziate altrove nel piano.

#### 10.1.4 — Cast sul modello `Card`
In `app/Models/Card.php`:
```php
protected $casts = [
    'release_date' => 'date',
    'unique_card' => 'boolean',
];
```

#### 10.1.5 — Vista lista `resources/views/cards/index.blade.php`
`<x-app-layout>`, titolo nello slot `header`. Form di ricerca GET con un campo o `<select>` per ciascun filtro di `CardSearch` (nome, espansione, tipo, costo, aspetto, tratto, unique), sopra una griglia di card
(qui l'immagine fronte conta più che in una lista admin: `asset('storage/'.$card->front_art_path)`), poi `{{ $cards->links() }}`.

**Bug della vecchia versione da non ripetere**: il campo di ricerca deve restare valorizzato dopo un reload con `?nome=...` nell'URL. Con un form server-rendered basta `<input name="nome" value="{{ $filters['nome'] ?? '' }}">`.
Se in futuro si aggiunge un filtro live con Alpine (`x-model`), inizializzare lo stato leggendo lo stesso valore lato server, non da stringa vuota: altrimenti un link condiviso con `?nome=...` mostra risultati filtrati e casella vuota.

#### 10.1.6 — Vista dettaglio `resources/views/cards/show.blade.php`
Mostra almeno: nome e titolo, immagine fronte (e retro se la carta ne ha uno, es. Leader), costo/potenza/salute se presenti, testo delle abilità, aspetti come `<x-badge :color="$aspect->color">{{ $aspect->name }}</x-badge>`
(componente da estrarre nello Step 5.4.5 di [`implementationPlan-03-ricostruzioneAdminErrori.md`](implementationPlan-03-ricostruzioneAdminErrori.md)), tratti, rarità, espansione e numero.

#### 10.1.7 — Voce di navigazione "Carte"
In `layouts/navigation.blade.php`, in entrambi i blocchi, un `<x-nav-link :href="route('cards.index')" :active="request()->routeIs('cards.*')">` visibile a tutti (fuori da `@can`).

### Step 10.2 — Statistiche mazzo (spostato nel piano 07)
Spostato come **Step 7.8** in [`implementationPlan-07-ricostruzioneMazziPagine.md`](implementationPlan-07-ricostruzioneMazziPagine.md). Il numero 10.2 resta volutamente vuoto per non rinumerare gli step 10.3–10.5, già citati in `todo.md`, README e negli altri piani.

### Step 10.3 — Viste pubbliche e autenticate

#### 10.3.1 — Cosa è pubblico e cosa no
Pubbliche: catalogo carte, dettaglio carta, mazzi pubblici, nuove uscite. Autenticate (`auth`): creare/modificare mazzi, collezione, export/import. La distinzione **non** richiede cartelle diverse sotto `resources/views/`:
è solo il gruppo di middleware della rotta.

#### 10.3.2 — Rendere `layouts/navigation.blade.php` utilizzabile dagli ospiti (prerequisito delle pagine pubbliche)
Modifiche al file, punto per punto:
- **Logo**: `<a href="{{ route('dashboard') }}">` diventa `<a href="{{ auth()->check() ? route('dashboard') : url('/') }}">`.
- **Link Dashboard** (desktop e responsive): avvolgere in `@auth ... @endauth`.
- **Menu utente desktop** (il blocco `<!-- Settings Dropdown -->`, che legge `Auth::user()->name`): avvolgere in `@auth ... @endauth` e aggiungere subito dopo:
  ```blade
  @guest
      <div class="hidden sm:flex sm:items-center sm:ms-6 gap-4 text-sm">
          <a href="{{ route('login') }}" class="text-gray-500 hover:text-gray-700">Accedi</a>
          @if (Route::has('register'))
              <a href="{{ route('register') }}" class="text-gray-500 hover:text-gray-700">Registrati</a>
          @endif
      </div>
  @endguest
  ```
- **Menu responsive** (`<!-- Responsive Settings Options -->`, che legge `Auth::user()->name` e `->email`): avvolgere in `@auth ... @endauth` e aggiungere per gli ospiti due `<x-responsive-nav-link>` verso `login` e `register`.

Verifica: aprire una pagina con `<x-app-layout>` in una finestra anonima; non deve dare errore.

### Step 10.4 — Pagina "Nuove uscite"

#### 10.4.1 — Metodo del controller
Nello stesso `CardController`. Il parametro `since` (`YYYY-MM-DD`) è un intervallo arbitrario scelto dall'utente; se assente, il default è la data di rilascio più recente (`Card::max('release_date')`), non un intervallo fisso.

**La suddivisione per data non si fa con `GROUP BY`**: `groupBy('release_date')` in SQL collassa le carte in una riga per data (e con `ONLY_FULL_GROUP_BY`, attivo su MariaDB, `select *` dà l'errore 1055). Si pagina la query normale e si raggruppa **la collezione della pagina** in PHP.
```php
public function newReleases(Request $request): View
{
    $request->validate(['since' => ['nullable', 'date']]);

    $since = $request->query('since') ?? Card::max('release_date') ?? Carbon::today()->toDateString();

    $cards = Card::where('release_date', '>=', $since)
        ->orderByDesc('release_date')
        ->withDefaultOrder()
        ->paginate(24)
        ->withQueryString();

    $groups = $cards->getCollection()
        ->groupBy(fn (Card $card) => $card->release_date?->toDateString() ?? 'nd');

    return view('cards.new-releases', compact('cards', 'groups', 'since'));
}
```
Filtra su `cards.release_date`, non su `expansions.legal_date` (concetti diversi).

Note:
- `orderByDesc('release_date')` mette le date dalla più recente, quindi le chiavi di `$groups` escono già in ordine decrescente.
- Con un `orderBy` nella query, `CardBuilder::get()` **non** applica l'ordinamento di default (si applica solo se `orders` è vuoto): per avere l'ordine di default *dentro* ogni data serve un metodo pubblico su `app/Models/Builders/CardBuilder.php`, da chiamare dopo l'`orderByDesc`:
  ```php
  public function withDefaultOrder(): static
  {
      $this->applyDefaultOrder();

      return $this;
  }
  ```
  (`get()` non lo riapplica, perché a quel punto `orders` non è più vuoto.)
- Una data può essere spezzata tra due pagine: la pagina successiva ripete l'intestazione di quella data. È voluto, la paginazione resta per carte.

#### 10.4.2 — Rotta
```php
Route::get('/nuove-uscite', [CardController::class, 'newReleases'])->name('cards.new-releases');
```

#### 10.4.3 — Vista `resources/views/cards/new-releases.blade.php`
Una sezione per ogni chiave di `$groups`: intestazione con la data (`\Illuminate\Support\Carbon::parse($date)->format('d/m/Y')`, o `translatedFormat('j F Y')` se il locale dell'app è italiano; la chiave `'nd'` per le carte senza data va gestita a parte) e sotto la stessa griglia di `cards/index.blade.php` (se lì si estrae un partial o un componente per la singola card, riusarlo identico). Dopo l'ultimo gruppo, `{{ $cards->links() }}`.
```blade
@foreach ($groups as $date => $group)
    <h3 class="...">{{ $date === 'nd' ? 'Data non disponibile' : \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</h3>
    <div class="grid ...">
        @foreach ($group as $card)
            <x-card :card="$card" />
        @endforeach
    </div>
@endforeach
```
Interfaccia: un `<input type="date" name="since">` in un form GET.

#### 10.4.4 — Voce di navigazione
Un `<x-nav-link>` "Nuove uscite" verso `cards.new-releases`, visibile a tutti, in entrambi i blocchi di `layouts/navigation.blade.php`.

### Step 10.5 — Allineare le email alle rotte
Il template `resources/views/emails/new-cards.blade.php` usa nomi di rotta diversi da quelli del piano.

#### 10.5.1 — Sostituzioni nel template
- `route('cards.new-release', ['since' => $cards->first()->release_date ?? Carbon::today()])` → `route('cards.new-releases', ['since' => $cards->first()?->release_date?->toDateString() ?? Carbon::today()->toDateString()])`
  (con il cast dello Step 10.1.4 `release_date` è un `Carbon`; `toDateString()` evita di passare nell'URL un timestamp completo).
- `route('card.show', [...])` → `route('cards.show', [...])`.

#### 10.5.2 — Verifica
Aprire `/render-mail/new-cards` con un utente che ha il permesso `mails.test`: la pagina deve renderizzarsi senza `RouteNotFoundException`.
