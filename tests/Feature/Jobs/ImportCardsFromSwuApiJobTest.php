<?php

use App\Jobs\ImportCardsFromSwuApiJob;
use App\Mail\AdminScanReportEmail;
use App\Mail\NewCardsEmail;
use App\Models\Card;
use App\Models\Expansion;
use App\Models\SystemError;
use App\Models\User;
use App\Services\CardImageDownloader;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Spatie\Permission\Models\Role;

// Fixture minima ma fedele alla struttura reale confermata su test.json (Strapi: data[].attributes,
// relazioni annidate come attributes.expansion.data.attributes.code). Un helper tipo cardFixture(['cardUid' => ...])
// che parta da questo scheletro e sovrascriva solo i campi che cambiano evita di ripeterlo in ogni test.

/** Simula l'API SWU e risponde con un PNG finto a qualunque altro URL (immagini CDN). */
function fakeSwuHttp(array $routes, array $variants = []): void
{
    Storage::fake('public');
    Http::fake([
        'admin.starwarsunlimited.com/api/card-list*notNull*' => Http::response([
            'data' => $variants,
            'meta' => ['pagination' => ['pageCount' => $variants === [] ? 0 : 1]],
        ]),
    ] + $routes + ['*' => Http::response('fake-image', 200, ['Content-Type' => 'image/png'])]);
}
function fakeCardEntry(array $overrides = []): array
{
    static $base = null;

    if ($base === null) {
        $json = Storage::disk('local')->get('api-example-result.json');
        $all = json_decode($json, true);
        $first = reset($all);           // primo URL come chiave
        $base = $first['data'][0];     // prima card: Luke Skywalker (id 7)
        $base['attributes']['publishedAt'] ??= '2025-01-01T00:00:00.000Z';
    }

    return array_replace_recursive($base, $overrides);
}

/**
 * A variant entry: same skeleton as the original, with its own cardUid/number, the original it points to and its variant type
 * Una variante: stesso scheletro dell'originale, con cardUid e numero propri, l'originale a cui rimanda e il tipo di variante
 */
function fakeVariantEntry(string $cid, int $number, string $originalUid, string $type, string $variantId): array
{
    return fakeCardEntry(['attributes' => [
        'cardUid' => $cid,
        'cardNumber' => $number,
        'variantOf' => ['data' => ['attributes' => ['cardUid' => $originalUid]]],
        'variantTypes' => ['data' => [['attributes' => ['name' => $type, 'variantId' => $variantId]]]],
    ]]);
}

it('crea le carte nuove ricevute dall\'API', function () {
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [
                fakeCardEntry(),
                fakeCardEntry(['attributes' => ['cardUid' => '9999999999', 'cardNumber' => 6, 'title' => 'Leia Organa']]),
            ],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    User::factory()->create();
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    expect(Card::count())->toBe(2);
    Mail::assertQueued(NewCardsEmail::class);
});

it('pagina correttamente su piu\' pagine', function () {
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::sequence()
            ->push(['data' => [fakeCardEntry()], 'meta' => ['pagination' => ['pageCount' => 2]]])
            ->push(['data' => [fakeCardEntry(['attributes' => ['cardUid' => '1111111111', 'cardNumber' => 12]])], 'meta' => ['pagination' => ['pageCount' => 2]]])
            // consumato dalla richiesta della fase varianti (la risposta vera arriva da fakeSwuHttp, `*notNull*`)
            ->push(['data' => [], 'meta' => ['pagination' => ['pageCount' => 0]]]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    expect(Http::recorded(fn ($request) => str_contains($request->url(), 'card-list')))->toHaveCount(3); // 2 originali + 1 varianti
    expect(Card::count())->toBe(2);
});

it('registra un SystemError su dati malformati invece di fermare lo scan', function () {
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [['id' => 1, 'attributes' => ['cardUid' => null /* campo obbligatorio mancante, forza l\'eccezione */]]],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    expect(SystemError::count())->toBeGreaterThan(0);
});

it('non manda la mail agli admin per una carta gia\' presente', function () {
    User::factory()->create()->assignRole(Role::findOrCreate('admin'));
    Expansion::create(['expansion' => 'SOR', 'rotation' => '0']);
    Card::create([
        'cid' => '2579145458', 'expansion' => 'SOR', 'number' => 5,
        'name' => 'Luke Skywalker', 'type' => 'Leader', 'rarity' => 'Speciale',
    ]); // se la migration richiede altri campi non nullable, aggiungili qui
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    Mail::assertNotQueued(AdminScanReportEmail::class);
});

it('manda la mail agli admin quando una carta va in errore e il report si renderizza', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [['id' => 1, 'attributes' => ['cardUid' => null]]],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    Mail::assertQueued(AdminScanReportEmail::class, fn ($mail) => $mail->hasTo($admin->email)
        && $mail->errors->first() instanceof SystemError);
    expect((new AdminScanReportEmail(SystemError::all()))->render())->toContain('cardUid mancante nel payload');
});

