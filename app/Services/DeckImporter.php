<?php

namespace App\Services;

use App\Enums\DeckFormat;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DeckImporter
{
    private const MAX_BYTES = 1_048_576; // 1 MB: un mazzo pesa pochi KB

    /**
     * Imports a deck from raw content (JSON or TXT, detected from the content itself)
     * Importa un mazzo da un contenuto grezzo (JSON o TXT, rilevato dal contenuto stesso)
     *
     * @return array{deck: ?Deck, notFound: array<int, string>, sideboardIgnored: int, error: ?string}
     *                                                                                                 deck = il mazzo creato (null se non è stata riconosciuta nessuna carta), notFound = righe/id non risolti,
     *                                                                                                 sideboardIgnored = copie di sideboard scartate, error = motivo del fallimento (null se deck non è null)
     */
    public function fromContent(string $content, User $owner, string $name, DeckFormat $format, bool $isPublic = false): array
    {
        $data = json_decode($content, true);

        [$rows, $sideboard] = is_array($data) ? $this->parseJson($data) : $this->parseText($content);

        return $this->persist($rows, $sideboard, $owner, $name, $format, $isPublic);
    }

    /**
     * Downloads a deck from an https URL (swudb.com deck links are rewritten to their API) and imports it
     * Scarica un mazzo da un URL https (i link swudb.com si riscrivono verso la loro API) e lo importa
     *
     * @return array{deck: ?Deck, notFound: array<int, string>, sideboardIgnored: int, error: ?string}
     */
    public function fromUrl(string $url, User $owner, string $name, DeckFormat $format, bool $isPublic = false): array
    {
        [$body, $error] = $this->download($url);

        if ($body === null) {
            return $this->failure($error);
        }

        return $this->fromContent($body, $owner, $name, $format, $isPublic);
    }

    /**
     * Whether the host resolves to a public address (no private/reserved ranges): the URL is typed by the user,
     * so the server must not be usable to reach internal services (SSRF). Protected so tests can bypass the DNS lookup.
     * Se l'host risolve a un indirizzo pubblico (niente range privati/riservati): l'URL lo scrive l'utente, quindi il server
     * non deve poter raggiungere servizi interni (SSRF). Protected così i test possono saltare la risoluzione DNS.
     */
    protected function isPublicHost(string $host): bool
    {
        $ip = gethostbyname($host); // se non risolve restituisce l'host invariato, che non è un IP valido

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @return array{0: ?string, 1: ?string} [body, error]: uno dei due è sempre null
     */
    private function download(string $url): array
    {
        if (preg_match('~^https?://(?:www\.)?swudb\.com/deck/([A-Za-z0-9]+)~', $url, $m)) {
            $url = "https://swudb.com/api/deck/{$m[1]}";
        }

        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            return [null, 'Sono accettati solo URL https.'];
        }
        if (! $this->isPublicHost($parts['host'])) {
            return [null, 'Indirizzo non consentito o host non raggiungibile.'];
        }

        try {
            // Nessun redirect: il controllo sull'host varrebbe solo per il primo indirizzo, non per quelli a cui rimanda
            $response = Http::timeout(10)->connectTimeout(5)->withoutRedirecting()
                ->accept('application/json, text/plain')
                ->get($url);
        } catch (ConnectionException) {
            return [null, 'URL non raggiungibile.'];
        }

        if ($response->redirect()) {
            return [null, "L'URL rimanda a un altro indirizzo: incolla direttamente l'indirizzo finale."];
        }
        if ($response->failed()) {
            return [null, "L'URL ha risposto con errore HTTP {$response->status()}."];
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (! str_contains($contentType, 'json') && ! str_contains($contentType, 'text/plain')) {
            return [null, 'L\'URL non restituisce un file di mazzo (atteso JSON o testo, ricevuto "'.($contentType ?: 'sconosciuto').'").'];
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            return [null, 'Il file è troppo grande.'];
        }

        return [$body, null];
    }

    /**
     * @param  array<string, mixed>  $data  JSON decodificato (ufficiale o nativo di swudb.com)
     * @return array{0: list<array{label: string, card: ?Card, count: int}>, 1: int} [righe, copie di sideboard]
     */
    private function parseJson(array $data): array
    {
        if (isset($data['shuffledDeck'])) {
            $data = $this->convertSwudb($data);
        }

        $entries = [];
        foreach (['leader', 'secondleader', 'base'] as $key) {
            if (isset($data[$key]['id'])) {
                $entries[] = $data[$key];
            }
        }
        foreach ((array) ($data['deck'] ?? []) as $entry) {
            $entries[] = $entry;
        }

        $rows = [];
        foreach ($entries as $entry) {
            $id = (string) ($entry['id'] ?? '');
            $rows[] = ['label' => $id, 'card' => $this->findById($id), 'count' => (int) ($entry['count'] ?? 1)];
        }

        return [$rows, (int) collect($data['sideboard'] ?? [])->sum('count')];
    }

    /**
     * @return array{0: list<array{label: string, card: ?Card, count: int}>, 1: int} [righe, copie di sideboard]
     */
    private function parseText(string $text): array
    {
        $rows = [];
        $sideboard = 0;
        $section = null;

        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (in_array($line, ['Leaders', 'Base', 'Deck', 'Sideboard'], true)) {
                $section = $line;

                continue;
            }

            // Riga fuori da una sezione o che non ha la forma "{copie} | {nome}[ | {titolo}]": non si perde in silenzio
            if ($section === null || ! preg_match('/^(\d+)\s*\|\s*([^|]+?)\s*(?:\|\s*(.+))?$/', $line, $m)) {
                $rows[] = ['label' => $line, 'card' => null, 'count' => 1];

                continue;
            }

            $count = (int) $m[1];
            if ($section === 'Sideboard') {
                $sideboard += $count;

                continue;
            }

            $rows[] = ['label' => $line, 'card' => $this->findByText(trim($m[2]), trim($m[3] ?? '')), 'count' => $count];
        }

        return [$rows, $sideboard];
    }

    /**
     * Converts the native swudb.com JSON into the official format
     * Converte il JSON nativo di swudb.com nel formato ufficiale
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function convertSwudb(array $data): array
    {
        $id = fn (array $card): string => data_get($card, 'defaultExpansionAbbreviation', '').'_'.data_get($card, 'defaultCardNumber', '');

        $official = ['deck' => [], 'sideboard' => []];

        foreach (['leader' => 'leader', 'secondLeader' => 'secondleader', 'base' => 'base'] as $from => $to) { // 'secondLeader': da verificare (7.7.0)
            if (isset($data[$from]) && is_array($data[$from])) {
                $official[$to] = ['id' => $id($data[$from]), 'count' => 1];
            }
        }

        foreach ((array) $data['shuffledDeck'] as $entry) {
            $card = (array) ($entry['card'] ?? []);
            if (($entry['count'] ?? 0) > 0) {
                $official['deck'][] = ['id' => $id($card), 'count' => (int) $entry['count']];
            }
            if (($entry['sideboardCount'] ?? 0) > 0) {
                $official['sideboard'][] = ['id' => $id($card), 'count' => (int) $entry['sideboardCount']];
            }
        }

        return $official;
    }

    /**
     * Card from an official id "{EXPANSION}_{number}" (SOR_134, SOR_005 and SOR_5 are the same card)
     * Carta da un id ufficiale "{ESPANSIONE}_{numero}" (SOR_134; SOR_005 e SOR_5 sono la stessa carta)
     */
    private function findById(string $id): ?Card
    {
        if (! preg_match('/^([A-Za-z0-9]+)_(\d+)$/', $id, $m)) {
            return null;
        }

        $exp = strtoupper($m[1]);
        $num = (int) $m[2];

        // Prima cerca tra gli originali; se è un codice promo (es. SOROP_1 o JTLP_1), trova la variante
        return Card::originals()->where('expansion', $exp)->where('number', $num)->first()
            ?? Card::where('expansion', $exp)->where('number', $num)->first();
    }

    /**
     * Card from a TXT line: by name and title, or by "EXP-number" for the exports of the old SWUDB
     * Carta da una riga TXT: per nome e titolo, oppure per "ESP-numero" negli export della vecchia SWUDB
     */
    private function findByText(string $name, string $title): ?Card
    {
        if (preg_match('/^([A-Za-z0-9]+)-(\d+)$/', $name, $m)) {
            $exp = strtoupper($m[1]);
            $num = (int) $m[2];

            return Card::originals()->where('expansion', $exp)->where('number', $num)->first()
                ?? Card::where('expansion', $exp)->where('number', $num)->first();
        }

        return Card::originals()
            ->where('name', $name)
            ->when(
                $title !== '',
                fn ($q) => $q->where('title', $title),
                fn ($q) => $q->where(fn ($q) => $q->whereNull('title')->orWhere('title', ''))
            )
            ->orderByDesc('release_date') // più ristampe con lo stesso nome: la più recente
            ->first();
    }

    /**
     * @param  list<array{label: string, card: ?Card, count: int}>  $rows
     * @return array{deck: ?Deck, notFound: array<int, string>, sideboardIgnored: int, error: ?string}
     */
    private function persist(array $rows, int $sideboard, User $owner, string $name, DeckFormat $format, bool $isPublic): array
    {
        $quantities = [];
        $notFound = [];

        foreach ($rows as $row) {
            if ($row['card'] === null) {
                $notFound[] = $row['label'];
            } elseif ($row['count'] > 0) {
                // Se il file conteneva l'id di una variante/promo, si riconduce sempre all'originale
                $cardId = $row['card']->original_card_id ?? $row['card']->id;
                $quantities[$cardId] = ($quantities[$cardId] ?? 0) + $row['count'];
            }
        }

        if ($quantities === []) {
            return $this->failure('Nessuna carta riconosciuta nel file.', $notFound, $sideboard);
        }

        $deck = DB::transaction(function () use ($quantities, $owner, $name, $format, $isPublic) {
            $deck = Deck::create([
                'user_id' => $owner->id,
                'name' => $name,
                'format' => $format,
                'is_public' => $isPublic,
                'version' => 1,
            ]);
            $deck->cards()->attach(collect($quantities)->map(fn (int $quantity) => ['quantity' => $quantity])->all());

            return $deck;
        });

        return ['deck' => $deck, 'notFound' => $notFound, 'sideboardIgnored' => $sideboard, 'error' => null];
    }

    /**
     * @param  array<int, string>  $notFound
     * @return array{deck: null, notFound: array<int, string>, sideboardIgnored: int, error: ?string}
     */
    private function failure(?string $error, array $notFound = [], int $sideboard = 0): array
    {
        return ['deck' => null, 'notFound' => $notFound, 'sideboardIgnored' => $sideboard, 'error' => $error];
    }
}
