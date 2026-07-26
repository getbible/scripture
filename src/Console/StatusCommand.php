<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Console;

use GetBible\Scripture\Maintenance\MaintenanceServiceInterface;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports scheduler state, capabilities, and installed translations.
 *
 * @since 0.3.0
 */
final class StatusCommand extends AbstractCommand
{
    /**
     * Stable Joomla Console command name.
     *
     * @var string|null
     * @since 0.3.0
     */
    protected static $defaultName = 'scripture:status';

    /**
     * Creates the status command.
     *
     * @param MaintenanceServiceInterface $maintenance Maintenance lifecycle.
     *
     * @since 0.3.0
     */
    public function __construct(private MaintenanceServiceInterface $maintenance)
    {
        parent::__construct();
    }

    /**
     * Configures command description.
     *
     * @return void
     * @since 0.3.0
     */
    protected function configure(): void
    {
        $this->setDescription('Report Scripture maintenance state and native provisioning capabilities.');
    }

    /**
     * Emits deterministic JSON status.
     *
     * @param InputInterface $input Bound command input.
     * @param OutputInterface $output Console output.
     *
     * @return int
     * @since 0.3.0
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln((string) json_encode(
            $this->maintenance->status()->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return 0;
    }
}
