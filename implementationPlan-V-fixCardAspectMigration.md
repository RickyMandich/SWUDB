# Fix: Migration `card_aspect` — Colonna `id` duplicata

## Problema

La migration `2026_09_20_115102_create_card_aspects_table.php` crea due colonne `id`:

```php
$table->id();                 // bigint autoincrement — NON DOVREBBE ESSERCI
$table->string('id');         // FK verso cards.id — CORRETTA
```

Questo causa un `QueryException: duplicate column name: id` quando SQLite (usato nei test) tenta di eseguire la migration.

## Causa

Le tabelle `card_trait` e `deck_cards` seguono il pattern corretto (niente `$table->id()`, solo `$table->string('id')` come FK). La tabella `card_aspect` ha un `$table->id()` aggiuntivo per errore.

## Soluzione

### File: `database/migrations/2026_09_20_115102_create_card_aspects_table.php`

**Rimuovere la riga 14** (`$table->id();`) e aggiungere una primary key composita come nelle altre pivot table.

Il file attuale:
```php
Schema::create('card_aspect', function (Blueprint $table) {
    $table->id();
    $table->string('id');
    $table->foreign('id')->references('id')->on('cards')->cascadeOnDelete();
    $table->foreignId('aspect_id')->constrained('aspects')->cascadeOnDelete();
    $table->timestamps();
});
```

Deve diventare:
```php
Schema::create('card_aspect', function (Blueprint $table) {
    $table->string('id');
    $table->foreign('id')->references('id')->on('cards')->cascadeOnDelete();
    $table->foreignId('aspect_id')->constrained('aspects')->cascadeOnDelete();
    $table->timestamps();

    $table->primary(['id', 'aspect_id']);
});
```

### Cambiamenti:
1. ~~`$table->id();`~~ — **rimossa** (non serve un autoincrement; `id` è la FK string verso `cards`)
2. **Aggiunta** `$table->primary(['id', 'aspect_id']);` — primary key composita, coerente col pattern usato in `card_trait` e `deck_cards`

## Verifica

Dopo la modifica, eseguire:
```bash
php artisan test --filter TelegramServiceTest
```

I 3 test dovrebbero passare (l'errore era nel boot dell'applicazione, non nel codice di Telegram).

## Note

- Se il database di sviluppo ha già la tabella `card_aspect` (creata con MySQL che potrebbe aver ignorato il conflitto), sarà necessario eseguire `php artisan migrate:fresh` o ricreare solo quella tabella.
