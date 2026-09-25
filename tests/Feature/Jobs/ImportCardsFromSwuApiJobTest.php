<?php

use App\Jobs\ImportCardsFromSwuApiJob;
use App\Mail\NewCardsEmail;
use App\Mail\AdminScanReportEmail;
use App\Models\Card;
use App\Models\SystemError;
use App\Services\CardImageDownloader;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

// Fixture minima ma fedele alla struttura reale confermata su test.json (Strapi: data[].attributes,
// relazioni annidate come attributes.expansion.data.attributes.code). Un helper tipo cardFixture(['cardUid' => ...])
// che parta da questo scheletro e sovrascriva solo i campi che cambiano evita di ripeterlo in ogni test.
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

it('crea le carte nuove ricevute dall\'API', function () {
    Http::fake([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [
                fakeCardEntry(),
                fakeCardEntry(['attributes' => ['cardUid' => '9999999999', 'cardNumber' => 6, 'title' => 'Leia Organa']]),
            ],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob())->handle(app(TelegramService::class), app(CardImageDownloader::class));

    expect(Card::count())->toBe(2);
    Mail::assertQueued(NewCardsEmail::class);
});

it('pagina correttamente su piu\' pagine', function () {
    Http::fake([
        'admin.starwarsunlimited.com/api/card-list*' => Http::sequence()
            ->push(['data' => [fakeCardEntry()], 'meta' => ['pagination' => ['pageCount' => 2]]])
            ->push(['data' => [fakeCardEntry(['attributes' => ['cardUid' => '1111111111', 'cardNumber' => 12]])], 'meta' => ['pagination' => ['pageCount' => 2]]]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob())->handle(app(TelegramService::class), app(CardImageDownloader::class));

    Http::assertSentCount(2);
    expect(Card::count())->toBe(2);
});

it('registra un SystemError su dati malformati invece di fermare lo scan', function () {
    Http::fake([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [['id' => 1, 'attributes' => ['cardUid' => null /* campo obbligatorio mancante, forza l\'eccezione */]]],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob())->handle(app(TelegramService::class), app(CardImageDownloader::class));

    expect(SystemError::count())->toBeGreaterThan(0);
});

it('invia la mail agli admin quando ci sono errori o carte gia\' presenti', function () {
    Card::factory()->create(['cid' => '2579145458']); // gia' presente, l'API la rispedisce
    Http::fake([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob())->handle(app(TelegramService::class), app(CardImageDownloader::class));

    Mail::assertQueued(AdminScanReportEmail::class);
});