<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Console;

use GetBible\Scripture\Configuration\ConfigurationPath;
use GetBible\Scripture\Setup\SetupServiceInterface;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports configuration and native-runtime readiness without changing state.
 *
 * @since 1.0.0
 */
final class DoctorCommand extends AbstractCommand
{
    /**
     * Stable Joomla Console command name.
     *
     * @var string|null
     * @since 1.0.0
     */
    protected static $defaultName = 'scripture:doctor';

    /**
     * Creates the read-only diagnostic command.
     *
     * @param SetupServiceInterface $setup Setup and inspection service.
     *
     * @since 1.0.0
     */
    public function __construct(private SetupServiceInterface $setup)
    {
        parent::__construct();
    }

    /**
     * Configures diagnostic options.
     *
     * @return void
     * @since 1.0.0
     */
    protected function configure(): void
    {
        $this->setDescription('Inspect Scripture configuration and native runtime prerequisites.');
        $this->addOption(
            'config',
            'c',
            InputOption::VALUE_REQUIRED,
            'Absolute JSON configuration path; otherwise use GETBIBLE_SCRIPTURE_CONFIG_PATH.',
        );
        $this->addOption(
            'json',
            null,
            InputOption::VALUE_NONE,
            'Emit deterministic JSON.',
        );
    }

    /**
     * Executes read-only diagnostics.
     *
     * @param InputInterface $input Bound command input.
     * @param OutputInterface $output Command output.
     *
     * @return int
     * @since 1.0.0
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $path = $this->stringOption($input, 'config');
        $runtime = $this->setup->inspect();
        $configuration = null;
        $errors = [];

        try {
            $resolvedPath = ConfigurationPath::resolve($path);
            $current = $this->setup->configuration($resolvedPath);
            $configuration = [
                'module_path' => $current->modulePath(),
                'cache_path' => $current->cachePath(),
                'refresh_interval' => $current->refreshIntervalSpec(),
                'auto_refresh' => $current->autoRefresh(),
                'lock_timeout' => $current->lockTimeout(),
                'modules' => $current->modules(),
                'provisioning_enabled' => $current->provisioningEnabled(),
                'install_all' => $current->installAll(),
            ];
        } catch (\Throwable $exception) {
            $resolvedPath = $path;
            $errors[] = $exception->getMessage();
        }

        $result = [
            'operation' => 'doctor',
            'succeeded' => $runtime->ready() && $errors === [],
            'configuration_path' => $resolvedPath,
            'configuration' => $configuration,
            'runtime' => $runtime->toArray(),
            'errors' => $errors,
        ];

        if ($input->getOption('json') === true || !$input->isInteractive()) {
            $output->writeln($this->json($result));
        } else {
            $output->writeln($result['succeeded'] ? '<info>Scripture is ready.</info>' : '<error>Scripture is not ready.</error>');
            $output->writeln($this->json($result));
        }

        return $result['succeeded'] ? 0 : 1;
    }

    /**
     * Returns a trimmed string option.
     *
     * @param InputInterface $input Bound command input.
     * @param string $name Option name.
     *
     * @return string|null
     * @since 1.0.0
     */
    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Encodes deterministic readable JSON.
     *
     * @param array<string, mixed> $value Result map.
     *
     * @return string
     * @since 1.0.0
     */
    private function json(array $value): string
    {
        return (string) json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }
}
