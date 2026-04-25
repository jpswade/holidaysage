<?php

namespace App\ViewModels;

class TopPickViewModel
{
    public function __construct(
        public readonly ResultCardViewModel $card,
    ) {}

    public static function fromResult(ResultCardViewModel $result): self
    {
        return new self(card: $result);
    }
}
