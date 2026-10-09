<?php

namespace App\Services\DeckValidation;

use App\Models\Deck;

interface DeckFormatValidator
{
    /**
     * Validates a deck against this format's rules, returning a list of human-readable errors
     * Valida un mazzo secondo le regole di questo formato, restituendo una lista di errori leggibili
     *
     * @return array<int, string> Vuoto se il mazzo è valido
     */
    public function validate(Deck $deck): array;
}
