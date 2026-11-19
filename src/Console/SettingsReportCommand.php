<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Console;

use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports the source of every defined setting (`db` / `config` / `default`),
 * stored keys without a definition (stale rows) and stored values that fail
 * validation. Exits with failure when any problem is found, so it can gate CI.
 * Secret values are never printed.
 *
 * @api
 */
#[AsCommand(
    name: 'settings:report',
    description: 'Report setting sources, stale stored keys and invalid stored values',
)]
final class SettingsReportCommand extends Command
{
    public function __construct(
        private readonly DbSettingsProvider $settings,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $problems = 0;

        $output->writeln('Setting sources:');

        foreach ($this->settings->definedKeys() as $key) {
            try {
                $state = $this->settings->describe($key);
            } catch (\Throwable $e) {
                $problems++;
                $output->writeln(sprintf('Invalid stored value for "%s": %s', $key, $e->getMessage()));

                continue;
            }

            $output->writeln(sprintf('  %-40s %s', $state->key, $state->source));

            if (!$state->isSecret) {
                continue;
            }

            // describe() skips the value of secret settings; only decrypting
            // them proves the stored envelope is intact
            try {
                $this->settings->get($key);
            } catch (\Throwable $e) {
                $problems++;
                $output->writeln(sprintf('Invalid stored value for "%s": %s', $key, $e->getMessage()));
            }
        }

        $stale = $this->settings->staleKeys();
        if ($stale !== []) {
            $problems += count($stale);

            $output->writeln('Stale stored keys (no definition; prune with settings:prune --apply):');
            foreach ($stale as $key) {
                $output->writeln('  ' . $key);
            }
        }

        if ($problems === 0) {
            $output->writeln('No problems found.');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('%d problem(s) found.', $problems));

        return Command::FAILURE;
    }
}
