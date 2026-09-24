<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Deck;
use App\Models\Composition;
use App\Models\Card;
use App\Http\Controllers\CardsController;
use Illuminate\Support\Facades\Auth;

class DeckBuildManager extends Component
{
    public $nome;
    public $user;
    public $deck;
    public $deckObject;
    public $proprietario;
    public $collezioneId;

    public $filtro = 'tutte'; // 'tutte', 'mancanti', 'possedute' (filtro della tabella)
    public $tipoLista = 'mancanti'; // 'mancanti', 'possedute' (lista TXT generata/esportata)
    public $searchQuery = '';

    public function mount($nome, $user, $deck, $deckObject, $proprietario)
    {
        $this->nome = $nome;
        $this->user = $user;
        $this->deck = $deck;
        $this->deckObject = $deckObject;
        $this->proprietario = $proprietario;

        // Recupera o crea la collezione dell'utente loggato
        $collezione = Deck::firstOrCreate(
            ['codUtente' => Auth::id(), 'nome' => 'Collezione'],
            ['public' => false]
        );
        $this->collezioneId = $collezione->id;
    }

    /**
     * Modifica il numero di copie possedute in collezione per una specifica carta
     *
     * @param string $espansione
     * @param int $numero
     * @param int $delta (+1 o -1)
     */
    public function modificaCopiaCollezione($espansione, $numero, $delta)
    {
        if (!Auth::check()) {
            return;
        }

        $collezione = Deck::find($this->collezioneId);
        if (!$collezione) {
            return;
        }

        $composizione = Composition::where('idMazzo', $this->collezioneId)
            ->where('espansione', $espansione)
            ->where('numero', $numero)
            ->first();

        $copieAttuali = $composizione ? $composizione->copie : 0;
        $nuoveCopie = max(0, $copieAttuali + $delta);

        if ($nuoveCopie <= 0) {
            if ($composizione) {
                $composizione->delete();
                $collezione->incrementVersion();
            }
        } else {
            if ($composizione) {
                if ($composizione->copie != $nuoveCopie) {
                    $composizione->copie = $nuoveCopie;
                    $composizione->save();
                    $collezione->incrementVersion();
                }
            } else {
                $composizione = new Composition();
                $composizione->idMazzo = $this->collezioneId;
                $composizione->espansione = $espansione;
                $composizione->numero = $numero;
                $composizione->copie = $nuoveCopie;
                $composizione->id = $this->collezioneId . "-" . $espansione . "-" . $numero;
                $composizione->save();
                $collezione->incrementVersion();
            }
        }
    }

    /**
     * Sort a card collection with the official mergeSort (aspects preloaded)
     * Ordina una collezione di carte con il mergeSort ufficiale (aspetti precaricati)
     *
     * @param \Illuminate\Support\Collection $carte Cards to sort / carte da ordinare
     * @return \Illuminate\Support\Collection Sorted cards (empty collection if input is empty)
     */
    private function ordinaCarte($carte)
    {
        if ($carte->isEmpty()) {
            return $carte;
        }

        if (!$carte instanceof \Illuminate\Database\Eloquent\Collection) {
            $carte = new \Illuminate\Database\Eloquent\Collection($carte->values());
        }
        $carte->load('aspects');

        return CardsController::mergeSort($carte);
    }

