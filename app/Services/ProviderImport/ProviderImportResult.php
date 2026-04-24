<?php

namespace App\Services\ProviderImport;

final readonly class ProviderImportResult
{
    /**
     * @param  list<array<string,mixed>>  $candidates
     */
    public function __construct(
        public int $responseStatus,
        public string $rawBody,
        public array $candidates,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function toSnapshotPayload(): array
    {
        return [
            'response_status' => $this->responseStatus,
            'candidates' => $this->candidates,
        ];
    }
}
