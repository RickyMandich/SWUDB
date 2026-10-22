# Implementation Plan: Update `fakeCardEntry` to read from `api-example-result.json`

## Obiettivo

Sostituire l'array hardcoded nella funzione `fakeCardEntry()` in
`tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php` con dati reali
letti da `Storage::disk('local')->get('api-example-result.json')`.

> **Nota:** Il disco `private` non è configurato come tale in `filesystems.php`,
> ma il disco `local` ha root `storage/app/private/` (configurazione Laravel di default).
> Il file si trova in `storage/app/private/api-example-result.json`,
> quindi si usa `Storage::disk('local')`.

---

## Analisi del file JSON

Il file `api-example-result.json` ha questa struttura di primo livello:

```json
{
  "https://admin.starwarsunlimited.com/api/card-list?...": {
    "data": [ { "id": 7, "attributes": { ... } }, ... ],
    "meta": { ... }
  }
}
```

Il **primo elemento** di `data` è Luke Skywalker (id: 7, cardUid: `2579145458`),
identico a quello usato attualmente nel fixture — quindi la sostituzione è compatibile
con i test esistenti.

---

## Modifica da apportare

**File:** `tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php`

### Prima (righe 16–42)

```php
function fakeCardEntry(array $overrides = []): array
{

    return array_replace_recursive([
        'id' => 7,
        'attributes' => [
            'cardUid' => '2579145458',
            'cardNumber' => 5,
            'title' => 'Luke Skywalker',
            'subtitle' => 'Amico Fidato',
            'unique' => true,
            'cost' => 6,
            'hp' => 7,
            'power' => 4,
            'text' => 'Testo di prova',
            'artist' => 'Borja Pindado',
            'type' => ['data' => ['attributes' => ['name' => 'Leader', 'value' => 'Leader']]],
            'rarity' => ['data' => ['attributes' => ['name' => 'Speciale', 'englishName' => 'Special']]],
            'expansion' => ['data' => ['attributes' => ['code' => 'SOR', 'name' => 'Scintilla di Ribellione']]],
            'arenas' => ['data' => [['attributes' => ['name' => 'Terrestre']]]],
            'traits' => ['data' => [['attributes' => ['name' => 'Forza']], ['attributes' => ['name' => 'Ribelle']]]],
            'aspects' => ['data' => [['attributes' => ['name' => 'Vigilanza', 'color' => '#4073d4']]]],
            'artFront' => ['data' => ['attributes' => ['url' => 'https://cdn.example/front.png', 'formats' => ['card' => ['url' => 'https://cdn.example/front-card.png']]]]],
            'artBack' => ['data' => ['attributes' => ['url' => 'https://cdn.example/back.png', 'formats' => ['card' => ['url' => 'https://cdn.example/back-card.png']]]]],
        ],
    ], $overrides);
}
```

### Dopo

```php
function fakeCardEntry(array $overrides = []): array
{
    static $base = null;

    if ($base === null) {
        $json    = \Illuminate\Support\Facades\Storage::disk('local')->get('api-example-result.json');
        $all     = json_decode($json, true);
        $first   = reset($all);           // primo URL come chiave
        $base    = $first['data'][0];     // prima card: Luke Skywalker (id 7)
    }

    return array_replace_recursive($base, $overrides);
}
```

---

## Note importanti

- Si usa `static $base` per evitare di rileggere il file a ogni chiamata
  (i test chiamano `fakeCardEntry()` più volte).
- `reset($all)` restituisce il valore del primo elemento dell'array
  associativo (la risposta alla prima URL), indipendentemente dalla chiave.
- I test esistenti rimangono compatibili perché la card di base è sempre
  Luke Skywalker con `cardUid = 2579145458`, identica a quella attuale.
- Nessuna dipendenza da `use` aggiuntiva è necessaria: il file di test
  è già in un contesto Pest con accesso ai Facade di Laravel.

---

## Verifiche post-applicazione

```bash
php artisan test --filter ImportCardsFromSwuApiJobTest
```

Tutti i test del file devono passare senza modifiche aggiuntive.
