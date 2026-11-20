<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Console;

use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Removes stored settings that no longer have a definition. Without `--apply`
 * only lists the stale keys (dry run); override rows of defined keys are
 * never touched.
 *
 * @api
 */
#[AsCommand(
    name: 'settings:prune',
    description: 'Remove stored settings that have no definition',
)]
final class SettingsPruneCommand extends Command
{
    public function __construct(
        private readonly DbSettingsProvider $settings,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption(
            name: 'apply',
            mode: InputOption::VALUE_NONE,
            description: 'Actually remove the stale rows instead of listing them',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stale = $this->settings->staleKeys();

        if ($stale === []) {
            $output->writeln('No stale stored keys.');

            return Command::SUCCESS;
        }

        if ($input->getOption('apply') !== true) {
            $output->writeln('Stale stored keys (no definition):');

            foreach ($stale as $key) {
                $output->writeln('  ' . $key);
            }

            $output->writeln(sprintf('%d key(s). Run with --apply to remove.', count($stale)));

            return Command::SUCCESS;
        }

        $removed = $this->settings->pruneStale();
        $output->writeln(sprintf('Removed %d stale key(s).', $removed));

        return Command::SUCCESS;
    }
}
