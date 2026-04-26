<?php

namespace App\Support;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Main + optional sub progress bars when the import pipeline runs on the sync queue.
 * Not active in PHPUnit; safe no-ops if begin() was not called.
 */
final class SyncRunProgress
{
    private static ?self $instance = null;

    private const MAIN_STEPS = 4;

    private ?OutputInterface $output = null;

    private ?ProgressBar $main = null;

    private ?ProgressBar $sub = null;

    private bool $subOpen = false;

    public static function isActive(): bool
    {
        return self::$instance !== null
            && self::$instance->output !== null
            && self::$instance->main !== null;
    }

    public static function begin(OutputInterface $output): void
    {
        if (app()->runningUnitTests() || ! app()->runningInConsole() || config('queue.default') !== 'sync') {
            return;
        }
        if (self::$instance !== null) {
            return;
        }

        $i = new self;
        $i->output = $output;
        $i->main = new ProgressBar($output, self::MAIN_STEPS);
        $i->main->setBarCharacter($output->isDecorated() ? '▓' : '=');
        $i->main->setFormat(
            " <fg=gray>Pipeline</>\n  %message%\n  %current%/%max% [%bar%] %percent:3s%%"
        );
        $i->main->setMessage('Preparing run…');
        $i->main->start();
        $output->writeln('');
        self::$instance = $i;
    }

    public static function next(string $message): void
    {
        $i = self::$instance;
        if (! $i || ! $i->main) {
            return;
        }
        $i->endSubIfOpen();
        $i->main->setMessage($message);
        $i->main->advance(1);
        $i->output?->writeln('');
    }

    public static function startSubBar(int $count): void
    {
        $i = self::$instance;
        if (! $i || ! $i->output || $count < 1) {
            return;
        }
        $i->endSubIfOpen();
        $i->output->writeln('  <fg=gray>Enriching hotel details (HTTP)</>');
        $i->sub = new ProgressBar($i->output, $count);
        $i->sub->setBarCharacter($i->output->isDecorated() ? '▓' : '=');
        $i->sub->setFormat("   %message%\n   %current%/%max% [%bar%] %percent:3s%%");
        $i->sub->setMessage("Fetching {$count} page(s)…");
        $i->sub->start();
        $i->output->writeln('');
        $i->subOpen = true;
    }

    public static function subTick(): void
    {
        $i = self::$instance;
        if (! $i?->sub || ! $i->subOpen) {
            return;
        }
        $i->sub->setMessage('Page fetch…');
        $i->sub->advance(1);
        $i->output?->writeln('');
    }

    public static function endSubBar(): void
    {
        $i = self::$instance;
        if (! $i) {
            return;
        }
        $i->endSubIfOpen();
    }

    public static function finishAll(): void
    {
        $i = self::$instance;
        if (! $i || ! $i->main) {
            return;
        }
        $i->endSubIfOpen();
        $i->main->setMessage('Run complete');
        $i->main->finish();
        $i->output?->writeln('');
        self::reset();
    }

    public static function onFailure(?Throwable $e = null): void
    {
        $i = self::$instance;
        if (! $i) {
            return;
        }
        if ($e !== null) {
            $i->output?->writeln('<fg=red> </> '.$e->getMessage());
        }
        $i->endSubIfOpen();
        $i->main?->setMessage('Failed or interrupted.');
        $i->main?->finish();
        $i->output?->writeln('');
        self::reset();
    }

    private function endSubIfOpen(): void
    {
        if (! $this->subOpen || ! $this->sub) {
            return;
        }
        if ($this->sub->getProgress() < $this->sub->getMaxSteps()) {
            $this->sub->setProgress($this->sub->getMaxSteps());
        }
        $this->sub->setMessage('Enrichment pass complete');
        $this->sub->finish();
        $this->output?->writeln('');
        $this->sub = null;
        $this->subOpen = false;
    }

    private static function reset(): void
    {
        self::$instance = null;
    }
}
