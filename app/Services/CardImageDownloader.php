<?php
namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class CardImageDownloader
{
    /**
     * Downloads a card image from the given URL and stores it on the public disk
     * Scarica l'immagine di una carta dall'URL indicato e la salva sul disk pubblico
     *
     * @param string $sourceUrl URL originale dell'immagine (dall'API SWU)
     * @param string $expansion Codice espansione, per il path di destinazione
     * @param int $number Numero carta, per il path di destinazione
     * @param string $side 'front' o 'back', per differenziare il nome file
     * @return string|null Path relativo salvato (es. "cards/SOR/1-front.webp"), null se il download fallisce
     */
    public function download(string $sourceUrl, string $expansion, int $number, string $side): ?string
    {
        // 1. Http::get($sourceUrl) -> se fallisce, ritorna null (il chiamante logga il SystemError)
        try{
            $response = Http::get($sourceUrl);
            if(!$response->successful()){
                // throw an error so i do not repeat the logic of returning null
                throw new ConnectionException("Failed to download image from {$sourceUrl}");
            }
        } catch (ConnectionException $e){
            return null;
        }
        
        // 2. determina l'estensione dal Content-Type della risposta (es. image/webp -> .webp), non dall'URL
        $ext = match(Str::before($response->header('Content-Type'), ';')){
            'image/webp' => 'webp',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => null,
        };
        
        if($ext === null){
            return null;
        }
        
        // 3. $path = "cards/{$expansion}/{$number}-{$side}.{$ext}"
        $path = "cards/{$expansion}/{$number}-{$side}.{$ext}";

        // 4. Storage::disk('public')->put($path, $response->body())
        $saved = Storage::disk('public')->put($path, $response->body());
        
        // 5. return $path
        return $saved ? $path : null;
    }
}