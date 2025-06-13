<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Composition;
use App\Models\Deck;
use App\Models\User;

use DB;

use Illuminate\Database\Query\JoinClause;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

use Illuminate\Support\Facades\Auth;

class DecksController extends Controller{
    public function index(){
        $decks = [];
        if(auth()->check()){
            $decksUser = Deck::where("codUtente", auth()->user()->id)
                            ->where("nome", "!=", "Collezione")
                            ->get();
            foreach($decksUser as $deck){
                $deck->utente = User::where("id", $deck->codUtente)->first()->name;
                $decks[$deck->id] = $deck;
            }
        }
        $decksPublic = Deck::where("public", 1)->where("codUtente", "!=", Auth::user()!= null ? Auth::user()->id : -1)->orderBy("codUtente")->get();
        foreach($decksPublic as $deck){
            $deck->utente = User::where("id", $deck->codUtente)->first()->name;
            $deck->dirtyName = "$deck->nome di $deck->utente";
            $decks[$deck->id] = $deck;
        }
        return view("mazzi.index", ["decks" => $decks]);
    }

    public function show($user, $deck){
        // Verifica esistenza dell'utente
        if(User::where("name", $user)->first() == null){
            return view("errors.406");
        }

        // Verifica esistenza del mazzo
        if(Deck::where("nome", str_replace("+", " ", $deck))->first() == null){
            return view("errors.405");
        }

        // Reindirizza accesso alla collezione verso la route dedicata
        if (str_replace("+", " ", $deck) === "Collezione") {
            return redirect()->route('collezione');
        }
        
        // Se utente e mazzo esistono, procedi
        $proprietario = Auth::check() ? Auth()->user()->id == User::where("name", $user)->first()->id : false;
        
        // Recupera il mazzo
        $mazzo = Deck::where("nome", str_replace("+", " ", $deck))
                    ->where("codUtente", 
                        User::where("name", $user)
                        ->first()
                        ->id)
                    ->first();
        
        // Recupera le carte del mazzo
        $cards = DB::table('compositions')
            ->leftJoin('cards', function (JoinClause $join){
                $join->on('compositions.espansione', '=', 'cards.espansione')
                    ->on('compositions.numero', '=', 'cards.numero');
            })
            ->select('cards.*', 'compositions.copie')
            ->where('compositions.idMazzo', $mazzo->id)
            ->get();
        
        // Calcola il numero totale di carte e aggiunge gli snippet
        $copie = 0;
        foreach($cards as $card){
            $card->snippet = "$card->espansione-$card->numero - ".$card->nome.(strlen($card->titolo) > 0 ? ", ". strtoupper($card->titolo) : "");
            $copie += $card->copie;
        }
        
        // Recupera tutte le carte disponibili
        $carte = Card::select("espansione", "numero", "nome", "titolo", "maxCopie")->get();
        // return $carte;
        // Prepara i dati per la view utilizzando Livewire
        return view("mazzi.show", [
            "nome" => $mazzo->nome,
            "mazzo" => $cards,
            "user" => $user,
            "proprietario" => $proprietario,
            "deck" => $deck,
            "carte" => $carte,
            "size" => $copie,
        ]);
    }

