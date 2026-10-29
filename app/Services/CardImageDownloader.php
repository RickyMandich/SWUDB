<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CardImageDownloader
{
    /**
     * Downloads a card image from the given URL and stores it on the public disk
     * Scarica l'immagine di una carta dall'URL indicato e la salva sul disk pubblico
     *
     * @param  string  $sourceUrl  URL originale dell'immagine (dall'API SWU)
     * @param  string  $card  The card to download the image for
     * @param  string  $side  'front' o 'back', per differenziare il nome file
     * @return string|null Path relativo salvato (es. "cards/SOR/1-front.webp"), null se il download fallisce
     */
    public function download(string $sourceUrl, Card $card, string $side): ?string
    {
        // 1. Se l'immagine esiste già in locale, restituisci il percorso.
        $basePath = "cards/{$card->expansion}/{$card->number}-{$side}";

        $disk = Storage::disk('public');

        foreach (['webp', 'jpg', 'png'] as $ext) {
            $path = "{$basePath}.{$ext}";

            if ($disk->exists($path)) {
                return $path;
            }
        }

        // 2. Scarica l'immagine solo se non esiste già.
        $response = null;

        try {
            $response = Http::timeout(20)
                ->retry(2, 500, throw: false)
                ->get($sourceUrl);

            if (! $response->successful()) {
                throw new ConnectionException(
                    "Failed to download image from {$sourceUrl}"
                );
            }
        } catch (ConnectionException $e) {
            Log::warning(
                "Download immagine {$side} fallito per {$card->cid} ".
                "({$card->expansion}-{$card->number} - {$card->name}, {$card->title}), ".
                "returned code {$response?->status()}",
                [
                    'source_url' => $sourceUrl,
                    'cid' => $card->cid,
                    'snippet' => $card->snippet,
                    'id' => $card->id,
                    'side' => $side,
                ]
            );

            return null;
        }

        // 3. Determina l'estensione dal Content-Type.
        $ext = match (Str::before($response->header('Content-Type'), ';')) {
            'image/webp' => 'webp',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => null,
        };

        if ($ext === null) {
            return null;
        }

        // 4. Salva l'immagine.
        $path = "{$basePath}.{$ext}";

        $saved = $disk->put($path, $response->body());

        // 5. Restituisce il percorso solo se il salvataggio è riuscito.
        return $saved ? $path : null;
    }
}
