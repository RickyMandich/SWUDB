# Implementation plan 09 — Ricostruzione UnlimitedDB · Fase 9b: bot Telegram, webhook e notifiche

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> **Prerequisiti**: [`implementationPlan-01-ricostruzioneTelegramService.md`](implementationPlan-01-ricostruzioneTelegramService.md) (il `TelegramService` usato qui) e
> [`implementationPlan-05-ricostruzioneCatalogoPubblico.md`](implementationPlan-05-ricostruzioneCatalogoPubblico.md) Step 10.1 (la rotta `cards.show` usata dal messaggio di ripiego di `/search`).
>
> Stato del codice: non esistono controller del bot, rotta webhook né job di notifica.

## Fase 9 — Bot Telegram (webhook e notifiche)

### Step 9.3 — Webhook

#### 9.3.1 — Controller
```
php artisan make:controller TelegramController
```

#### 9.3.2 — Rotta
In `routes/web.php`, fuori da qualunque gruppo `auth` (Telegram non ha una sessione Laravel):
```php
Route::post('/telegram/webhook', [TelegramController::class, 'handle'])->name('telegram.webhook');
```

#### 9.3.3 — Esenzione CSRF
Telegram invia una POST senza token CSRF: senza esenzione ogni update riceve `419` e il bot sembra non rispondere mai. In `bootstrap/app.php`, dentro la closure di `withMiddleware` che oggi contiene solo `$middleware->alias([...])`, aggiungere:
```php
$middleware->validateCsrfTokens(except: ['telegram/webhook']);
```

#### 9.3.4 — Logica del controller
Rispetto alla prima bozza il controllo del secret è più stretto: con `!==` e un `webhook_secret` non configurato (`null`) un header assente risultava uguale e la richiesta passava.
```php
class TelegramController extends Controller
{
    public function handle(Request $request, TelegramService $telegram): Response
    {
        $secret = (string) config('services.telegram.webhook_secret');
        abort_unless(
            $secret !== '' && hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')),
            403
        );

        $text = trim($request->input('message.text', ''));
        $chatId = $request->input('message.chat.id');
        [$command, $argument] = array_pad(explode(' ', $text, 2), 2, null);

        match ($command) {
            '/scan' => $this->handleScan($chatId, $telegram),
            '/search' => $this->handleSearch($chatId, $argument, $telegram),
            default => $telegram->sendMessage($chatId, 'Comandi disponibili: /scan, /search <nome carta>'),
        };

        return response()->noContent(); // Telegram si aspetta solo un 200, non legge il body
    }

    private function isAdminChat(int|string $chatId): bool
    {
        return (string) $chatId === (string) config('services.telegram.admin_chat_id');
    }

    private function handleScan(int|string $chatId, TelegramService $telegram): void
    {
        if (! $this->isAdminChat($chatId)) {
            $telegram->sendMessage($chatId, 'Comando riservato agli admin.');
            return;
        }
        \App\Jobs\ImportCardsFromSwuApiJob::dispatch();
        $telegram->sendMessage($chatId, 'Scan avviato, ti aggiorno qui.');
    }

    private function handleSearch(int|string $chatId, ?string $query, TelegramService $telegram): void
    {
        if (! $query) {
            $telegram->sendMessage($chatId, 'Uso: /search <nome carta>');
            return;
        }

        $card = \App\Models\Card::where('name', 'like', "%{$query}%")->first();

        if (! $card) {
            $telegram->sendMessage($chatId, "Nessuna carta trovata per {$query}.");
            return;
        }

        // Telegram vuole un URL assoluto e pubblico, non il path relativo salvato in database
        $imageUrl = $card->front_art_path ? asset('storage/'.$card->front_art_path) : null;
        $result = $imageUrl
            ? $telegram->sendPhoto($chatId, $imageUrl, $card->name)
            : $telegram->sendMessage($chatId, $card->name);

        if (! $result->successful) {
            $telegram->sendMessage($chatId, $card->name.' — '.route('cards.show', ['expansion' => $card->expansion, 'number' => $card->number]));
        }
    }
}
```
In locale l'URL dell'immagine non è raggiungibile da Telegram: `sendPhoto` fallisce e scatta il messaggio di ripiego con il link.

#### 9.3.5 — Registrazione del webhook (una tantum, da terminale)
```
curl -F "url=https://unlimiteddb.mandich.dev/telegram/webhook" -F "secret_token=IL_TUO_SECRET" https://api.telegram.org/bot<TOKEN>/setWebhook
```

### Step 9.6 — `NotifyAdminJob`
```php
class NotifyAdminJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $message) {}

    public function handle(TelegramService $telegram): void
    {
        $telegram->sendMessage(config('services.telegram.admin_chat_id'), $this->message);
    }
}
```
