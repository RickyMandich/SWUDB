<?php

use App\Events\CardReceived;
use App\Http\Controllers\CardsController;
use App\Http\Controllers\DecksController;
use App\Http\Controllers\JobController;
use App\Models\Card;

use App\Events\MessageCreated;

use App\Models\Deck;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Route;

Route::get('/', function(){return view('index');})->name("index");

Route::get("query", function(Request $request){
    if(!Auth::admin()){
        return view("errors.403");
    }
    $get = $request->all();
    if(isset($get["query"])){
        $query = $get["query"];
    }else{
        $query = "SELECT * FROM cards limit 10";
    }
    return view("query", ["result" => DB::select($query), "query"=>$query]);
})->name("query");

Route::get('/carte/{espansione?}', [CardsController::class, 'index'])->name("carte");

Route::get('/carta/{espansione}/{numero}', [CardsController::class, 'show'])->name("carta");

Route::get('/update', [CardsController::class, 'startImport'])->name("carte.update");

Route::get('/dispatchBatch', [CardsController::class, 'dispatchBatch'])->name("carte.dispatchBatch");

Route::get('/sendBatch', [CardsController::class, 'sendBatch'])->name("carte.sendBatch");

Route::get('/mazzi', [DecksController::class, 'index'])->name("mazzi");

Route::get('/mazzo/{user}/{mazzo}', [DecksController::class, 'show'])->name("mazzo");

Route::post('/mazzo/{user}/{mazzo}/save', [DecksController::class, 'store'])->name("mazzo.save");

Route::post('/mazzo/create', [DecksController::class, 'create'])->name("mazzo.create");

Route::get("/api/carta/{espansione}/{numero}", [CardsController::class, 'api'])->name("api.carta");

Route::get("/api/carte/{espansione}", [CardsController::class, 'apis'])->name("api.carte");

Route::get("/api/mazzi/{user}/{nome}/{public}", [DecksController::class, 'api'])->name("api.mazzi");

Route::get("/message/{message}", function($message){
    MessageCreated::dispatch($message);
})->name("message");

Route::fallback(function () {
    return view('errors.404');
});

Auth::routes();

Route::get("/migrate", function(){
    if(!Auth::admin()){
        return view("errors.403");
    }
    Artisan::call("migrate:fresh");
    return "Migrated";
});

Route::get('/dashboard', [App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');

Route::get('/users', function(){
    return redirect()->route("query", ["query" => "SELECT * FROM users"]);
});

Route::get('/docs/tos', function(){
    return view("docs.termOfService");
})->name("docs.tos");

Route::get('/docs/privacy', function(){
    return view("docs.privacy");
})->name("docs.privacy");

Route::get("/job/AddCard", [JobController::class, 'addCard'])->name("job.addCard");

Route::get("/job/SendMessage", [JobController::class, 'sendMessage'])->name("job.sendMessage");
route::get("test", function(Request $request){
    $get = $request->all();
    if(isset($get["espansione1"]) and isset($get["numero1"]) and isset($get["espansione2"]) and isset($get["numero2"])){
        $cards = Card::where(function($query) use ($get) {
            $query->where('espansione', $get['espansione1'])
                ->where('numero', $get['numero1']);
        })->orWhere(function($query) use ($get) {
            $query->where('espansione', $get['espansione2'])
                ->where('numero', $get['numero2']);
        })->get()->toArray();
        ob_start();
        return var_dump($cards);
        CardsController::mergeSort($cards, true);
        $output = ob_get_clean();
        return var_dump($output);
    }else{
        return "Please provide espansione1, numero1, espansione2, and numero2 in the query parameters.";
    }
})->name("test");