<?php

namespace App\Http\Controllers;

use App\Http\Controllers\CardsController;

use App\Models\Card;
use App\Models\Composition;
use App\Models\Deck;
use App\Models\User;

use Illuminate\Database\Query\JoinClause;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DecksController extends Controller{
    /**
     * Display a listing of all accessible decks (user's own + public decks)
     * Mostra l'elenco di tutti i mazzi accessibili (propri dell'utente + mazzi pubblici)
     *
     * This method retrieves and displays both the authenticated user's private decks
     * and all public decks from other users, excluding collection decks.
     *
     * @return \Illuminate\View\View The decks index view with all accessible decks
     */
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

    /**
     * Display a specific deck with its cards and management interface
     * Mostra un mazzo specifico con le sue carte e l'interfaccia di gestione
     *
     * This method handles deck viewing with comprehensive validation:
     * - Verifies user and deck existence
     * - Redirects collection access to dedicated route
     * - Determines ownership permissions
     * - Retrieves deck cards with composition data
     * - Calculates total card count and prepares data for Livewire components
     *
     * @param string $user The username of the deck owner
     * @param string $deck The deck name (URL encoded with + for spaces)
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse The deck view or error/redirect
     */
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
        
        // Recupera le carte del mazzo usando le relazioni Eloquent
        $compositions = $mazzo->compositions()->with('card')->get();

        // Trasforma le composizioni in un formato compatibile con il codice esistente
        $cards = $compositions->map(function($composition) {
            if ($composition->card) {
                $card = $composition->card;
                $card->copie = $composition->copie;
                $card->snippet = $card->snippet; // Usa l'accessor definito nel modello
                return $card;
            }
            return null;
        })->filter(); // Rimuove i null

        // Calcola il numero totale di carte
        $copie = $compositions->sum('copie');

        // Applica l'ordinamento usando il metodo del controller
        if (!$cards->isEmpty()) {
            // Converte la Collection di modelli Eloquent in array associativi per il sorting
            $cardsArray = $cards->map(function($card) {
                // Usa toArray() per preservare tutti gli attributi del modello
                if (is_object($card) && method_exists($card, 'toArray')) {
                    $cardArray = $card->toArray();
                    // Preserva il campo copie che è stato aggiunto dinamicamente
                    if (isset($card->copie)) {
                        $cardArray['copie'] = $card->copie;
                    }
                    return $cardArray;
                } else {
                    return (array) $card;
                }
            });
            $cards = CardsController::mergeSort($cardsArray);
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
            "deckObject" => $mazzo, // Pass the full Deck object for version info
            "carte" => $carte,
            "size" => $copie,
        ]);
    }

    /**
     * Store/update deck composition by processing card addition/removal operations
     * Salva/aggiorna la composizione del mazzo elaborando operazioni di aggiunta/rimozione carte
     *
     * This method processes bulk card operations for deck management:
     * - Validates user and deck existence
     * - Processes card operations in format: "operation-count" for each card
     * - Supports "A" (Add) and "R" (Remove) operations
     * - Handles composition creation, updates, and deletion
     * - Provides comprehensive error handling and user feedback
     *
     * @param Request $request HTTP request containing 'carte' array with card operations
     * @param string $user The username of the deck owner
     * @param string $deck The deck name (URL encoded)
     * @return \Illuminate\Http\RedirectResponse Redirect with success/error messages
     */
    public function store(Request $request, $user, $deck){
        if(User::where("name", $user)->first() == null){
            return redirect()->route("mazzi")->with("error", "Utente non trovato");
        }else if(Deck::where("nome", str_replace("+", " ", $deck))->first() == null){
            return redirect()->route("mazzi")->with("error", "Mazzo non trovato");
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
                    return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])->with("error", "Errore durante il salvataggio del mazzo: ".$e->getMessage());
                }finally{
                    $vars["msg"] = "sono arrivato alla fine";
                }
            };

            // Increment deck version after successful modifications
            // Incrementa la versione del mazzo dopo modifiche riuscite
            $mazzo->incrementVersion();

            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])->with("success", "Mazzo salvato con successo (versione " . $mazzo->getVersionString() . ")");
        }else{
            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])->with("warning", "Non hai aggiunto o rimosso nessuna carta");
        }
    }

    /**
     * Create a new deck for the authenticated user
     * Crea un nuovo mazzo per l'utente autenticato
     *
     * This method handles deck creation with validation:
     * - Requires user authentication
     * - Prevents creation of decks named "Collezione" (reserved)
     * - Checks for duplicate deck names per user
     * - Creates deck with specified name and visibility
     *
     * @param Request $request HTTP request containing 'nome' and 'public' parameters
     * @return \Illuminate\Http\RedirectResponse Redirect to deck view or error page
     */
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

    /**
     * Delete a deck owned by the authenticated user
     * Elimina un mazzo di proprietà dell'utente autenticato
     *
     * This method handles deck deletion with proper authorization:
     * - Verifies user authentication
     * - Validates deck ownership
     * - Prevents deletion of collection decks
     * - Removes deck and all associated compositions
     *
     * @param string $user The username of the deck owner
     * @param string $deck The deck name (URL encoded)
     * @return \Illuminate\Http\RedirectResponse Redirect with success/error messages
     */
    public function destroy($user, $deck)
    {
        if (!auth()->check()) {
            return redirect()->route("login")->with("warning", "Devi essere loggato per eseguire questa azione");
        }

        $deckName = str_replace("+", " ", $deck);

        // Verifica che l'utente esista
        $targetUser = User::where("name", $user)->first();
        if (!$targetUser) {
            return redirect()->route("mazzi")->with("error", "Utente non trovato");
        }

        // Verifica che il mazzo esista e appartenga all'utente autenticato
        $mazzo = Deck::where("nome", $deckName)
                    ->where("codUtente", auth()->user()->id)
                    ->first();

        if (!$mazzo) {
            return redirect()->route("mazzi")->with("error", "Mazzo non trovato o non hai i permessi per eliminarlo");
        }

        // Impedisce l'eliminazione della collezione
        if ($deckName === "Collezione") {
            return redirect()->route("mazzi")->with("warning", "Non puoi eliminare la collezione");
        }

        try {
            // Elimina tutte le composizioni associate al mazzo
            Composition::where("idMazzo", $mazzo->id)->delete();

            // Elimina il mazzo
            $mazzo->delete();

            return redirect()->route("mazzi")->with("success", "Mazzo '$deckName' eliminato con successo");
        } catch (\Exception $e) {
            return redirect()->route("mazzi")->with("error", "Errore durante l'eliminazione del mazzo: " . $e->getMessage());
        }
    }

    /**
     * Toggle deck visibility between public and private
     * Cambia la visibilità del mazzo tra pubblico e privato
     *
     * This method handles deck visibility changes with proper authorization:
     * - Verifies user authentication and deck ownership
     * - Toggles the public/private status
     * - Prevents modification of collection decks
     *
     * @param string $user The username of the deck owner
     * @param string $deck The deck name (URL encoded)
     * @return \Illuminate\Http\RedirectResponse Redirect with success/error messages
     */
    public function toggleVisibility($user, $deck)
    {
        if (!auth()->check()) {
            return redirect()->route("login")->with("warning", "Devi essere loggato per eseguire questa azione");
        }

        $deckName = str_replace("+", " ", $deck);

        // Verifica che l'utente esista
        $targetUser = User::where("name", $user)->first();
        if (!$targetUser) {
            return redirect()->route("mazzi")->with("error", "Utente non trovato");
        }

        // Verifica che il mazzo esista e appartenga all'utente autenticato
        $mazzo = Deck::where("nome", $deckName)
                    ->where("codUtente", auth()->user()->id)
                    ->first();

        if (!$mazzo) {
            return redirect()->route("mazzi")->with("error", "Mazzo non trovato o non hai i permessi per modificarlo");
        }

        // Impedisce la modifica della visibilità della collezione
        if ($deckName === "Collezione") {
            return redirect()->route("mazzi")->with("warning", "Non puoi modificare la visibilità della collezione");
        }

        try {
            // Cambia la visibilità
            $mazzo->public = !$mazzo->public;
            $mazzo->save();

            $status = $mazzo->public ? "pubblico" : "privato";
            return redirect()->route("mazzi")->with("success", "Mazzo '$deckName' ora è $status");
        } catch (\Exception $e) {
            return redirect()->route("mazzi")->with("error", "Errore durante la modifica della visibilità: " . $e->getMessage());
        }
    }

    /**
     * Rename a deck with validation and authorization checks
     * Rinomina un mazzo con controlli di validazione e autorizzazione
     *
     * This method handles deck renaming with comprehensive validation:
     * - Verifies user authentication and deck ownership
     * - Validates new name format and uniqueness
     * - Prevents renaming of collection decks
     * - Prevents use of reserved names
     *
     * @param Request $request HTTP request containing 'nuovo_nome' parameter
     * @param string $user The username of the deck owner
     * @param string $deck The current deck name (URL encoded)
     * @return \Illuminate\Http\RedirectResponse Redirect with success/error messages
     */
    public function rename(Request $request, $user, $deck)
    {
        if (!auth()->check()) {
            return redirect()->route("login")->with("warning", "Devi essere loggato per eseguire questa azione");
        }

        $deckName = str_replace("+", " ", $deck);
        $nuovoNome = trim($request->input('nuovo_nome'));

        // Validazione del nuovo nome
        if (empty($nuovoNome)) {
            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])
                           ->with("error", "Il nome del mazzo non può essere vuoto");
        }

        if (strlen($nuovoNome) > 500) {
            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])
                           ->with("error", "Il nome del mazzo non può superare i 500 caratteri");
        }

        // Verifica che l'utente esista
        $targetUser = User::where("name", $user)->first();
        if (!$targetUser) {
            return redirect()->route("mazzi")->with("error", "Utente non trovato");
        }

        // Verifica che il mazzo esista e appartenga all'utente autenticato
        $mazzo = Deck::where("nome", $deckName)
                    ->where("codUtente", auth()->user()->id)
                    ->first();

        if (!$mazzo) {
            return redirect()->route("mazzi")->with("error", "Mazzo non trovato o non hai i permessi per modificarlo");
        }

        // Impedisce la rinominazione della collezione
        if ($deckName === "Collezione") {
            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])
                           ->with("warning", "Non puoi rinominare la collezione");
        }

        // Impedisce l'uso del nome riservato "Collezione"
        if ($nuovoNome === "Collezione") {
            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])
                           ->with("warning", "Il nome 'Collezione' è riservato");
        }

        // Verifica che non esista già un mazzo con il nuovo nome
        $mazzoEsistente = Deck::where("nome", $nuovoNome)
                             ->where("codUtente", auth()->user()->id)
                             ->where("id", "!=", $mazzo->id)
                             ->first();

        if ($mazzoEsistente) {
            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])
                           ->with("warning", "Esiste già un mazzo con questo nome");
        }

        try {
            // Aggiorna il nome del mazzo
            $vecchioNome = $mazzo->nome;
            $mazzo->nome = $nuovoNome;
            $mazzo->save();

            // Reindirizza al mazzo con il nuovo nome
            return redirect()->route("mazzo", ["user" => $user, "mazzo" => str_replace(" ", "+", $nuovoNome)])
                           ->with("success", "Mazzo rinominato da {$vecchioNome} a {$nuovoNome}");
        } catch (\Exception $e) {
            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])
                           ->with("error", "Errore durante la rinominazione: " . $e->getMessage());
        }
    }

    /**
     * Display and manage the user's personal collection as a special deck
     * Mostra e gestisce la collezione personale dell'utente come mazzo speciale
     *
     * This method handles the collection management system:
     * - Auto-creates collection deck if it doesn't exist
     * - Retrieves all cards in the collection with composition data
     * - Provides comprehensive statistics and debug information
     * - Applies sorting using the CardsController merge sort algorithm
     * - Calculates database statistics for filter optimization
     *
     * @return \Illuminate\View\View The collection view with cards, statistics and debug info
     */
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

        // Recupera le carte della collezione usando le relazioni Eloquent
        $compositions = $collezione->compositions()->with('card')->get();

        // Trasforma le composizioni in un formato compatibile con il codice esistente
        $cards = $compositions->map(function($composition) {
            if ($composition->card) {
                $card = $composition->card;
                $card->copie = $composition->copie;
                return $card;
            }
            return null;
        })->filter(); // Rimuove i null

        // Calcola il numero totale di carte
        $totalCards = $cards->sum('copie');

        // Le carte verranno caricate dinamicamente dal componente Livewire
        // Cards will be loaded dynamically by the Livewire component

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
            'cards_displayed' => 'Caricate dinamicamente da Livewire',
            'cards_in_collezione' => $cardsInCollezione,
            'max_values' => [
                'costo' => $maxCosto,
                'potenza' => $maxPotenza,
                'vita' => $maxVita
            ],
            'high_value_cards_count' => $carteAltoValore,
            'sample_high_value_cards' => $carteEsempio,
            'livewire_status' => 'Filtri gestiti da SearchFilter component',
            'sort_applied' => 'mergeSort applicato dinamicamente'
        ];

        return view('collezione.index', [
            'collezione' => $cards,
            'totalCards' => $totalCards,
            'collezioneId' => $collezione->id,
            'debugInfo' => $debugInfo
        ]);
    }

    /**
     * Update the user's collection by adding/removing/updating card quantities
     * Aggiorna la collezione dell'utente aggiungendo/rimuovendo/modificando quantità carte
     *
     * This AJAX endpoint handles real-time collection updates:
     * - Validates collection existence for authenticated user
     * - Updates or creates composition entries for cards
     * - Removes cards when quantity is set to 0 or less
     * - Provides JSON response for frontend integration
     *
     * @param Request $request HTTP request with 'espansione', 'numero', 'copie' parameters
     * @return \Illuminate\Http\JsonResponse JSON response indicating success or error
     */
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

        $hasChanges = false;

        if ($copie <= 0) {
            // Rimuovi la carta dalla collezione
            if ($composizione) {
                $composizione->delete();
                $hasChanges = true;
            }
        } else {
            if ($composizione) {
                // Aggiorna il numero di copie solo se è diverso
                if ($composizione->copie != $copie) {
                    $composizione->copie = $copie;
                    $composizione->save();
                    $hasChanges = true;
                }
            } else {
                // Crea nuova composizione
                $composizione = new Composition();
                $composizione->idMazzo = $collezione->id;
                $composizione->espansione = $espansione;
                $composizione->numero = $numero;
                $composizione->copie = $copie;
                $composizione->id = $collezione->id."-".$espansione."-".$numero;
                $composizione->save();
                $hasChanges = true;
            }
        }

        // Increment collection version if there were changes
        // Incrementa la versione della collezione se ci sono state modifiche
        if ($hasChanges) {
            $collezione->incrementVersion();
        }

        return response()->json(['success' => true]);
    }

    /**
     * API endpoint to search decks by user and name with visibility filter
     * Endpoint API per cercare mazzi per utente e nome con filtro di visibilità
     *
     * @param string $user Username pattern to search for
     * @param string $nome Deck name pattern to search for
     * @param bool $public Whether to search only public decks
     * @return \Illuminate\Database\Eloquent\Collection Collection of matching decks
     */
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
     * Export a deck in official TXT format for Star Wars Unlimited
     * Esporta un mazzo nel formato TXT ufficiale per Star Wars Unlimited
     *
     * This method generates a downloadable TXT file following the official format
     * used by all Star Wars Unlimited programs. The format includes sections for
     * Leaders, Base, Deck, and Sideboard with proper card formatting.
     *
     * @param string $user The username of the deck owner
     * @param string $deck The deck name (URL encoded)
     * @return \Illuminate\Http\Response File download response or error
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
     * Export a deck in official JSON format for Star Wars Unlimited
     * Esporta un mazzo nel formato JSON ufficiale per Star Wars Unlimited
     *
     * This method generates a downloadable JSON file following the official format
     * used by all Star Wars Unlimited programs. The format includes metadata,
     * leader, base, deck, and sideboard sections with proper card ID formatting.
     *
     * @param string $user The username of the deck owner
     * @param string $deck The deck name (URL encoded)
     * @return \Illuminate\Http\Response File download response or error
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

        // Recupera le carte del mazzo usando le relazioni Eloquent
        $compositions = $deckModel->compositions()->with('card')->get();

        // Trasforma le composizioni in un formato compatibile con il codice esistente
        $cards = $compositions->map(function($composition) {
            if ($composition->card) {
                $card = $composition->card;
                $card->copie = $composition->copie;
                return $card;
            }
            return null;
        })->filter(); // Rimuove i null

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
        // Debug: log che showImport è stato chiamato
        \Log::info('showImport called', [
            'method' => request()->method(),
            'url' => request()->url(),
            'user_id' => Auth::id(),
            'is_authenticated' => Auth::check()
        ]);

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
            'public' => 'nullable|in:on,1,true,0,false'
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
        // Debug: log che il metodo è stato chiamato
        \Log::info('importFromUrl called', [
            'method' => $request->method(),
            'url' => $request->url(),
            'user_id' => Auth::id(),
            'is_authenticated' => Auth::check(),
            'input' => $request->all(),
            'public_input' => $request->input('public'),
            'public_boolean' => $request->boolean('public'),
            'has_public' => $request->has('public')
        ]);

        if (!Auth::check()) {
            \Log::warning('User not authenticated for import');
            return response()->json(['error' => 'Non autorizzato'], 401);
        }

        \Log::info('Step 1: User authenticated');

        try {
            $request->validate([
                'url' => 'required|url',
                'deck_name' => 'required|string|max:500',
                'public' => 'nullable|in:on,1,true,0,false'
            ]);
            \Log::info('Step 2: Validation passed');
        } catch (\Exception $e) {
            \Log::error('Step 2: Validation failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Validazione fallita: ' . $e->getMessage()], 400);
        }

        try {
            \Log::info('Step 3: Starting try block');

            $url = $request->input('url');
            \Log::info('Step 4: Got URL', ['url' => $url]);

            // Controlla se è un URL di SWUDB e convertilo all'API
            $apiUrl = $this->convertSwudbUrl($url);
            \Log::info('Step 5: URL converted', ['api_url' => $apiUrl]);

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

            \Log::info('Step 8: About to call processDeckImport');

            $result = $this->processDeckImport($content, $extension, $request->input('deck_name'), $request->boolean('public'));

            \Log::info('Step 9: processDeckImport completed', ['result' => $result]);

            if ($result['success']) {
                \Log::info('Step 10: Success, returning JSON response');
                return response()->json([
                    'success' => true,
                    'message' => 'Mazzo importato con successo',
                    'deck_url' => route('mazzo', ['user' => Auth::user()->name, 'mazzo' => str_replace(' ', '+', $result['deck_name'])])
                ]);
            } else {
                \Log::info('Step 10: Error, returning error response', ['error' => $result['error']]);
                return response()->json(['error' => $result['error']], 400);
            }
        } catch (\Exception $e) {
            \Log::error('Step X: Exception caught', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Errore durante l\'importazione: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Processa l'importazione del mazzo
     */
    private function processDeckImport($content, $format, $deckName, $isPublic)
    {
        try {
            // Debug: log dei parametri ricevuti
            \Log::info('processDeckImport called', [
                'deck_name' => $deckName,
                'is_public' => $isPublic,
                'is_public_type' => gettype($isPublic),
                'format' => $format,
                'content_length' => strlen($content)
            ]);

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

            // Debug: log prima del salvataggio
            \Log::info('Creating deck', [
                'nome' => $deck->nome,
                'public' => $deck->public,
                'public_type' => gettype($deck->public),
                'codUtente' => $deck->codUtente
            ]);

            $deck->save();

            // Debug: log dopo il salvataggio
            \Log::info('Deck created', [
                'id' => $deck->id,
                'nome' => $deck->nome,
                'public' => $deck->public,
                'public_from_db' => $deck->fresh()->public
            ]);

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