    public function render()
    {
        // Qualunque valore diverso da 'possedute' viene trattato come 'mancanti'
        $tipoLista = $this->tipoLista === 'possedute' ? 'possedute' : 'mancanti';

        // Recupera le composizioni del mazzo
        $deckModel = Deck::find($this->deckObject->id);
        $compositions = $deckModel->compositions()->with('card.aspects')->get();

        // Map collezioni
        $collezioneCompositions = Composition::where('idMazzo', $this->collezioneId)->get();
        $collezioneMap = [];
        foreach ($collezioneCompositions as $comp) {
            $key = $comp->espansione . '-' . $comp->numero;
            $collezioneMap[$key] = ($collezioneMap[$key] ?? 0) + $comp->copie;
        }

        $cards = collect();
        $missingCards = collect();
        $ownedCards = collect();

        foreach ($compositions as $comp) {
            if ($comp->card) {
                $card = clone $comp->card;
                $key = $card->espansione . '-' . $card->numero;
                $copieMazzo = $comp->copie;
                $copieCollezione = $collezioneMap[$key] ?? 0;
                $copieMancanti = max(0, $copieMazzo - $copieCollezione);

                $card->copie_mazzo = $copieMazzo;
                $card->copie_collezione = $copieCollezione;
                $card->copie_mancanti = $copieMancanti;
                $card->is_complete = ($copieCollezione >= $copieMazzo);

                $cards->push($card);

                if ($copieMancanti > 0) {
                    $missingCard = clone $card;
                    $missingCard->copie_mancanti = $copieMancanti;
                    $missingCards->push($missingCard);
                }

                // Copie del mazzo effettivamente coperte dalla collezione
                $copiePossedute = min($copieMazzo, $copieCollezione);
                if ($copiePossedute > 0) {
                    $ownedCard = clone $card;
                    $ownedCard->copie_possedute = $copiePossedute;
                    $ownedCards->push($ownedCard);
                }
            }
        }

        // Applica mergeSort
        if (!$cards->isEmpty()) {
            if (!$cards instanceof \Illuminate\Database\Eloquent\Collection) {
                $cards = new \Illuminate\Database\Eloquent\Collection($cards->values());
            }
            $cards->load('aspects');
            $cards = CardsController::mergeSort($cards);
        }

        $missingCards = $this->ordinaCarte($missingCards);
        $ownedCards = $this->ordinaCarte($ownedCards);

        // Formattazione stringa TXT post-mergesort della lista scelta dall'utente
        $listaTxtLines = [];
        if ($tipoLista === 'possedute') {
            foreach ($ownedCards as $oCard) {
                $listaTxtLines[] = "{$oCard->copie_possedute}x {$oCard->espansione} {$oCard->numero} {$oCard->nome} ({$oCard->rarita})";
            }
        } else {
            foreach ($missingCards as $mCard) {
                $listaTxtLines[] = "{$mCard->copie_mancanti}x {$mCard->espansione} {$mCard->numero} {$mCard->nome} ({$mCard->rarita})";
            }
        }
        $listaTxt = implode("\n", $listaTxtLines);

        // Calcolo statistiche KPI
        $totaleCarteMazzo = $cards->sum('copie_mazzo');
        $totaleCopiePossedute = $cards->sum(function($c) {
            return min($c->copie_mazzo, $c->copie_collezione);
        });
        $totaleCarteMancanti = $cards->sum('copie_mancanti');
        $percentualeCompletamento = $totaleCarteMazzo > 0 ? round(($totaleCopiePossedute / $totaleCarteMazzo) * 100) : 0;

        // Filtraggio dinamico carte da visualizzare
        $filteredCards = $cards->filter(function ($c) {
            if ($this->filtro === 'mancanti' && $c->copie_mancanti <= 0) {
                return false;
            }
            if ($this->filtro === 'possedute' && $c->copie_mancanti > 0) {
                return false;
            }
            if (!empty($this->searchQuery)) {
                $q = strtolower($this->searchQuery);
                $nomeMatch = str_contains(strtolower($c->nome), $q);
                $codeMatch = str_contains(strtolower($c->espansione . ' ' . $c->numero), $q);
                return $nomeMatch || $codeMatch;
            }
            return true;
        });

        return view('livewire.deck-build-manager', [
            'cards' => $filteredCards,
            'listaTxt' => $listaTxt,
            'tipoLista' => $tipoLista,
            'totaleCarteMazzo' => $totaleCarteMazzo,
            'totaleCopiePossedute' => $totaleCopiePossedute,
            'totaleCarteMancanti' => $totaleCarteMancanti,
            'percentualeCompletamento' => $percentualeCompletamento,
            'countCarteMancantiDistinte' => $missingCards->count(),
        ]);
    }
}
