<?php

namespace App\Services\DeckValidation;

use App\Enums\DeckFormat;

class DeckFormatValidatorFactory
{
    public static function make(DeckFormat $format): DeckFormatValidator
    {
        return match ($format) {
            DeckFormat::Premier => new PremierFormatValidator,
            DeckFormat::Eternal => new EternalFormatValidator,
            DeckFormat::TwinSuns => new TwinSunsFormatValidator,
        };
    }
}
