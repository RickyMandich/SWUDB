# Implementation plan 06 — Ricostruzione UnlimitedDB · Fase 7a: mazzi, dominio e regole di formato

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md). Le pagine e l'export/import stanno in
> [`implementationPlan-07-ricostruzioneMazziPagine.md`](implementationPlan-07-ricostruzioneMazziPagine.md) (Fase 7b, da fare dopo questa).
>
> **Prerequisito**: [`implementationPlan-02-ricostruzioneAllineamentoSchema.md`](implementationPlan-02-ricostruzioneAllineamentoSchema.md) applicato (pivot con `card_id`, `Deck::cards()` corretta).
>
> Stato del codice: esistono le migration `decks`/`deck_cards` e i modelli `Deck`/`DeckCard`; non esistono enum, validatori, factory dei validatori, policy, rotte, controller né viste dei mazzi.
> La colonna `decks.format` è un `enum` SQL nativo (`premier`/`eternal`/`twin_suns`), non una stringa libera.

## Fase 7 — Gestione mazzi multi-formato (dominio)

### Step 7.1 — Enum `DeckFormat` e cast

#### 7.1.1 — Creare `app/Enums/DeckFormat.php`
La cartella `app/Enums/` non esiste ancora.
```php
namespace App\Enums;

enum DeckFormat: string
{
    case Premier = 'premier';
    case Eternal = 'eternal';
    case TwinSuns = 'twin_suns';
}
```

#### 7.1.2 — Cast su `Deck`
In `app/Models/Deck.php` aggiungere (mancano tutti):
```php
protected $casts = [
    'format' => \App\Enums\DeckFormat::class,
    'is_public' => 'boolean',
    'assembled' => 'boolean',
];
```
Da qui `$deck->format` restituisce un'istanza dell'enum (`DeckFormat::Premier`), utile per lo `match` di validator e factory (Step 7.3 e 7.4).
Riferimento: https://laravel.com/docs/12.x/eloquent-mutators#enum-casting

### Step 7.2 — Relazioni leader/base di `Deck`
Il ruolo di una carta nel mazzo non è una colonna di `deck_cards`: si deduce da `cards.type` (`Leader`, `Base`) con un join.

#### 7.2.1 — Cosa c'è oggi (da eliminare)
`Deck::leader()` e `Deck::base()` sono sbagliate per tre motivi: interrogano `Card` con `hasMany`/`hasOne` ma `cards` non ha una colonna `deck_id` (il legame passa dalla pivot `deck_cards`);
confrontano `$this->format === 'twin_suns'` con una stringa (ora sarà un enum); filtrano `where('type', 'leader')` in minuscolo mentre i valori dell'enum SQL sono `Leader` e `Base`.

#### 7.2.2 — Sostituzione in `app/Models/Deck.php`
Rimuovere `leader()` e `base()` e aggiungere:
```php
/**
 * Leader cards of the deck (1 in Premier/Eternal, 2 in Twin Suns); the count is enforced by the format validator
 * Carte leader del mazzo (1 in Premier/Eternal, 2 in Twin Suns); il conteggio lo controlla il validator del formato
 */
public function leaders()
{
    return $this->belongsToMany(Card::class, 'deck_cards', 'deck_id', 'card_id')
        ->using(DeckCard::class)
        ->withPivot('quantity')
        ->where('cards.type', 'Leader');
}

/**
 * Base card of the deck
 * Carta base del mazzo
 */
public function baseCard()
{
    return $this->belongsToMany(Card::class, 'deck_cards', 'deck_id', 'card_id')
        ->using(DeckCard::class)
        ->withPivot('quantity')
        ->where('cards.type', 'Base');
}
```
Restano `belongsToMany` veri, quindi eager-loadabili: `Deck::with('leaders', 'baseCard')->get()`. `previousVersion()` e `nextVersion()` sono già corrette.

### Step 7.3 — Validator per formato

#### 7.3.1 — Interfaccia `app/Services/DeckValidation/DeckFormatValidator.php`
```php
namespace App\Services\DeckValidation;

use App\Models\Deck;

interface DeckFormatValidator
{
    /**
     * Validates a deck against this format's rules, returning a list of human-readable errors
     * Valida un mazzo secondo le regole di questo formato, restituendo una lista di errori leggibili
     *
     * @return array<int, string> Vuoto se il mazzo è valido
     */
    public function validate(Deck $deck): array;
}
```

