<?php

namespace App\Services\Imports;

use App\Contracts\ImportUrlParser;
use App\Services\Imports\Parsers\Jet2ImportUrlParser;
use App\Services\Imports\Parsers\TuiImportUrlParser;
use InvalidArgumentException;

class ImportUrlParserRegistry
{
    public function __construct(
        private readonly Jet2ImportUrlParser $jet2,
        private readonly TuiImportUrlParser $tui,
    ) {}

    public function parserFor(string $url): ImportUrlParser
    {
        foreach ($this->parsers() as $parser) {
            if ($parser->supports($url)) {
                return $parser;
            }
        }

        throw new InvalidArgumentException('No import parser supports this URL: '.$url);
    }

    /**
     * @return list<ImportUrlParser>
     */
    private function parsers(): array
    {
        return [$this->jet2, $this->tui];
    }
}
