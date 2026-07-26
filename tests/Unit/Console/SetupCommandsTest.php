<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Console;

use GetBible\Scripture\Configuration\JsonConfigurationRepositoryFactory;
use GetBible\Scripture\Console\DoctorCommand;
use GetBible\Scripture\Console\SetupCommand;
use GetBible\Scripture\Contract\StructuredData;
use GetBible\Scripture\Setup\SetupService;
use Joomla\Console\Command\AbstractCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Verifies deterministic non-interactive setup and doctor command behavior.
 *
 * @since 1.0.0
 */
final class SetupCommandsTest extends TestCase
{
    /**
     * Isolated command test directory.
     *
     * @var string
     * @since 1.0.0
     */
    private string $directory;

    /**
     * Creates an isolated command root.
     *
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . '/getbible-scripture-setup-command-'
            . bin2hex(random_bytes(8));
    }

    /**
     * Removes isolated command files.
     *
     * @return void
     * @since 1.0.0
     */
    protected function tearDown(): void
    {
        $path = $this->directory . '/configuration.json';

        if (is_file($path)) {
            unlink($path);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    /**
     * Verifies non-interactive setup writes JSON configuration without warming.
     *
     * @return void
     * @since 1.0.0
     */
    public function testNonInteractiveSetupIsDeterministic(): void
    {
        $path = $this->directory . '/configuration.json';
        [$exitCode, $display] = $this->execute(
            new SetupCommand($this->service()),
            [
                '--config' => $path,
                '--cache-path' => '/srv/scripture-cache',
                '--module' => ['KJV', 'WEB'],
                '--no-auto-refresh' => true,
                '--no-warm' => true,
                '--json' => true,
            ],
        );
        $payload = StructuredData::object(
            json_decode($display, true, 32, JSON_THROW_ON_ERROR),
            'Setup command response',
        );
        $configuration = StructuredData::object(
            $payload['configuration'] ?? null,
            'Setup command configuration',
        );

        self::assertSame(0, $exitCode);
        self::assertSame('setup', $payload['operation'] ?? null);
        self::assertTrue($payload['succeeded'] ?? false);
        self::assertSame(['KJV', 'WEB'], $configuration['modules'] ?? null);
        self::assertFalse($configuration['auto_refresh'] ?? true);
        self::assertFalse($payload['warm_requested'] ?? true);
        self::assertFileExists($path);
    }

    /**
     * Verifies doctor emits deterministic JSON and remains read-only.
     *
     * @return void
     * @since 1.0.0
     */
    public function testDoctorReportsRuntimeAndConfiguration(): void
    {
        $path = $this->directory . '/configuration.json';
        $service = $this->service();
        $this->execute(
            new SetupCommand($service),
            [
                '--config' => $path,
                '--cache-path' => '/srv/scripture-cache',
                '--no-warm' => true,
                '--json' => true,
            ],
        );
        $before = file_get_contents($path);
        [$exitCode, $display] = $this->execute(
            new DoctorCommand($service),
            [
                '--config' => $path,
                '--json' => true,
            ],
        );
        $payload = StructuredData::object(
            json_decode($display, true, 32, JSON_THROW_ON_ERROR),
            'Doctor command response',
        );
        $runtime = StructuredData::object(
            $payload['runtime'] ?? null,
            'Doctor command runtime',
        );
        $configuration = StructuredData::object(
            $payload['configuration'] ?? null,
            'Doctor command configuration',
        );

        self::assertSame(0, $exitCode);
        self::assertSame('doctor', $payload['operation'] ?? null);
        self::assertTrue($runtime['ready'] ?? false);
        self::assertSame('/srv/scripture-cache', $configuration['cache_path'] ?? null);
        self::assertSame($before, file_get_contents($path));
    }

    /**
     * Verifies non-interactive setup refuses to invent a configuration path.
     *
     * @return void
     * @since 1.0.0
     */
    public function testNonInteractiveSetupRequiresConfigurationPath(): void
    {
        $previous = getenv('GETBIBLE_SCRIPTURE_CONFIG_PATH');
        putenv('GETBIBLE_SCRIPTURE_CONFIG_PATH');

        try {
            [$exitCode, $display] = $this->execute(
                new SetupCommand($this->service()),
                [
                    '--cache-path' => '/srv/scripture-cache',
                    '--no-warm' => true,
                    '--json' => true,
                ],
            );
            $payload = json_decode($display, true, 32, JSON_THROW_ON_ERROR);

            self::assertSame(1, $exitCode);
            self::assertIsArray($payload);
            self::assertFalse($payload['succeeded'] ?? true);
            self::assertSame(
                ['Supply --config or set GETBIBLE_SCRIPTURE_CONFIG_PATH.'],
                $payload['errors'] ?? null,
            );
        } finally {
            if (is_string($previous)) {
                putenv('GETBIBLE_SCRIPTURE_CONFIG_PATH=' . $previous);
            } else {
                putenv('GETBIBLE_SCRIPTURE_CONFIG_PATH');
            }
        }
    }

    /**
     * Executes a Joomla command through its public input/output boundary.
     *
     * @param AbstractCommand $command Command under test.
     * @param array<string, bool|string|list<string>> $parameters Input parameters.
     *
     * @return array{int, string}
     * @since 1.0.0
     */
    private function execute(AbstractCommand $command, array $parameters): array
    {
        $input = new ArrayInput($parameters);
        $input->setInteractive(false);
        $output = new BufferedOutput();
        $exitCode = $command->execute($input, $output);

        return [$exitCode, $output->fetch()];
    }

    /**
     * Creates a deterministic setup service.
     *
     * @return SetupService
     * @since 1.0.0
     */
    private function service(): SetupService
    {
        return new SetupService(
            new JsonConfigurationRepositoryFactory(),
            new CommandRuntimeInspector(),
            new CommandApplicationWarmer(),
        );
    }
}
