<?php

namespace App\Services;

final readonly class TelegramActionResult
{
    public function __construct(
        public bool $successful,
        public ?int $messageId = null,
        public ?string $errorDescription = null,
        public array $raw = [],
    ){}
}
