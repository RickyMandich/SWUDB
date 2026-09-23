<?php

namespace App\Jobs;

use App\Mail\AdminScanReportEmail;
use App\Mail\NewCardsEmail;
use App\Models\Card;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ImportCardsFromSwuApiJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = 60;

    public function handle(TelegramService $telegram, CardImageDownloader $imageDownloader): void
    {
        $adminChatId = config('services.telegram.admin_chat_id');
        $progress = $telegram->sendMessage($adminChatId, 'Scan avviato...');

        $newCards = collect();
        $errors = collect();
        $page = 1;

        do {
            $response = Http::get('https://admin.starwarsunlimited.com/api/card-list', [
                'locale' => 'it',
                'filters[variantOf][id][$null]' => 'true',
                'fields' => ['cardUid', 'cardNumber', 'title', 'subtitle', 'unique', 'cost', 'hp', 'power', 'text', 'artist'],
                'pagination[page]' => $page,
                'pagination[pageSize]' => 40,
            ]);

            if ($response->failed()) {
                SystemError::create([
                    'source' => self::class,
                    'message' => "Pagina {$page}: richiesta API fallita ({$response->status()})",
                    'context' => ['page' => $page, 'body' => $response->body()],
                ]);
                break; // l'intera pagina non e' recuperabile, non ha senso continuare a paginare
            }

            $payload = $response->json();
            $lastPage = $payload['meta']['pagination']['pageCount'] ?? $page;
            $lastestRotation = Expansion::max('rotation') ?? '0';

            foreach ($payload['data'] ?? [] as $cardEntry) {
                $cardData = $cardEntry['attributes'] ?? [];
                $cid = $cardData['cardUid'] ?? null;

                try {
                    if (! $cid) {
                        throw new \RuntimeException('cardUid mancante nel payload');
                    }

                    $existed = Card::where('cid', $cid)->exists();

                    // Espansione: crea se non esiste (stessa FK-first strategy di aspetti e tratti)
                    $expansionData = $cardData['expansion']['data']['attributes'] ?? null;
                    $expansionCode = $expansionData['code'] ?? null;
                    if ($expansionCode) {
                        Expansion::firstOrCreate(['expansion' => $expansionCode],
                            [
                                'legal_date' => Carbon::parse($expansionData['publishedAt'])->toDateString(),
                                'rotation' => $lastestRotation,
                            ]
                        );
                    }


                    $card = Card::updateOrCreate(
                        ['cid' => $cid],
                        [
                            'expansion' => $cardData['expansion']['data']['attributes']['code'] ?? null,
                            'number' => $cardData['cardNumber'],
                            'unique_card' => $cardData['unique'] ?? false,
                            'name' => $cardData['title'],
                            'title' => $cardData['subtitle'] ?? null,
                            'type' => $cardData['type']['data']['attributes']['value'] ?? null,
                            'rarity' => $cardData['rarity']['data']['attributes']['englishName'] ?? null,
                            'cost' => $cardData['cost'] ?? null,
                            'health' => $cardData['hp'] ?? null,
                            'power' => $cardData['power'] ?? null,
                            'text' => $cardData['text'] ?? '',
                            'arena' => $cardData['arenas']['data'][0]['attributes']['name'] ?? null,
                            'artist' => $cardData['artist'] ?? null,
                            'release_date' => Carbon::parse($cardData['publishedAt'])->toDateString() ?? null,
                            'max_copies' => // the only card that breaks the max copies rule (actually i have to do this because the api do not support this)
                                    $cardData['cardNumber'] == 256
                                        &&
                                    $cardData['expansion']['data']['attributes']['code'] == 'JTL'
                                ? 15 : null,
                        ]
                    );

                    if (! $existed) {
                        $newCards->push($card);
                    } else {
                        $err = SystemError::Create([
                            'source' => self::class,
                            'message' => "Carta {$cid} gia' presente, dati aggiornati",
                            'status' => SystemError::STATUS_IGNORED,
                            'context' => ['card' => $card],
                        ]);
                        $errors->push($err);
                    }

                    // Aspetti e tratti: upsert + sync sulla pivot, non solo creazione
                    $aspectIds = collect($cardData['aspects']['data'] ?? [])->map(function ($aspectEntry) {
                        $attr = $aspectEntry['attributes'];
                        return \App\Models\Aspect::updateOrCreate(
                            ['name' => $attr['name']],
                            [
                                'color' => $attr['color'] ?? null,
                                'order' => $attr['sortValue'] ?? null,
                                'slug'  => \Illuminate\Support\Str::slug($attr['englishName'] ?? $attr['name']),
                            ]
                        )->id;
                    });
                    $card->aspects()->sync($aspectIds);


                    $traitNames = collect($cardData['traits']['data'] ?? [])->pluck('attributes.name');
                    $traitNames->each(fn ($name) => \App\Models\CardTrait::firstOrCreate(['name' => $name]));
                    $card->traits()->sync($traitNames);

                    $frontUrl = $cardData['artFront']['data']['attributes']['url']
                        ?? $cardData['artFront']['data']['attributes']['formats']['card']['url']
                        ?? null;
                    if ($frontUrl && ! $card->front_art_path) {
                        $path = $imageDownloader->download($frontUrl, $card->expansion, $card->number, 'front');
                        $path ? $card->update(['front_art_path' => $path]) : SystemError::create([
                            'source' => CardImageDownloader::class,
                            'message' => "Download immagine davanti fallito per {{$cid}} ({$card->expansion}-{$card->number} - {$card->name}, {$card->title})",
                        ]);
                    }


                    $backAttrs = $cardData['artBack']['data']['attributes'] ?? null;
                    $backUrl = $backAttrs['url'] ??
                        $backAttrs['formats']['card']['url'] ??
                        null;

                    if ($backUrl && ! $card->back_art_path) {
                        $path = $imageDownloader->download($backUrl, $card->expansion, $card->number, 'back');
                        $path ? $card->update(['back_art_path' => $path]) : SystemError::create([
                            'source' => CardImageDownloader::class,
                            'message' => "Download immagine retro fallito per {{$cid}} ({$card->expansion}-{$card->number} - {$card->name}, {$card->title})",
                        ]);
                    }
                } catch (\Throwable $e) {
                    $err = SystemError::create([
                        'source' => self::class,
                        'message' => "Errore su carta {$cid} " . ($cid ? '' : '(cid mancante)'),
                        'stack_trace' => $e->getTraceAsString(),
                        'context' => ['raw' => $cardData, 'error' => $e],
                    ]);
                    $errors->push($err);
                    continue; // fail-soft: una carta rotta non ferma lo scan
                }
            }

            $telegram->editMessage($adminChatId, $progress->messageId, "Scan in corso: pagina {$page}/{$lastPage}...");
            $page++;
        } while ($page <= $lastPage);

        if ($newCards->isNotEmpty()) {
            foreach(User::all() as $user) {
                Mail::to($user)->queue(new NewCardsEmail($newCards));
            }
        }
        if ($errors->isNotEmpty()) {
            $admins = User::role('admin')->get();
            Mail::to($admins)->queue(new AdminScanReportEmail($errors));
        }

        $telegram->editMessage(
            $adminChatId,
            $progress->messageId,
            "Scan completato: {$newCards->count()} nuove carte, {$errors->count()} problemi."
        );
    }
}