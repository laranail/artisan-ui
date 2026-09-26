<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands;

use Illuminate\Console\Command;

final class EchoCommand extends Command
{
    protected $signature = 'lau-fixture:echo {text : What to print} {--shout : Upper-case it} {--tag=* : Repeatable}';

    protected $description = 'Print the text back';

    public function handle(): int
    {
        $text = (string) $this->argument('text');
        $this->line($this->option('shout') ? strtoupper($text) : $text);

        foreach ((array) $this->option('tag') as $tag) {
            $this->line('tag:' . $tag);
        }

        return self::SUCCESS;
    }
}
