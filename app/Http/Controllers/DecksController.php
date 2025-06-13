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

    /**
     * Mostra la pagina di importazione mazzi
     */
    public function showImport()
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('warning', 'Devi essere loggato per importare mazzi');
        }

        return view('mazzi.import');
    }

    /**
     * Importa un mazzo da file
     */
    public function importFromFile(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Non autorizzato'], 401);
        }

        $request->validate([
            'file' => 'required|file|mimes:txt,json|max:2048',
            'deck_name' => 'required|string|max:500',
            'public' => 'boolean'
        ]);

        try {
            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());
            $extension = $file->getClientOriginalExtension();

            $result = $this->processDeckImport($content, $extension, $request->input('deck_name'), $request->boolean('public'));

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Mazzo importato con successo',
                    'deck_url' => route('mazzo', ['user' => Auth::user()->name, 'mazzo' => str_replace(' ', '+', $result['deck_name'])])
                ]);
            } else {
                return response()->json(['error' => $result['error']], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Errore durante l\'importazione: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Importa un mazzo da URL
     */
    public function importFromUrl(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Non autorizzato'], 401);
        }

        $request->validate([
            'url' => 'required|url',
            'deck_name' => 'required|string|max:500',
            'public' => 'boolean'
        ]);

        try {
            $url = $request->input('url');

            // Controlla se è un URL di SWUDB e convertilo all'API
            $apiUrl = $this->convertSwudbUrl($url);

            // Debug: log degli URL
            \Log::info('Import URL Debug', [
                'original_url' => $url,
                'api_url' => $apiUrl,
                'is_swudb' => str_contains($url, 'swudb.com')
            ]);

            // Scarica il contenuto dall'URL usando cURL
            $content = $this->downloadFromUrl($apiUrl);

            if ($content === false) {
                \Log::error('Download failed', ['url' => $apiUrl]);
                return response()->json([
                    'error' => 'Impossibile scaricare il file dall\'URL fornito. ' .
                              'Verifica che l\'URL sia corretto e accessibile.',
                    'debug' => [
                        'original_url' => $url,
                        'api_url' => $apiUrl
                    ]
                ], 400);
            }

            // Debug: log del contenuto ricevuto
            \Log::info('Content received', [
                'content_length' => strlen($content),
                'content_start' => substr($content, 0, 100),
                'is_html' => str_starts_with(trim($content), '<!doctype') || str_starts_with(trim($content), '<html')
            ]);

            // Debug: controlla se il contenuto è HTML invece di JSON
            if (str_starts_with(trim($content), '<!doctype') || str_starts_with(trim($content), '<html')) {
                return response()->json([
                    'error' => 'L\'URL ha restituito una pagina HTML invece dei dati del mazzo. ' .
                              'Verifica che l\'URL sia corretto.',
                    'debug' => [
                        'content_type' => 'HTML',
                        'content_preview' => substr($content, 0, 200),
                        'original_url' => $url,
                        'api_url' => $apiUrl
                    ]
                ], 400);
            }

            // Determina il formato dal contenuto o dall'URL
            $extension = $this->detectFileFormat($content, $apiUrl);

            // Se è formato SWUDB, convertilo al formato ufficiale
            if ($this->isSwudbFormat($content)) {
                $content = $this->convertSwudbToOfficial($content);
                $extension = 'json';
            }

            $result = $this->processDeckImport($content, $extension, $request->input('deck_name'), $request->boolean('public'));

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Mazzo importato con successo',
                    'deck_url' => route('mazzo', ['user' => Auth::user()->name, 'mazzo' => str_replace(' ', '+', $result['deck_name'])])
                ]);
            } else {
                return response()->json(['error' => $result['error']], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Errore durante l\'importazione: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Processa l'importazione del mazzo
     */
    private function processDeckImport($content, $format, $deckName, $isPublic)
    {
        try {
            // Verifica che il mazzo non esista già
            if (Deck::where('nome', $deckName)->where('codUtente', Auth::user()->id)->exists()) {
                return ['success' => false, 'error' => 'Un mazzo con questo nome esiste già'];
            }

            // Impedisce la creazione di mazzi chiamati "Collezione"
            if ($deckName === "Collezione") {
                return ['success' => false, 'error' => 'Il nome "Collezione" è riservato'];
            }

            // Parse del contenuto in base al formato
            if ($format === 'json') {
                $parsedData = $this->parseJsonDeck($content);
            } else {
                $parsedData = $this->parseTxtDeck($content);
            }

            if (!$parsedData['success']) {
                return $parsedData;
            }

            // Crea il mazzo
            $deck = new Deck();
            $deck->nome = $deckName;
            $deck->public = $isPublic;
            $deck->codUtente = Auth::user()->id;
            $deck->save();

            // Aggiunge le carte al mazzo
            $addedCards = 0;
            $errors = [];

            foreach ($parsedData['cards'] as $cardData) {
                $result = $this->addCardToDeck($deck->id, $cardData);
                if ($result['success']) {
                    $addedCards++;
                } else {
                    $errors[] = $result['error'];
                }
            }

            if ($addedCards === 0) {
                // Se nessuna carta è stata aggiunta, elimina il mazzo
                $deck->delete();
                return ['success' => false, 'error' => 'Nessuna carta valida trovata nel file. Errori: ' . implode(', ', $errors)];
            }

            return [
                'success' => true,
                'deck_name' => $deckName,
                'added_cards' => $addedCards,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Errore durante il processing: ' . $e->getMessage()];
        }
    }

    /**
     * Rileva il formato del file dal contenuto o URL
     */
    private function detectFileFormat($content, $url = '')
    {
        // Prova a decodificare come JSON
        $jsonData = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
            return 'json';
        }

        // Controlla l'estensione dell'URL
        if (str_ends_with(strtolower($url), '.json')) {
            return 'json';
        }

        // Default a TXT
        return 'txt';
    }

    /**
     * Parse di un mazzo in formato JSON
     */
    private function parseJsonDeck($content)
    {
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'File JSON non valido'];
        }

        $cards = [];

        try {
            // Parse leader
            if (isset($data['leader'])) {
                $cards[] = $this->parseCardFromId($data['leader']['id'], $data['leader']['count']);
            }

            // Parse base
            if (isset($data['base'])) {
                $cards[] = $this->parseCardFromId($data['base']['id'], $data['base']['count']);
            }

            // Parse deck
            if (isset($data['deck']) && is_array($data['deck'])) {
                foreach ($data['deck'] as $card) {
                    $cards[] = $this->parseCardFromId($card['id'], $card['count']);
                }
            }

            // Parse sideboard (se presente)
            if (isset($data['sideboard']) && is_array($data['sideboard'])) {
                foreach ($data['sideboard'] as $card) {
                    $cards[] = $this->parseCardFromId($card['id'], $card['count']);
                }
            }

            // Filtra le carte non valide
            $validCards = array_filter($cards, function($card) {
                return $card !== null;
            });

            return ['success' => true, 'cards' => $validCards];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Errore nel parsing JSON: ' . $e->getMessage()];
        }
    }

    /**
     * Parse di un mazzo in formato TXT
     */
    private function parseTxtDeck($content)
    {
        $lines = explode("\n", $content);
        $cards = [];
        $currentSection = null;

        try {
            foreach ($lines as $line) {
                $line = trim($line);

                // Salta righe vuote
                if (empty($line)) {
                    continue;
                }

                // Identifica le sezioni
                if (in_array($line, ['Leaders', 'Base', 'Deck', 'Sideboard'])) {
                    $currentSection = $line;
                    continue;
                }

                // Parse delle carte
                if ($currentSection && preg_match('/^(\d+)\s*\|\s*([^|]+)(?:\s*\|\s*(.+))?$/', $line, $matches)) {
                    $count = (int) $matches[1];
                    $name = trim($matches[2]);
                    $title = isset($matches[3]) ? trim($matches[3]) : '';

                    $card = $this->findCardByNameAndTitle($name, $title);
                    if ($card) {
                        $cards[] = [
                            'espansione' => $card->espansione,
                            'numero' => $card->numero,
                            'count' => $count
                        ];
                    }
                }
            }

            return ['success' => true, 'cards' => $cards];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Errore nel parsing TXT: ' . $e->getMessage()];
        }
    }

    /**
     * Parse di una carta dall'ID formato {espansione}_{numero}
     */
    private function parseCardFromId($cardId, $count)
    {
        if (preg_match('/^([A-Z0-9]+)_(\d+)$/', $cardId, $matches)) {
            $espansione = $matches[1];
            $numero = (int) $matches[2];

            // Verifica che la carta esista nel database
            $card = Card::where('espansione', $espansione)->where('numero', $numero)->first();
            if ($card) {
                return [
                    'espansione' => $espansione,
                    'numero' => $numero,
                    'count' => (int) $count
                ];
            }
        }

        return null;
    }

    /**
     * Trova una carta per nome e titolo
     */
    private function findCardByNameAndTitle($name, $title = '')
    {
        $query = Card::where('nome', $name);

        if (!empty($title)) {
            $query->where('titolo', $title);
        } else {
            $query->where(function($q) {
                $q->where('titolo', '')->orWhereNull('titolo');
            });
        }

        return $query->first();
    }

    /**
     * Aggiunge una carta al mazzo
     */
    private function addCardToDeck($deckId, $cardData)
    {
        try {
            $espansione = $cardData['espansione'];
            $numero = $cardData['numero'];
            $count = $cardData['count'];

            // Verifica che la carta esista
            $card = Card::where('espansione', $espansione)->where('numero', $numero)->first();
            if (!$card) {
                return ['success' => false, 'error' => "Carta {$espansione}-{$numero} non trovata"];
            }

            // Verifica il limite di copie
            if ($count > $card->maxCopie) {
                $count = $card->maxCopie;
            }

            // Crea o aggiorna la composizione
            $compositionId = $deckId . '-' . $espansione . '-' . $numero;

            $composition = Composition::where('id', $compositionId)->first();
            if ($composition) {
                $composition->copie += $count;
                if ($composition->copie > $card->maxCopie) {
                    $composition->copie = $card->maxCopie;
                }
                $composition->save();
            } else {
                $composition = new Composition();
                $composition->id = $compositionId;
                $composition->idMazzo = $deckId;
                $composition->espansione = $espansione;
                $composition->numero = $numero;
                $composition->copie = $count;
                $composition->save();
            }

            return ['success' => true];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Errore aggiunta carta: ' . $e->getMessage()];
        }
    }

    /**
     * Converte un URL di SWUDB in URL API
     */
    private function convertSwudbUrl($url)
    {
        // Pattern per URL SWUDB: https://swudb.com/deck/{deckId}
        if (preg_match('/swudb\.com\/deck\/([a-zA-Z0-9]+)/', $url, $matches)) {
            $deckId = $matches[1];
            return "https://swudb.com/api/deck/{$deckId}";
        }

        return $url;
    }

    /**
     * Testa la connessione a SWUDB
     */
    public function testSwudbConnection()
    {
        try {
            $testUrl = 'https://swudb.com/api/deck/HBzjsPUBBGYTt';

            $content = $this->downloadFromUrl($testUrl);

            if ($content === false) {
                return response()->json([
                    'success' => false,
                    'error' => 'Connessione fallita',
                    'details' => 'Impossibile scaricare da SWUDB'
                ]);
            }

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'error' => 'Risposta non JSON',
                    'content_preview' => substr($content, 0, 200)
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Connessione SWUDB OK',
                'deck_name' => $data['deckName'] ?? 'N/A',
                'content_length' => strlen($content)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Eccezione: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Verifica se il contenuto è in formato SWUDB
     */
    private function isSwudbFormat($content)
    {
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        // Controlla se ha la struttura tipica di SWUDB
        return isset($data['deckId']) && isset($data['leader']) && isset($data['shuffledDeck']);
    }

    /**
     * Converte il formato SWUDB al formato ufficiale
     */
    private function convertSwudbToOfficial($content)
    {
        $swudbData = json_decode($content, true);

        $officialFormat = [
            'metadata' => [
                'name' => $swudbData['deckName'] ?? 'Imported Deck',
                'author' => $swudbData['authorName'] ?? 'Unknown'
            ]
        ];

        // Converte leader
        if (isset($swudbData['leader'])) {
            $officialFormat['leader'] = [
                'id' => $swudbData['leader']['defaultExpansionAbbreviation'] . '_' . $swudbData['leader']['defaultCardNumber'],
                'count' => 1
            ];
        }

        // Converte base
        if (isset($swudbData['base'])) {
            $officialFormat['base'] = [
                'id' => $swudbData['base']['defaultExpansionAbbreviation'] . '_' . $swudbData['base']['defaultCardNumber'],
                'count' => 1
            ];
        }

        // Converte deck
        $deck = [];
        if (isset($swudbData['shuffledDeck'])) {
            foreach ($swudbData['shuffledDeck'] as $cardEntry) {
                if ($cardEntry['count'] > 0) { // Solo carte nel deck principale
                    $deck[] = [
                        'id' => $cardEntry['card']['defaultExpansionAbbreviation'] . '_' . $cardEntry['card']['defaultCardNumber'],
                        'count' => $cardEntry['count']
                    ];
                }
            }
        }

        if (!empty($deck)) {
            $officialFormat['deck'] = $deck;
        }

        // Converte sideboard (se presente)
        $sideboard = [];
        if (isset($swudbData['shuffledDeck'])) {
            foreach ($swudbData['shuffledDeck'] as $cardEntry) {
                if ($cardEntry['sideboardCount'] > 0) {
                    $sideboard[] = [
                        'id' => $cardEntry['card']['defaultExpansionAbbreviation'] . '_' . $cardEntry['card']['defaultCardNumber'],
                        'count' => $cardEntry['sideboardCount']
                    ];
                }
            }
        }

        if (!empty($sideboard)) {
            $officialFormat['sideboard'] = $sideboard;
        }

        return json_encode($officialFormat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Scarica contenuto da URL usando cURL
     */
    private function downloadFromUrl($url)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'UnlimitedDB.net Deck Importer',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/plain, */*',
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: no-cache'
            ]
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        // Debug: log delle informazioni cURL
        \Log::info('cURL Debug', [
            'url' => $url,
            'http_code' => $httpCode,
            'content_type' => $contentType,
            'error' => $error,
            'content_length' => $content ? strlen($content) : 0,
            'content_start' => $content ? substr($content, 0, 100) : 'No content'
        ]);

        if ($content === false || !empty($error) || $httpCode >= 400) {
            \Log::error('cURL failed', [
                'url' => $url,
                'http_code' => $httpCode,
                'error' => $error
            ]);
            return false;
        }

        return $content;
    }
}