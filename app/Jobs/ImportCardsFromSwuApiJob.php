<?php

namespace App\Jobs;

use App\Mail\AdminScanReportEmail;
use App\Mail\NewCardsEmail;
use App\Models\Aspect;
use App\Models\Card;
use App\Models\CardTrait;
use App\Models\Expansion;
use App\Models\SystemError;
use App\Models\User;
use App\Services\CardImageDownloader;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ImportCardsFromSwuApiJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $backoff = 180;

    public function handle(TelegramService $telegram, CardImageDownloader $imageDownloader): void
    {
        $adminChatId = config('services.telegram.admin_chat_id');
        $progress = $telegram->sendMessage($adminChatId, 'Scan avviato...');
        Log::debug('message status', ['progress' => $progress]);

        $newCards = collect();
        $errors = collect();
        $existingCards = collect();
        $page = 1;
        $lastPage = 'not yet readed';
        $cardPerPage = 40;
        $deltaProgress = $progress;
        $firstRun = true;
        Log::info('start cicle');
        do {
            $deltaProgress = $telegram->editMessage($adminChatId, $progress->messageId, "Scan in corso: pagina {$page}/{$lastPage}...");
            Log::info('message status', ['deltaProgress' => $deltaProgress]);
            Log::info("Scan in corso: pagina {$page}/{$lastPage}...");
            $response = Http::get('https://admin.starwarsunlimited.com/api/card-list', [
                'locale' => 'it',
                'filters[variantOf][id][$null]' => 'true',
                'fields' => ['cardUid', 'cardNumber', 'title', 'subtitle', 'unique', 'cost', 'hp', 'power', 'text', 'artist', 'publishedAt'],
                'pagination[page]' => $page,
                'pagination[pageSize]' => $cardPerPage,
            ]);

            Log::info("finish api call of page {$page}/{$lastPage}");

            if ($response->failed()) {
                $deltaProgress = $telegram->editMessage($adminChatId, $progress->messageId, "Pagina {$page}: richiesta API fallita ({$response->status()})");
                Log::debug('message status', ['deltaProgress' => $deltaProgress]);
                Log::warning("Pagina {$page}: richiesta API fallita ({$response->status()})");
                break;
            }

            $payload = $response->json();
            $lastPage = $payload['meta']['pagination']['pageCount'] ?? $page;
            $latestRotation = Expansion::max('rotation') ?? '0';

            Log::info("trovate pagine totali: {$lastPage}", ['lastPage' => $lastPage]);
            if ($firstRun) {
                $deltaProgress = $telegram->editMessage($adminChatId, $progress->messageId, "Scan in corso: pagina {$page}/{$lastPage}...");
                Log::debug('message status', ['deltaProgress' => $deltaProgress]);
                $firstRun = false;
            }
            $currentCardOnPage = 1;
            foreach ($payload['data'] ?? [] as $cardEntry) {
                try {
                    $deltaProgress = $telegram->editMessage($adminChatId, $progress->messageId, "Scan in corso: carta {$currentCardOnPage}/{$cardPerPage} della pagina {$page}/{$lastPage}");
                    Log::debug('message status', ['deltaProgress' => $deltaProgress]);
                    Log::info("Scan in corso: carta {$currentCardOnPage}/{$cardPerPage} della pagina {$page}/{$lastPage}");

                    $this->processCard($cardEntry['attributes'] ?? [], $latestRotation, $imageDownloader, $newCards, $errors);
                } catch (\Throwable $th) {
                    Log::warning("errore nell'elaborazione della carta", ['raw' => $cardEntry, 'error' => $th]);
                    $errors->push($th);

                    continue;
                } finally {
                    $currentCardOnPage++;
                }
            }
            $deltaProgress = $telegram->editMessage($adminChatId, $progress->messageId, "Scan completato: pagina {$page}/{$lastPage}...");
            Log::debug('message status', ['deltaProgress' => $deltaProgress]);
            Log::info("Scan completato: pagina {$page}/{$lastPage}...");
            $page++;
        } while (is_int($lastPage) && $page <= $lastPage);

        $this->sendNotifications($newCards, $errors);

        // delete the message so at the end the user receive a notification, otherwise he have to check once in a while if the process is finished
        $deltaProgress = $telegram->deleteMessage($adminChatId, $progress->messageId);
        Log::debug('message status', ['deltaProgress' => $deltaProgress]);

        $deltaProgress = $telegram->sendMessage(
            $adminChatId,
            "Scan completato: {$newCards->count()} nuove carte, {$existingCards->count()} carte già presenti, {$errors->count()} problemi."
        );
        Log::debug('message status', ['deltaProgress' => $deltaProgress]);
        Log::info("Scan completato: {$newCards->count()} nuove carte, {$existingCards->count()} carte già presenti, {$errors->count()} problemi.");
    }

    private function processCard(
        array $cardData,
        string $lastestRotation,
        CardImageDownloader $imageDownloader,
        Collection $newCards,
        Collection $existingCards
    ): void {
        $cid = $cardData['cardUid'] ?? null;

        try {
            if (! $cid) {
                Log::warning('cardUid mancante nel payload', ['raw' => $cardData]);
                throw new \RuntimeException('cardUid mancante nel payload');
            }

            Log::info("Processo carta {$cid}");

            $existed = Card::where('cid', $cid)->exists();
            $convertType = function ($type) {
                return match ($type) {
                    'Base' => 'Base',
                    'Evento' => 'Event',
                    'Leader' => 'Leader',
                    'Miglioria' => 'Upgrade',
                    'Miglioria Segnalino' => 'TokenUpgrade',
                    'Segnalino Credito' => 'CreditToken',
                    'Segnalino Forza' => 'ForceToken',
                    'Unità' => 'Unit',
                    'Unità Segnalino' => 'TokenUnit',
                    default => $type,
                };
            };

            if (str_contains($convertType($cardData['type']['data']['attributes']['name'] ?? null), 'Token')) {
                $cardData['expansion']['data']['attributes']['code'] =
                    "T{$cardData['expansion']['data']['attributes']['code']}";
            }

            $this->createExpansionIfMissing($cardData, $lastestRotation);

            $card = Card::updateOrCreate(
                ['cid' => $cid],
                [
                    'expansion' => $cardData['expansion']['data']['attributes']['code'] ?? null,
                    'number' => $cardData['cardNumber'],
                    'unique_card' => $cardData['unique'] ?? false,
                    'name' => $cardData['title'],
                    'title' => $cardData['subtitle'] ?? null,
                    'type' => $convertType($cardData['type']['data']['attributes']['name'] ?? null),
                    'rarity' => $cardData['rarity']['data']['attributes']['name'] ?? null,
                    'cost' => $cardData['cost'] ?? null,
                    'health' => $cardData['hp'] ?? null,
                    'power' => $cardData['power'] ?? null,
                    'text' => $cardData['text'] ?? '',
                    'arena' => $cardData['arenas']['data'][0]['attributes']['name'] ?? null,
                    'artist' => $cardData['artist'] ?? null,
                    'release_date' => Carbon::parse($cardData['publishedAt'])->toDateString() ?? null,
                    'max_copies' => $cardData['cardNumber'] == 256 && $cardData['expansion']['data']['attributes']['code'] == 'JTL' ? 15 : null,
                ]
            );
            $card->save();
            Log::info("Dati della carta {$cid} ('{$card->expansion}-{$card->number}') recuperati dall'api e record inserito/aggiornato, sincronizzazione altri dati in corso", ['card' => $card, 'cid' => $cid]);

            if (! $existed) {
                $newCards->push($card);
            } else {
                $existingCards->push($card);
            }

            $this->syncAspectsAndTraits($card, $cardData);
            $this->downloadImages($card, $cardData, $imageDownloader);

        } catch (\Throwable $e) {
            $espansione = $card->expansion ?? 'Espansione Mancante';
            $numero = $card->number ?? 'Numero Mancante';
            $err = SystemError::create([
                'source' => self::class,
                'message' => "Errore su carta {$cid} ({$espansione}-{$numero})",
                'stack_trace' => $e->getTraceAsString(),
                'context' => [
                    'error_message' => $e->getMessage(),
                    'error_line' => $e->getLine(),
                    'error_code' => $e->getCode(),
                    'error_file' => $e->getFile(),
                    'raw' => $cardData,
                ],
            ]);
            throw $e;
        }
        Log::info("Fine elaborazione carta {$cid}", ['card' => $card, 'cid' => $cid]);
    }

    private function createExpansionIfMissing(array $cardData, string $lastestRotation): void
    {
        Log::info("inizio processo di creazione dell'espansione se manca");
        $expansionData = $cardData['expansion']['data']['attributes'] ?? null;
        $expansionCode = $expansionData['code'] ?? null;

        if ($expansionCode && ! Expansion::where('expansion', $expansionCode)->exists()) {
            Log::info("l'espansione non era presente: creazione dell'espansione {$expansionCode}", ['code' => $expansionCode]);
            Expansion::create(
                [
                    'expansion' => $expansionCode,
                    'legal_date' => Carbon::parse($expansionData['publishedAt'])->toDateString(),
                    'rotation' => $lastestRotation,
                ]
            );
        }
        Log::info("fine processo di creazione dell'espansione se manca");
    }

    private function syncAspectsAndTraits(Card $card, array $cardData): void
    {
        $aspectIds = collect($cardData['aspects']['data'] ?? [])->map(function ($aspectEntry) {
            $attr = $aspectEntry['attributes'];

            return Aspect::updateOrCreate(
                ['name' => $attr['name']],
                [
                    'color' => $attr['color'] ?? null,
                    'order' => $attr['sortValue'] ?? Aspect::max('order') + 1,
                    'slug' => Str::slug($attr['englishName'] ?? $attr['name']),
                ]
            )->id;
        });
        $card->aspects()->sync($aspectIds);

        $traitNames = collect($cardData['traits']['data'] ?? [])->pluck('attributes.name');
        $traitNames->each(fn ($name) => CardTrait::firstOrCreate(['name' => $name]));
        $card->traits()->sync($traitNames);
    }

    private function downloadImages(Card $card, array $cardData, CardImageDownloader $imageDownloader): void
    {
        $frontUrl = $cardData['artFront']['data']['attributes']['url']
            ?? $cardData['artFront']['data']['attributes']['formats']['card']['url']
            ?? null;

        if ($frontUrl && ! $card->front_art_path) {
            $path = $imageDownloader->download($frontUrl, $card, 'front');
            $path ? $card->update(['front_art_path' => $path]) : null;
        }

        $backAttrs = $cardData['artBack']['data']['attributes'] ?? null;
        $backUrl = $backAttrs['url'] ?? $backAttrs['formats']['card']['url'] ?? null;

        if ($backUrl && ! $card->back_art_path) {
            $path = $imageDownloader->download($backUrl, $card, 'back');
            $path ? $card->update(['back_art_path' => $path]) : SystemError::create([
                'source' => CardImageDownloader::class,
                'message' => "Download immagine retro fallito per {{$card->cid}} ({$card->expansion}-{$card->number} - {$card->name}, {$card->title})",
            ]);
        }
    }

    private function sendNotifications(Collection $newCards, Collection $errors): void
    {
        if ($newCards->isNotEmpty()) {
            foreach (User::all() as $user) {
                Mail::to($user)->queue(new NewCardsEmail($newCards));
            }
        }

        if ($errors->isNotEmpty()) {
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                Mail::to($admin)->queue(new AdminScanReportEmail($errors));
            }
        }
    }
}
