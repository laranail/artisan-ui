<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Commands;

use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Simtabi\Laranail\Console\Tools\Widgets\Table;
use Illuminate\Console\Command as IlluminateCommand;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\ArtisanUI\Core\Policy\CommandPolicy;
use Simtabi\Laranail\ArtisanUI\Core\Policy\RiskClassifier;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * `laranail::artisan-ui.policy` shows, for every command in the application, whether the
 * panel lists it and which risk class it falls in, so the effect of the allow, deny and
 * risk lists can be read before anyone opens the panel.
 */
final class PolicyCommand extends Command
{
    use SupportsNamespacedNames;

    protected $description = 'Show which commands the artisan-ui panel exposes, and their risk class';

    protected $signature = 'laranail::artisan-ui.policy
        {--risk= : Only show commands in this risk class (safe, writes_files, destructive, forbidden)}
        {--listed : Only show commands the panel lists}
        {--json : Emit the rows as JSON}';

    public function handle(CommandPolicy $policy, RiskClassifier $classifier): int
    {
        $filter = $this->option('risk');
        $risk = is_string($filter) && $filter !== '' ? CommandRisk::tryFrom($filter) : null;

        if (is_string($filter) && $filter !== '' && ! $risk instanceof CommandRisk) {
            $this->error("Unknown risk class [{$filter}]. Use one of: " . implode(', ', CommandRisk::values()) . '.');

            return self::INVALID;
        }

        $rows = [];

        foreach ($this->getApplication()?->all() ?? [] as $name => $command) {
            if (! $command instanceof IlluminateCommand || $command->getName() !== $name) {
                continue;
            }

            $class = $classifier->classify($name);
            $listed = $policy->isListed($name, $command->isHidden());

            if (($risk instanceof CommandRisk && $class !== $risk) || ($this->option('listed') && ! $listed)) {
                continue;
            }

            $rows[$name] = [
                'command' => $name,
                'listed'  => $listed,
                'risk'    => $class->value,
                'reason'  => match (true) {
                    $listed                => '',
                    ! $class->isRunnable() => 'forbidden',
                    $command->isHidden()   => 'hidden',
                    default                => 'allow/deny list',
                },
            ];
        }

        ksort($rows);

        if ($this->option('json')) {
            $this->line((string) json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->output->write(Table::make()
            ->headers(['Command', 'Listed', 'Risk', 'Why not'])
            ->rows(array_map(
                static fn (array $row): array => [$row['command'], $row['listed'] ? 'yes' : 'no', $row['risk'], $row['reason']],
                array_values($rows),
            ))
            ->render());

        $this->newLine();
        $this->line(sprintf('%d commands, %d listed.', count($rows), count(array_filter($rows, static fn (array $r): bool => $r['listed']))));

        return self::SUCCESS;
    }
}
