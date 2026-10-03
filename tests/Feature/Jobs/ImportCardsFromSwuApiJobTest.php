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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

// Fixture minima ma fedele alla struttura reale confermata su test.json (Strapi: data[].attributes,
// relazioni annidate come attributes.expansion.data.attributes.code). Un helper tipo cardFixture(['cardUid' => ...])
// che parta da questo scheletro e sovrascriva solo i campi che cambiano evita di ripeterlo in ogni test.

/** Simula l'API SWU e risponde con un PNG finto a qualunque altro URL (immagini CDN). */
function fakeSwuHttp(array $routes): void
{
    Storage::fake('public');
    Http::fake($routes + ['*' => Http::response('fake-image', 200, ['Content-Type' => 'image/png'])]);
}
function fakeCardEntry(array $overrides = []): array
{
    static $base = null;

    if ($base === null) {
        $json = Storage::disk('local')->get('api-example-result.json');
        $all = json_decode($json, true);
        $first = reset($all);           // primo URL come chiave
        $base = $first['data'][0];     // prima card: Luke Skywalker (id 7)
    }

    return array_replace_recursive($base, $overrides);
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
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    expect(Card::count())->toBe(2);
    Mail::assertQueued(NewCardsEmail::class);
});

it('pagina correttamente su piu\' pagine', function () {
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::sequence()
            ->push(['data' => [fakeCardEntry()], 'meta' => ['pagination' => ['pageCount' => 2]]])
            ->push(['data' => [fakeCardEntry(['attributes' => ['cardUid' => '1111111111', 'cardNumber' => 12]])], 'meta' => ['pagination' => ['pageCount' => 2]]]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    expect(Http::recorded(fn ($request) => str_contains($request->url(), 'card-list')))->toHaveCount(2);
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