#### 7.3.2 — `PremierFormatValidator`
```php
namespace App\Services\DeckValidation;

use App\Models\Deck;

class PremierFormatValidator implements DeckFormatValidator
{
    public function validate(Deck $deck): array
    {
        $errors = [];

        if ($deck->leaders()->count() !== 1) {
            $errors[] = 'Il formato Premier richiede esattamente 1 leader.';
        }
        if ($deck->baseCard()->count() !== 1) {
            $errors[] = 'Il formato Premier richiede esattamente 1 base.';
        }

        foreach ($deck->cards as $card) {
            $limit = $card->max_copies ?? 3; // 3 è il limite standard SWU, max_copies lo sovrascrive per le eccezioni (es. JTL #256 = 15)
            if ($card->unique_card) {
                $limit = 1;
            }
            if ($card->pivot->quantity > $limit) {
                $errors[] = "Troppe copie di {$card->name} ({$card->pivot->quantity}/{$limit}).";
            }
        }

        // TODO: controllo di rotazione (solo le ultime 2 expansions.rotation) quando la Fase 6 avrà dati reali confermati

        return $errors;
    }
}
```

#### 7.3.3 — `EternalFormatValidator`
Stesso schema di Premier (1 leader + 1 base, limite copie), **senza** il controllo di rotazione: Eternal ammette tutte le espansioni.
```php
namespace App\Services\DeckValidation;

use App\Models\Deck;

class EternalFormatValidator implements DeckFormatValidator
{
    public function validate(Deck $deck): array
    {
        $errors = [];

        if ($deck->leaders()->count() !== 1) {
            $errors[] = 'Il formato Eternal richiede esattamente 1 leader.';
        }
        if ($deck->baseCard()->count() !== 1) {
            $errors[] = 'Il formato Eternal richiede esattamente 1 base.';
        }

        foreach ($deck->cards as $card) {
            $limit = $card->unique_card ? 1 : ($card->max_copies ?? 3);
            if ($card->pivot->quantity > $limit) {
                $errors[] = "Troppe copie di {$card->name} ({$card->pivot->quantity}/{$limit}).";
            }
        }

        return $errors;
    }
}
```

#### 7.3.4 — `TwinSunsFormatValidator`
Stesso schema con `leaders()->count() !== 2` e messaggi "2 leader". In più serve il controllo di **allineamento tra i due leader**: lo schema `cards` attuale non ha nessuna colonna per l'allineamento
(l'import non lo legge), quindi va prima capito come l'API lo espone (`aspects`? un campo dedicato?) e poi aggiunta una colonna. Fino ad allora lasciare nel validator un `// TODO: allineamento dei due leader`.

### Step 7.4 — Factory dei validator
`app/Services/DeckValidation/DeckFormatValidatorFactory.php`:
```php
namespace App\Services\DeckValidation;

use App\Enums\DeckFormat;

class DeckFormatValidatorFactory
{
    public static function make(DeckFormat $format): DeckFormatValidator
    {
        return match ($format) {
            DeckFormat::Premier => new PremierFormatValidator(),
            DeckFormat::Eternal => new EternalFormatValidator(),
            DeckFormat::TwinSuns => new TwinSunsFormatValidator(),
        };
    }
}
```
Uso tipico nel controller (Fase 7b): `DeckFormatValidatorFactory::make($deck->format)->validate($deck)`. Aggiungere un formato futuro richiede solo una nuova classe e un `case`.

### Step 7.5 — Policy

#### 7.5.1 — Generare
```
php artisan make:policy DeckPolicy --model=Deck
```

#### 7.5.2 — Contenuto di `app/Policies/DeckPolicy.php`
```php
namespace App\Policies;

use App\Models\Deck;
use App\Models\User;

class DeckPolicy
{
    public function update(User $user, Deck $deck): bool
    {
        return $user->id === $deck->user_id || $user->can('decks.manage-any');
    }

    public function delete(User $user, Deck $deck): bool
    {
        return $this->update($user, $deck);
    }

    public function view(?User $user, Deck $deck): bool
    {
        return $deck->is_public
            || ($user !== null && ($user->id === $deck->user_id || $user->can('decks.manage-any')));
    }
}
```
`view` accetta `?User` perché la lista e il dettaglio dei mazzi pubblici sono raggiungibili anche da ospiti (rispetto alla prima bozza, che dava per scontato un utente loggato e avrebbe rifiutato ogni ospite).
Laravel registra `DeckPolicy` da solo per il modello `Deck` (convenzione dei nomi, nessuna registrazione manuale da Laravel 11). Nel controller: `$this->authorize('update', $deck);`, in Blade `@can('update', $deck)`.
