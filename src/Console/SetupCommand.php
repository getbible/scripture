<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Console;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Configuration\ConfigurationPath;
use GetBible\Scripture\Setup\SetupRequest;
use GetBible\Scripture\Setup\SetupServiceInterface;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Interactively configures and warms already-installed Scripture modules.
 *
 * This command never invokes PIE, a subprocess, a package manager, privilege
 * escalation, or network access.
 *
 * @since 1.0.0
 */
final class SetupCommand extends AbstractCommand
{
    /**
     * Stable Joomla Console command name.
     *
     * @var string|null
     * @since 1.0.0
     */
    protected static $defaultName = 'scripture:setup';

    /**
     * Creates the application setup command.
     *
     * @param SetupServiceInterface $setup Setup service.
     *
     * @since 1.0.0
     */
    public function __construct(private SetupServiceInterface $setup)
    {
        parent::__construct();
    }

    /**
     * Configures explicit and non-interactive setup options.
     *
     * @return void
     * @since 1.0.0
     */
    protected function configure(): void
    {
        $this->setDescription('Persist Scripture settings and warm installed translations.');
        $this->addOption(
            'config',
            'c',
            InputOption::VALUE_REQUIRED,
            'Absolute JSON configuration path; otherwise use GETBIBLE_SCRIPTURE_CONFIG_PATH.',
        );
        $this->addOption('module-path', null, InputOption::VALUE_REQUIRED, 'Explicit installed SWORD module root.');
        $this->addOption('cache-path', null, InputOption::VALUE_REQUIRED, 'Writable Scripture snapshot cache root.');
        $this->addOption(
            'refresh-interval',
            null,
            InputOption::VALUE_REQUIRED,
            'Positive ISO-8601 refresh interval.',
        );
        $this->addOption(
            'lock-timeout',
            null,
            InputOption::VALUE_REQUIRED,
            'Positive lifecycle lock timeout in seconds.',
        );
        $this->addOption(
            'module',
            'm',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Installed translation to configure and warm; repeat for multiple modules.',
        );
        $this->addOption('auto-refresh', null, InputOption::VALUE_NONE, 'Enable stale snapshot refresh on query.');
        $this->addOption('no-auto-refresh', null, InputOption::VALUE_NONE, 'Disable stale snapshot refresh on query.');
        $this->addOption('no-warm', null, InputOption::VALUE_NONE, 'Persist settings without warming snapshots.');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Emit deterministic JSON.');
    }

    /**
     * Executes setup without mutating system dependencies.
     *
     * @param InputInterface $input Bound command input.
     * @param OutputInterface $output Command output.
     *
     * @return int
     * @since 1.0.0
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $path = $this->configurationPath($input, $output);
            $current = $this->setup->configuration($path);
            $values = $this->values($input, $output, $current);
            $warm = $input->getOption('no-warm') !== true;

            if ($input->isInteractive() && $input->getOption('no-warm') !== true) {
                $warm = $this->confirmation(
                    $input,
                    $output,
                    'Warm the configured installed translations now?',
                    true,
                );
            }

            $result = $this->setup->apply(new SetupRequest($path, $values, $warm));
            $payload = $result->toArray();
            $succeeded = $result->succeeded();
        } catch (\Throwable $exception) {
            $payload = [
                'operation' => 'setup',
                'succeeded' => false,
                'configuration_path' => $this->stringOption($input, 'config'),
                'configuration' => null,
                'runtime' => $this->setup->inspect()->toArray(),
                'warm_requested' => $input->getOption('no-warm') !== true,
                'maintenance' => null,
                'errors' => [$exception->getMessage()],
            ];
            $succeeded = false;
        }

        if ($input->getOption('json') === true || !$input->isInteractive()) {
            $output->writeln($this->json($payload));
        } else {
            $output->writeln(
                $succeeded
                    ? '<info>Scripture setup completed.</info>'
                    : '<error>Scripture setup failed.</error>',
            );
            $output->writeln($this->json($payload));
        }

        return $succeeded ? 0 : 1;
    }

    /**
     * Resolves or interactively requests the durable configuration path.
     *
     * @param InputInterface $input Bound command input.
     * @param OutputInterface $output Command output.
     *
     * @return string
     * @since 1.0.0
     */
    private function configurationPath(InputInterface $input, OutputInterface $output): string
    {
        $path = ConfigurationPath::resolve($this->stringOption($input, 'config'));

        if ($path === null && $input->isInteractive()) {
            $path = ConfigurationPath::resolve($this->question(
                $input,
                $output,
                'Absolute Scripture configuration file path',
                null,
            ));
        }

        if ($path === null) {
            throw new \InvalidArgumentException(
                'Supply --config or set ' . ConfigurationPath::ENVIRONMENT_VARIABLE . '.',
            );
        }

        return $path;
    }

