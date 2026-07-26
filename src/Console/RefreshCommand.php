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
 * Runs forced or interval-gated module and snapshot refresh.
 *
 * @since 0.3.0
 */
final class RefreshCommand extends AbstractCommand
{
    /**
     * Stable Joomla Console command name.
     *
     * @var string|null
     * @since 0.3.0
     */
    protected static $defaultName = 'scripture:refresh';

    /**
     * Creates the refresh command.
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
        $this->setDescription('Refresh native modules when enabled and rebuild validated snapshots.');
        $this->addOption(
            'module',
            'm',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Exact translation module to refresh; repeat the option for multiple modules.',
        );
        $this->addOption(
            'if-due',
            null,
            InputOption::VALUE_NONE,
            'Skip successfully when the configured durable refresh interval has not elapsed.',
        );
    }

    /**
     * Executes refresh and emits deterministic JSON.
     *
     * @param InputInterface $input Bound command input.
     * @param OutputInterface $output Console output.
     *
     * @return int
     * @since 0.3.0
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $modules = ModuleOption::normalize($input->getOption('module'));

        $result = $input->getOption('if-due') === true
            ? $this->maintenance->refreshIfDue($modules)
            : $this->maintenance->refresh($modules);
        $output->writeln((string) json_encode(
            $result->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return $result->succeeded() ? 0 : 1;
    }
}
