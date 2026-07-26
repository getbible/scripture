<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Console;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Configuration\JsonConfigurationRepositoryFactory;
use GetBible\Scripture\Console\DoctorCommand;
use GetBible\Scripture\Console\SetupCommand;
use GetBible\Scripture\Maintenance\MaintenanceResult;
use GetBible\Scripture\Setup\ApplicationWarmerInterface;
use GetBible\Scripture\Setup\RuntimePrerequisiteInspectorInterface;
use GetBible\Scripture\Setup\RuntimePrerequisiteReport;
use GetBible\Scripture\Setup\SetupService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

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
        $tester = new CommandTester(new SetupCommand($this->service()));

        $exitCode = $tester->execute(
            [
                '--config' => $path,
                '--cache-path' => '/srv/scripture-cache',
                '--module' => ['KJV', 'WEB'],
                '--no-warm' => true,
                '--json' => true,
            ],
            ['interactive' => false],
        );
        $payload = json_decode($tester->getDisplay(), true, 32, JSON_THROW_ON_ERROR);

        self::assertSame(0, $exitCode);
        self::assertIsArray($payload);
        self::assertSame('setup', $payload['operation'] ?? null);
        self::assertTrue($payload['succeeded'] ?? false);
        self::assertSame(['KJV', 'WEB'], $payload['configuration']['modules'] ?? null);
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
        $setup = new CommandTester(new SetupCommand($service));
        $setup->execute(
            [
                '--config' => $path,
                '--cache-path' => '/srv/scripture-cache',
                '--no-warm' => true,
                '--json' => true,
            ],
            ['interactive' => false],
        );
        $before = file_get_contents($path);
        $tester = new CommandTester(new DoctorCommand($service));

        $exitCode = $tester->execute(
            [
                '--config' => $path,
                '--json' => true,
            ],
            ['interactive' => false],
        );
        $payload = json_decode($tester->getDisplay(), true, 32, JSON_THROW_ON_ERROR);

        self::assertSame(0, $exitCode);
        self::assertIsArray($payload);
        self::assertSame('doctor', $payload['operation'] ?? null);
        self::assertTrue($payload['runtime']['ready'] ?? false);
        self::assertSame('/srv/scripture-cache', $payload['configuration']['cache_path'] ?? null);
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
            $tester = new CommandTester(new SetupCommand($this->service()));
            $exitCode = $tester->execute(
                [
                    '--cache-path' => '/srv/scripture-cache',
                    '--no-warm' => true,
                    '--json' => true,
                ],
                ['interactive' => false],
            );
            $payload = json_decode($tester->getDisplay(), true, 32, JSON_THROW_ON_ERROR);

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

/**
 * Supplies a compatible runtime to command tests.
 *
 * @since 1.0.0
 */
final class CommandRuntimeInspector implements RuntimePrerequisiteInspectorInterface
{
    /**
     * Returns a compatible native report.
     *
     * @return RuntimePrerequisiteReport
     * @since 1.0.0
     */
    public function inspect(): RuntimePrerequisiteReport
    {
        return new RuntimePrerequisiteReport(
            true,
            true,
            '0.1.0',
            RuntimePrerequisiteReport::EXPECTED_ABI,
            RuntimePrerequisiteReport::EXPECTED_CONTRACT,
            '0.3.0',
        );
    }
}

/**
 * Fails if a command unexpectedly requests warming.
 *
 * @since 1.0.0
 */
final class CommandApplicationWarmer implements ApplicationWarmerInterface
{
    /**
     * Rejects unexpected warming in command tests.
     *
     * @param Configuration $configuration Candidate configuration.
     * @param list<string> $modules Module targets.
     *
     * @return MaintenanceResult
     * @since 1.0.0
     */
    public function warm(Configuration $configuration, array $modules = []): MaintenanceResult
    {
        throw new \LogicException('Command test unexpectedly requested warming.');
    }
}