    /**
     * Collects explicit options and optional interactive values.
     *
     * @param InputInterface $input Bound command input.
     * @param OutputInterface $output Command output.
     * @param Configuration $current Current configuration.
     *
     * @return array<string, bool|int|string|list<string>|null>
     * @since 1.0.0
     */
    private function values(
        InputInterface $input,
        OutputInterface $output,
        Configuration $current,
    ): array {
        if ($input->getOption('auto-refresh') === true && $input->getOption('no-auto-refresh') === true) {
            throw new \InvalidArgumentException('--auto-refresh and --no-auto-refresh cannot be combined.');
        }

        $values = [];
        $options = [
            'module-path' => 'module_path',
            'cache-path' => 'cache_path',
            'refresh-interval' => 'refresh_interval',
            'lock-timeout' => 'lock_timeout',
        ];

        foreach ($options as $option => $setting) {
            $value = $this->stringOption($input, $option);

            if ($value !== null) {
                $values[$setting] = $value;
            }
        }

        $modules = ModuleOption::normalize($input->getOption('module'));

        if ($modules !== []) {
            $values['modules'] = $modules;
        }

        if ($input->getOption('auto-refresh') === true) {
            $values['auto_refresh'] = true;
        } elseif ($input->getOption('no-auto-refresh') === true) {
            $values['auto_refresh'] = false;
        }

        if (!$input->isInteractive()) {
            return $values;
        }

        $values['module_path'] = $this->question(
            $input,
            $output,
            'Installed SWORD module root',
            $this->stringValue($values['module_path'] ?? $current->modulePath()),
        );
        $values['cache_path'] = $this->question(
            $input,
            $output,
            'Scripture cache root',
            $this->stringValue($values['cache_path'] ?? $current->cachePath()),
        );
        $values['refresh_interval'] = $this->question(
            $input,
            $output,
            'Snapshot refresh interval',
            $this->stringValue($values['refresh_interval'] ?? $current->refreshIntervalSpec()),
        );
        $values['lock_timeout'] = $this->question(
            $input,
            $output,
            'Lifecycle lock timeout in seconds',
            $this->stringValue($values['lock_timeout'] ?? (string) $current->lockTimeout()),
        );
        $currentModules = $values['modules'] ?? $current->modules();
        $moduleText = $this->question(
            $input,
            $output,
            'Installed translation identifiers, comma separated',
            implode(',', is_array($currentModules) ? $currentModules : []),
        );
        $values['modules'] = $moduleText === null ? [] : $moduleText;

        if (!isset($values['auto_refresh'])) {
            $values['auto_refresh'] = $this->confirmation(
                $input,
                $output,
                'Refresh stale snapshots on query?',
                $current->autoRefresh(),
            );
        }

        return $values;
    }

    /**
     * Asks one interactive text question.
     *
     * @param InputInterface $input Bound command input.
     * @param OutputInterface $output Command output.
     * @param string $label Prompt label.
     * @param string|null $default Default answer.
     *
     * @return string|null
     * @since 1.0.0
     */
    private function question(
        InputInterface $input,
        OutputInterface $output,
        string $label,
        ?string $default,
    ): ?string {
        $answer = $this->questionHelper()->ask($input, $output, new Question($label . ': ', $default));

        if ($answer === null) {
            return null;
        }

        if (!is_string($answer)) {
            throw new \UnexpectedValueException(sprintf('The "%s" answer must be text.', $label));
        }

        return trim($answer);
    }

    /**
     * Asks one interactive confirmation.
     *
     * @param InputInterface $input Bound command input.
     * @param OutputInterface $output Command output.
     * @param string $label Prompt label.
     * @param bool $default Default answer.
     *
     * @return bool
     * @since 1.0.0
     */
    private function confirmation(
        InputInterface $input,
        OutputInterface $output,
        string $label,
        bool $default,
    ): bool {
        $answer = $this->questionHelper()->ask(
            $input,
            $output,
            new ConfirmationQuestion($label . ' ', $default),
        );

        return $answer === true;
    }

    /**
     * Resolves the Symfony question helper used by Joomla Console.
     *
     * @return QuestionHelper
     * @since 1.0.0
     */
    private function questionHelper(): QuestionHelper
    {
        $helper = $this->getHelper('question');

        if (!$helper instanceof QuestionHelper) {
            throw new \LogicException('The Joomla Console question helper is unavailable.');
        }

        return $helper;
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
     * Returns a nullable setting as a text default.
     *
     * @param mixed $value Candidate value.
     *
     * @return string|null
     * @since 1.0.0
     */
    private function stringValue(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
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