    public function store(Request $request, $user, $deck){
        if(User::where("name", $user)->first() == null){
            return view("errors.406");
        }else if(Deck::where("nome", str_replace("+", " ", $deck))->first() == null){
            return view("errors.405");
        }else if($request->input("carte") != null){
            $mazzo = Deck::where("nome", str_replace("+", " ", $deck))
                        ->where("codUtente", 
                            User::where("name", $user)
                            ->first()
                            ->id)
                        ->first();
            foreach($request->input("carte") as $card => $value){
                try{
                    $value = explode("-", $value);
                    $card = explode("-", $card);
                    $operazione = $value[0];
                    $copie = $value[1];
                    $espansione = $card[0];
                    $numero = $card[1];
                    $composizione = Composition::where("idMazzo", $mazzo->id)
                    ->where("espansione", $espansione)
                    ->where("numero", $numero)
                    ->first();
                    $vars = [
                        "operazione" => $operazione,
                        "copie" => $copie,
                        "espansione" => $espansione,
                        "numero" => $numero,
                        "composizione" => $composizione
                    ];
                    if($operazione == "A"){
                        if($composizione == null){
                            $vars["if"] = "addToNull";
                            $composizione = new Composition();
                            $composizione->idMazzo = $mazzo->id;
                            $composizione->espansione = $espansione;
                            $composizione->numero = $numero;
                            $composizione->copie = $copie;
                            $composizione->id = $mazzo->id."-".$espansione."-".$numero;
                            $composizione->save();
                        }else{
                            $vars["if"] = "addToValue";
                            $composizione->copie += $copie;
                            $composizione->save();
                        }
                    }else if($operazione == "R"){
                        if($composizione == null){
                            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])->with("warning", "Non puoi rimuovere una carta che non hai nel mazzo");
                        }else{
                            if($composizione->copie - $copie <= 0){
                                $vars["if"] = "removeFromNull";
                                $composizione->delete();
                            }else{
                                $vars["if"] = "removeFromValue";
                                $composizione->copie -= $copie;
                                $composizione->save();
                            }
                        }
                    }
                }catch(\Exception $e){
                    $vars["error"] = "Errore durante il salvataggio del mazzo: ".$e->getMessage();
                    return $vars;
                }finally{
                    $vars["msg"] = "sono arrivato alla fine";
                }
            };
            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])->with("success", "Mazzo salvato con successo");
        }else{
            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])->with("warning", "Non hai aggiunto o rimosso nessuna carta");
        }
    }

    public function create(Request $request){
        if(auth()->check()){
            // Impedisce la creazione di mazzi chiamati "Collezione"
            if($request->input("nome") === "Collezione"){
                return redirect()->route("mazzi")->with("warning", "Il nome 'Collezione' è riservato");
            }

            if(Deck::where("nome", $request->input("nome"))->where("codUtente", Auth::user()->id)->first() == null){
                $mazzo = new Deck();
                $mazzo->nome = $request->input("nome");
                $mazzo->public = $request->input("public") == true;
                $mazzo->codUtente = Auth::user()->id;
                $mazzo->save();
                return redirect()->route("mazzo", ["user" => Auth::user()->name, "mazzo" => str_replace(" ", "+", $mazzo->nome)])->with("success", "Mazzo creato con successo");
            }else{
                return redirect()->route("mazzo", ["user" => Auth::user()->name, "mazzo" => str_replace(" ", "+", $request->input("nome"))])->with("warning", "Questo mazzo esiste già");
            }
        }
        return redirect()->route("login")->with("warning", "Devi essere loggato per visualizzare questa pagina");
    }

    public function collezione(){
        $user = Auth::user();

        // Cerca collezione esistente
        $collezione = Deck::where('codUtente', $user->id)
                         ->where('nome', 'Collezione')
                         ->first();

        // Se non esiste, creala
        if (!$collezione) {
            $collezione = new Deck();
            $collezione->nome = 'Collezione';
            $collezione->public = false;
            $collezione->codUtente = $user->id;
            $collezione->save();
        }

        // Recupera le carte della collezione
        $cards = DB::table('compositions')
            ->leftJoin('cards', function (JoinClause $join){
                $join->on('compositions.espansione', '=', 'cards.espansione')
                    ->on('compositions.numero', '=', 'cards.numero');
            })
            ->select('cards.*', 'compositions.copie')
            ->where('compositions.idMazzo', $collezione->id)
            ->get();

        // Calcola il numero totale di carte
        $totalCards = $cards->sum('copie');

        // Recupera tutte le carte disponibili (nessun filtro di default)
        $allCards = Card::all();

        // Applica l'ordinamento usando il metodo del controller
        if (!$allCards->isEmpty()) {
            $allCards = \App\Http\Controllers\CardsController::mergeSort($allCards);
        }

        // Debug: analisi delle carte e filtri
        $totalCardsInDb = Card::count();
        $cardsInCollezione = $cards->count();

        // Statistiche generali del database
        $maxCosto = Card::max('costo');
        $maxPotenza = Card::max('potenza');
        $maxVita = Card::max('vita');

        // Conta carte per range di valori
        $carteAltoValore = Card::where('costo', '>', 10)
                              ->orWhere('potenza', '>', 10)
                              ->orWhere('vita', '>', 10)
                              ->count();

        // Esempi di carte con valori alti
        $carteEsempio = Card::where('costo', '>', 10)
                           ->orWhere('potenza', '>', 10)
                           ->orWhere('vita', '>', 10)
                           ->take(5)
                           ->get()
                           ->map(function($card) {
                               return $card->snippet . " (C:{$card->costo}, P:{$card->potenza}, V:{$card->vita})";
                           })
                           ->toArray();

        // Aggiungi debug info aggiornato
        $debugInfo = [
            'total_cards_db' => $totalCardsInDb,
            'cards_displayed' => $allCards->count(),
            'cards_in_collezione' => $cardsInCollezione,
            'max_values' => [
                'costo' => $maxCosto,
                'potenza' => $maxPotenza,
                'vita' => $maxVita
            ],
            'high_value_cards_count' => $carteAltoValore,
            'sample_high_value_cards' => $carteEsempio,
            'livewire_status' => 'Filtri gestiti da SearchFilter component',
            'sort_applied' => !$allCards->isEmpty() ? 'mergeSort applicato' : 'nessun ordinamento'
        ];

        return view('collezione.index', [
            'collezione' => $cards,
            'totalCards' => $totalCards,
            'allCards' => $allCards,
            'collezioneId' => $collezione->id,
            'debugInfo' => $debugInfo
        ]);
    }

    public function updateCollezione(Request $request){
        $user = Auth::user();

        // Trova la collezione dell'utente
        $collezione = Deck::where('codUtente', $user->id)
                         ->where('nome', 'Collezione')
                         ->first();

        if (!$collezione) {
            return response()->json(['error' => 'Collezione non trovata'], 404);
        }

        $espansione = $request->input('espansione');
        $numero = $request->input('numero');
        $copie = $request->input('copie');

        // Trova la composizione esistente
        $composizione = Composition::where('idMazzo', $collezione->id)
                                  ->where('espansione', $espansione)
                                  ->where('numero', $numero)
                                  ->first();

        if ($copie <= 0) {
            // Rimuovi la carta dalla collezione
            if ($composizione) {
                $composizione->delete();
            }
        } else {
            if ($composizione) {
                // Aggiorna il numero di copie
                $composizione->copie = $copie;
                $composizione->save();
            } else {
                // Crea nuova composizione
                $composizione = new Composition();
                $composizione->idMazzo = $collezione->id;
                $composizione->espansione = $espansione;
                $composizione->numero = $numero;
                $composizione->copie = $copie;
                $composizione->id = $collezione->id."-".$espansione."-".$numero;
                $composizione->save();
            }
        }

        return response()->json(['success' => true]);
    }

    public function api($user, $nome, $public){
        return Deck::where("nome", "like", "%$nome%")
                ->where("codUtente",
                    User::where("name", "like", "%$user%")
                    ->first()
                    ->id)
                ->where("public", $public)
                ->get();
    }

    /**
     * Esporta un mazzo in formato TXT
     */
    public function exportTxt($user, $deck)
    {
        $deckData = $this->getDeckData($user, $deck);
        if (!$deckData) {
            return response('Mazzo non trovato', 404);
        }

        // Controllo autorizzazione
        if (!$this->canExportDeck($deckData['deck'])) {
            return response('Non autorizzato ad esportare questo mazzo', 403);
        }

        $content = $this->generateTxtContent($deckData);

        $filename = $this->sanitizeFilename($deckData['deck']->nome . '_' . $deckData['user']->name) . '.txt';

        return response($content)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Esporta un mazzo in formato JSON
     */
    public function exportJson($user, $deck)
    {
        $deckData = $this->getDeckData($user, $deck);
        if (!$deckData) {
            return response()->json(['error' => 'Mazzo non trovato'], 404);
        }

        // Controllo autorizzazione
        if (!$this->canExportDeck($deckData['deck'])) {
            return response()->json(['error' => 'Non autorizzato ad esportare questo mazzo'], 403);
        }

        $content = $this->generateJsonContent($deckData);

        $filename = $this->sanitizeFilename($deckData['deck']->nome . '_' . $deckData['user']->name) . '.json';

        return response($content)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Recupera i dati del mazzo per l'esportazione
     */
    private function getDeckData($user, $deck)
    {
        $userModel = User::where("name", $user)->first();
        if (!$userModel) {
            return null;
        }

        $deckModel = Deck::where("nome", str_replace("+", " ", $deck))
                         ->where("codUtente", $userModel->id)
                         ->first();
        if (!$deckModel) {
            return null;
        }

        // Recupera le carte del mazzo con le informazioni complete
        $cards = DB::table('compositions')
            ->leftJoin('cards', function (JoinClause $join) {
                $join->on('compositions.espansione', '=', 'cards.espansione')
                     ->on('compositions.numero', '=', 'cards.numero');
            })
            ->select('cards.*', 'compositions.copie')
            ->where('compositions.idMazzo', $deckModel->id)
            ->get();

        return [
            'user' => $userModel,
            'deck' => $deckModel,
            'cards' => $cards
        ];
    }

    /**
     * Genera il contenuto in formato TXT secondo il formato ufficiale
     */
    private function generateTxtContent($deckData)
    {
        $content = "";

        // Raggruppa le carte per tipo
        $leaders = [];
        $bases = [];
        $deck = [];
        $sideboard = []; // Per ora vuoto, ma preparato per future implementazioni

        foreach ($deckData['cards'] as $card) {
            $cardLine = $card->copie . " | " . $card->nome;
            if (!empty($card->titolo)) {
                $cardLine .= " | " . $card->titolo;
            }

            switch ($card->tipo) {
                case 'Leader':
                    $leaders[] = $cardLine;
                    break;
                case 'Base':
                    $bases[] = $cardLine;
                    break;
                default:
                    $deck[] = $cardLine;
                    break;
            }
        }

        // Sezione Leaders
        if (!empty($leaders)) {
            $content .= "Leaders\n";
            foreach ($leaders as $leader) {
                $content .= $leader . "\n";
            }
            $content .= "\n";
        }

        // Sezione Base
        if (!empty($bases)) {
            $content .= "Base\n";
            foreach ($bases as $base) {
                $content .= $base . "\n";
            }
            $content .= "\n";
        }

        // Sezione Deck
        if (!empty($deck)) {
            $content .= "Deck\n";
            foreach ($deck as $deckCard) {
                $content .= $deckCard . "\n";
            }
            $content .= "\n";
        }

        // Sezione Sideboard (per ora vuota ma preparata)
        if (!empty($sideboard)) {
            $content .= "Sideboard\n";
            foreach ($sideboard as $sideboardCard) {
                $content .= $sideboardCard . "\n";
            }
        }

        return trim($content);
    }

    /**
     * Genera il contenuto in formato JSON secondo il formato ufficiale
     */
    private function generateJsonContent($deckData)
    {
        $leader = null;
        $base = null;
        $deck = [];
        $sideboard = []; // Per ora vuoto, ma preparato per future implementazioni

        foreach ($deckData['cards'] as $card) {
            $cardData = [
                'id' => $card->espansione . '_' . $card->numero,
                'count' => (int) $card->copie
            ];

            switch ($card->tipo) {
                case 'Leader':
                    $leader = $cardData;
                    break;
                case 'Base':
                    $base = $cardData;
                    break;
                default:
                    $deck[] = $cardData;
                    break;
            }
        }

        $deckExport = [
            'metadata' => [
                'name' => $deckData['deck']->nome,
                'author' => $deckData['user']->name
            ]
        ];

        // Aggiungi leader se presente
        if ($leader) {
            $deckExport['leader'] = $leader;
        }

        // Aggiungi base se presente
        if ($base) {
            $deckExport['base'] = $base;
        }

        // Aggiungi deck se presente
        if (!empty($deck)) {
            $deckExport['deck'] = $deck;
        }

        // Aggiungi sideboard se presente (per ora vuoto ma preparato)
        if (!empty($sideboard)) {
            $deckExport['sideboard'] = $sideboard;
        }

        return json_encode($deckExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sanitizza il nome del file per l'esportazione
     */
    private function sanitizeFilename($filename)
    {
        // Rimuove caratteri non validi per i nomi file
        $filename = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $filename);
        // Sostituisce spazi con underscore
        $filename = str_replace(' ', '_', $filename);
        // Rimuove underscore multipli
        $filename = preg_replace('/_+/', '_', $filename);
        // Rimuove underscore all'inizio e alla fine
        $filename = trim($filename, '_');

        return $filename;
    }

    /**
     * Verifica se l'utente corrente può esportare il mazzo
     */
    private function canExportDeck($deck)
    {
        // Se il mazzo è pubblico, chiunque può esportarlo
        if ($deck->public) {
            return true;
        }

        // Se l'utente non è autenticato, non può esportare mazzi privati
        if (!Auth::check()) {
            return false;
        }

        // Se l'utente è il proprietario del mazzo, può esportarlo
        if (Auth::user()->id === $deck->codUtente) {
            return true;
        }

        return false;
    }
}