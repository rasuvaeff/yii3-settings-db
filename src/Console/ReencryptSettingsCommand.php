<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Console;

use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Re-encrypts all stored secret values using the active cipher key. Works in
 * any Symfony Console / `yiisoft/yii-console` application.
 *
 * @api
 */
#[AsCommand(
    name: 'settings:reencrypt',
    description: 'Re-encrypt all stored secret settings using the active cipher key',
)]
final class ReencryptSettingsCommand extends Command
{
    public function __construct(
        private readonly DbSettingsProvider $settings,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->settings->reencryptSecrets();

        $output->writeln(sprintf('Re-encrypted %d secret setting(s).', $count));

        return Command::SUCCESS;
    }
}
