# Implementation plan 01 — Ricostruzione UnlimitedDB · Fase 9a: `TelegramService` (bloccante per lo scan)

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md). **Primo piano da eseguire.**
> Il webhook e le notifiche (Step 9.3 e 9.6) stanno in [`implementationPlan-09-ricostruzioneBotTelegramWebhook.md`](implementationPlan-09-ricostruzioneBotTelegramWebhook.md).
>
> **Perché per primo**: `ImportCardsFromSwuApiJob::handle()` ha `TelegramService` nella firma e `App\Services\TelegramService` non esiste. Il job (quindi `cards:scan`, lo scheduler del lunedì e
> l'intera suite Pest del job) fallisce appena parte.
>
> Stato del codice: `config/services.php` ha già le chiavi `services.telegram.bot_token`, `admin_chat_id`, `webhook_secret` (lette da `TELEGRAM_BOT_TOKEN`, `TELEGRAM_ADMIN_CHAT_ID`,
> `TELEGRAM_WEBHOOK_SECRET`), ma `.env.example` non le elenca.

## Fase 9 — Bot Telegram (servizio)

### Step 9.1 — Libreria
Facade `Http` nativa (https://laravel.com/docs/12.x/http-client), nessun SDK esterno: la vecchia versione usava `telegram-bot/api`, ma i wrapper PHP per Telegram di quella fascia sono in gran parte poco mantenuti o abbandonati.

### Step 9.2 — `TelegramService`

#### 9.2.1 — Variabili d'ambiente
Aggiungere a `.env.example` (e ai `.env` locale e di produzione con i valori veri):
```env
TELEGRAM_BOT_TOKEN=
TELEGRAM_ADMIN_CHAT_ID=
TELEGRAM_WEBHOOK_SECRET=
```
`TELEGRAM_WEBHOOK_SECRET` accetta solo lettere, cifre, `_` e `-` (vincolo di Telegram).

#### 9.2.2 — `app/Services/TelegramActionResult.php`
```php
namespace App\Services;

final readonly class TelegramActionResult
{
    public function __construct(
        public bool $successful,
        public ?int $messageId = null,
        public ?string $errorDescription = null,
        public array $raw = [],
    ) {}
}
```

#### 9.2.3 — `app/Services/TelegramService.php`
Un solo punto di contatto con l'API di Telegram. Due vincoli che vengono dal job di import e dai test:
- **`$chatId` e `$messageId` possono essere `null`**: il job passa `config('services.telegram.admin_chat_id')` (nullo se non configurato) e `$progress->messageId` (nullo se il primo invio è fallito). Con tipi non nullable
  la prima chiamata andrebbe in `TypeError`. In questi casi il service non fa nulla e ritorna un risultato "non riuscito".
- **Senza `bot_token` non parte nessuna richiesta HTTP**: così lo scan funziona anche in locale e nei test senza Telegram configurato.
```php
namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    /**
     * Sends a text message
     * Invia un messaggio di testo
     */
    public function sendMessage(int|string|null $chatId, string $text, array $options = []): TelegramActionResult
    {
        return $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $text] + $options);
    }

    /**
     * Sends a photo by public URL (Telegram must be able to fetch it)
     * Invia una foto tramite URL pubblico (Telegram deve poterlo scaricare)
     */
    public function sendPhoto(int|string|null $chatId, string $photoUrl, string $caption = '', array $options = []): TelegramActionResult
    {
        return $this->call('sendPhoto', ['chat_id' => $chatId, 'photo' => $photoUrl, 'caption' => $caption] + $options);
    }

    /**
     * Edits the text of a message already sent (used for the single progress message of a scan)
     * Modifica il testo di un messaggio già inviato (usato per l'unico messaggio di avanzamento dello scan)
     */
    public function editMessage(int|string|null $chatId, ?int $messageId, string $text): TelegramActionResult
    {
        if ($messageId === null) {
            return new TelegramActionResult(false, null, 'messageId assente: il messaggio iniziale non è stato inviato');
        }

        return $this->call('editMessageText', ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text]);
    }

    /**
     * Deletes a message
     * Elimina un messaggio
     */
    public function deleteMessage(int|string|null $chatId, ?int $messageId): TelegramActionResult
    {
        if ($messageId === null) {
            return new TelegramActionResult(false, null, 'messageId assente');
        }

        return $this->call('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    /**
     * Calls a Telegram Bot API method and maps {"ok","result","description"} to a TelegramActionResult
     * Chiama un metodo dell'API bot di Telegram e traduce {"ok","result","description"} in un TelegramActionResult
     */
    private function call(string $method, array $payload): TelegramActionResult
    {
        $token = config('services.telegram.bot_token');

        if (! $token || empty($payload['chat_id'])) {
            return new TelegramActionResult(false, null, 'Telegram non configurato (bot_token o chat_id mancanti)');
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/{$method}", $payload);
        } catch (ConnectionException $e) {
            return new TelegramActionResult(false, null, $e->getMessage());
        }

        $body = $response->json() ?? [];

        return new TelegramActionResult(
            (bool) ($body['ok'] ?? false),
            $body['result']['message_id'] ?? null,
            $body['description'] ?? null,
            $body,
        );
    }
}
```
Altri metodi (`pinMessage`, `answerCallbackQuery`, ...) si aggiungono man mano che servono con lo stesso schema.

#### 9.2.4 — Test `tests/Feature/Services/TelegramServiceTest.php`
```php
use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;

it('non fa richieste HTTP se il bot token manca', function () {
    config(['services.telegram.bot_token' => null]);
    Http::fake();

    $result = app(TelegramService::class)->sendMessage('123', 'ciao');

    expect($result->successful)->toBeFalse();
    Http::assertNothingSent();
});

it('restituisce il message id della risposta di Telegram', function () {
    config(['services.telegram.bot_token' => 'TOKEN']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);

    $result = app(TelegramService::class)->sendMessage('123', 'ciao');

    expect($result->successful)->toBeTrue()->and($result->messageId)->toBe(42);
});

it('editMessage senza message id non fa nulla', function () {
    Http::fake();

    $result = app(TelegramService::class)->editMessage('123', null, 'testo');

    expect($result->successful)->toBeFalse();
    Http::assertNothingSent();
});
```

#### 9.2.5 — Verifica
```bash
docker compose -f docker-compose.dev.yml exec app php artisan test --filter TelegramServiceTest
docker compose -f docker-compose.dev.yml exec app php artisan cards:scan
docker logs unlimiteddb_worker_dev -f
```
Il worker deve eseguire il job senza `BindingResolutionException`. Il piano successivo è [`implementationPlan-02-ricostruzioneAllineamentoSchema.md`](implementationPlan-02-ricostruzioneAllineamentoSchema.md).