it('renderizza il report admin anche con errori senza chiave error nel contesto', function () {
    $error = SystemError::create([
        'source' => 'test',
        'message' => "Carta X gia' presente, dati aggiornati",
        'status' => SystemError::STATUS_IGNORED,
        'context' => ['card' => ['name' => 'X']],
    ]);

    $html = (new AdminScanReportEmail(collect([$error])))->render();

    expect($html)->toContain('Carta X');
});

it('riprende da una pagina con cards:scan --page', function () {
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()],
            'meta' => ['pagination' => ['pageCount' => 2]],
        ]),
    ]);
    Mail::fake();

    Artisan::call('cards:scan', ['--page' => 2]);

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'pagination[page]=2'));
    Http::assertNotSent(fn ($request) => str_contains(urldecode($request->url()), '[$null]=true')
        && str_contains(urldecode($request->url()), 'pagination[page]=1'));
});

it('non avvia due scan insieme', function () {
    fakeSwuHttp([]);
    Cache::put(ImportCardsFromSwuApiJob::LOCK_KEY, 'un-altro-scan', now()->addHour());

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    expect(Card::count())->toBe(0);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'card-list'));
});

it('lancia un errore se l\'API risponde con un 5xx invece di chiudere lo scan', function () {
    Sleep::fake();
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response('Bad Gateway', 502),
    ]);
    Mail::fake();

    expect(fn () => (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class)))
        ->toThrow(RuntimeException::class, 'richiesta API fallita (502)');
});

it('failed() salva un SystemError con la pagina e libera il lock', function () {
    $job = new ImportCardsFromSwuApiJob(4);
    Cache::put(ImportCardsFromSwuApiJob::LOCK_KEY, $job->runId, now()->addHour());

    $job->failed(new RuntimeException('boom'));

    expect(SystemError::where('message', 'like', '%pagina 4%')->exists())->toBeTrue();
    expect(Cache::has(ImportCardsFromSwuApiJob::LOCK_KEY))->toBeFalse();
});

it('limita l\'elenco delle carte nella mail ma ne riporta il totale', function () {
    User::factory()->create();
    $entries = collect(range(1, 105))->map(fn ($i) => fakeCardEntry(['attributes' => ['cardUid' => "uid{$i}", 'cardNumber' => $i]]))->all();
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => $entries,
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    Mail::assertQueued(NewCardsEmail::class, fn ($mail) => $mail->newCards->count() === ImportCardsFromSwuApiJob::EMAIL_CARDS_LIMIT
        && $mail->total === 105);
});

it('una seconda scansione non segnala come nuove le carte già importate', function () {
    User::factory()->create();
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    Mail::fake();
    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    $this->travel(2)->minutes(); // created_at ha la precisione dei secondi
    Mail::fake();              // azzera le mail registrate dal primo scan
    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    Mail::assertNotQueued(NewCardsEmail::class);
});

it('mostra nel report admin quanti errori ci sono oltre a quelli elencati', function () {
    $error = SystemError::create(['source' => 'test', 'message' => 'Carta X', 'context' => ['error_message' => 'boom']]);

    $html = (new AdminScanReportEmail(collect([$error]), 7))->render();

    expect($html)->toContain('7 errori')->toContain('Gestione errori');
});

it('assegna le promo dei primi set alle sottoespansioni stile swudb', function () {
    fakeSwuHttp(
        ['admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()], // originale SOR
            'meta' => ['pagination' => ['pageCount' => 1]],
        ])],
        [
            fakeVariantEntry('v-pr', 2, originalUid: '2579145458', type: 'Promo Prerelease', variantId: '04'),
            fakeVariantEntry('v-op', 2, originalUid: '2579145458', type: 'Weekly Play', variantId: '60'),
            fakeVariantEntry('v-sh', 2, originalUid: '2579145458', type: 'Store Showdown', variantId: '41'),
        ]
    );
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    $pr = Card::where('cid', 'v-pr')->firstOrFail();
    $op = Card::where('cid', 'v-op')->firstOrFail();
    $sh = Card::where('cid', 'v-sh')->firstOrFail();

    expect($pr->expansion)->toBe('SORPR')
        ->and($op->expansion)->toBe('SOROP')
        ->and($sh->expansion)->toBe('SORSH')
        ->and(Expansion::where('expansion', 'SOROP')->value('group_main_expansion'))->toBe('SOR');
});

it('cards:scan --phase=variants accoda la fase varianti', function () {
    Queue::fake();

    Artisan::call('cards:scan', ['--phase' => 'variants']);

    Queue::assertPushed(ImportCardsFromSwuApiJob::class, fn ($job) => $job->phase === ImportCardsFromSwuApiJob::PHASE_VARIANTS && $job->page === 1);
});

it('cards:scan rifiuta una fase sconosciuta', function () {
    Queue::fake();

    expect(Artisan::call('cards:scan', ['--phase' => 'boh']))->toBe(1);

    Queue::assertNothingPushed();
});

it('un job in coda serializzato prima del piano 14 riparte dalla fase originali', function () {
    $job = (new ReflectionClass(ImportCardsFromSwuApiJob::class))->newInstanceWithoutConstructor();

    expect($job->phase)->toBe(ImportCardsFromSwuApiJob::PHASE_ORIGINALS);
});
