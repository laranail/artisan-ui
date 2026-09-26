<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Exercises the echo capture: interleaving, ob_clean, and a command removing the buffer.
 */
final class BufferCommand extends Command
{
    protected $signature = 'lau-fixture:buffers {mode} {--label= : Value optional}';

    protected $description = 'Output-buffer edge cases';

    protected function configure(): void
    {
        parent::configure();

        // Laravel's signature syntax has no negatable options; Symfony's API does.
        $this->addOption('cache', null, InputOption::VALUE_NEGATABLE, 'A negatable flag');
    }

    public function handle(): int
    {
        match ($this->argument('mode')) {
            'interleave' => $this->interleave(),
            'clean'      => $this->cleaned(),
            'pop'        => $this->pop(),
            default      => $this->line('label=' . var_export($this->option('label'), true) . ' cache=' . var_export($this->option('cache'), true)),
        };

        return self::SUCCESS;
    }

    private function interleave(): void
    {
        echo "A\n";
        $this->line('B');
        echo "C\n";
    }

    private function cleaned(): void
    {
        echo 'discard me';
        ob_clean();
        echo "kept\n";
    }

    private function pop(): void
    {
        echo "before\n";
        ob_end_clean();
        $this->line('after');
    }
}
