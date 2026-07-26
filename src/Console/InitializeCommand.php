<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Console;

use GetBible\Scripture\Maintenance\MaintenanceServiceInterface;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Installs configured modules when permitted and warms their snapshots.
 *
 * @since 0.3.0
 */
final class InitializeCommand extends AbstractCommand
{
    /**
     * Stable Joomla Console command name.
     *
     * @var string|null
     * @since 0.3.0
     */
    protected static $defaultName = 'scripture:initialize';

    /**
     * Creates the initialization command.
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
     * Configures command options and help.
     *
     * @return void
     * @since 0.3.0
     */
    protected function configure(): void
    {
        $this->setDescription('Install missing Bible modules when permitted and warm validated snapshots.');
        $this->addOption(
            'module',
            'm',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Exact translation module to initialize; repeat the option for multiple modules.',
        );
        $this->addOption(
            'all',
            'a',
            InputOption::VALUE_NONE,
            'Explicitly request every policy-approved translation from the native provisioner.',
        );
    }

    /**
     * Executes initialization and emits deterministic JSON.
     *
     * @param InputInterface $input Bound command input.
     * @param OutputInterface $output Console output.
     *
     * @return int
     * @since 0.3.0
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->maintenance->initialize(
            ModuleOption::normalize($input->getOption('module')),
            $input->getOption('all') === true,
        );
        $output->writeln((string) json_encode(
            $result->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return $result->succeeded() ? 0 : 1;
    }
}
