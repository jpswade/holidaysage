<?php

namespace App\Support;

/**
 * One-line progress for CLI when the queue uses the sync driver (e.g. `holidaysage:run --sync`).
 */
final class SyncQueueLine
{
    public static function line(string $message): void
    {
        if (! app()->runningInConsole() || app()->runningUnitTests()) {
            return;
        }
        if (config('queue.default') !== 'sync') {
            return;
        }
        fwrite(\STDOUT, '  [holidaysage] '.$message.PHP_EOL);
    }
}
