<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Setup;

use GetBible\Scripture\Configuration\JsonConfigurationRepositoryFactory;
use GetBible\Scripture\Setup\ApplicationWarmerInterface;
use GetBible\Scripture\Setup\RuntimePrerequisiteInspectorInterface;
use GetBible\Scripture\Setup\RuntimePrerequisiteReport;
use GetBible\Scripture\Setup\SetupRequest;
use GetBible\Scripture\Setup\SetupService;
use PHPUnit\Framework\TestCase;

/**
 * Verifies setup orchestration failure and no-warm paths.
 *
 * @since 1.0.0
 */
final class SetupServiceFailureTest extends TestCase
{
    /**
     * Isolated configuration directory.
     *
     * @var string
     * @since 1.0.0
     */
    private string $directory;

    /**
     * Creates an isolated setup root.
     *
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . '/getbible-scripture-setup-failures-'
            . bin2hex(random_bytes(8));
    }

    /**
     * Removes setup files created by a test.
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
     * Verifies inspection delegates to the configured read-only inspector.
     *
     * @return void
     * @since 1.0.0
     */
    public function testInspectReturnsInspectorReport(): void
    {
        $report = $this->readyRuntime();
        $inspector = $this->createMock(RuntimePrerequisiteInspectorInterface::class);
        $inspector->expects(self::once())->method('inspect')->willReturn($report);
        $warmer = $this->createStub(ApplicationWarmerInterface::class);
        $service = new SetupService(
            new JsonConfigurationRepositoryFactory(),
            $inspector,
            $warmer,
        );

        self::assertSame($report, $service->inspect());
    }

    /**
     * Verifies setup refuses to invent a persistence location.
     *
     * @return void
     * @since 1.0.0
     */
    public function testApplyRequiresDurableConfigurationPath(): void
    {
        $previous = getenv('GETBIBLE_SCRIPTURE_CONFIG_PATH');
        putenv('GETBIBLE_SCRIPTURE_CONFIG_PATH');
        $service = new SetupService(
            new JsonConfigurationRepositoryFactory(),
            $this->createStub(RuntimePrerequisiteInspectorInterface::class),
            $this->createStub(ApplicationWarmerInterface::class),
        );

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Supply an absolute configuration path');

            $service->apply(new SetupRequest(null, ['cache_path' => '/srv/cache']));
        } finally {
            $this->restoreEnvironment('GETBIBLE_SCRIPTURE_CONFIG_PATH', $previous);
        }
    }

    /**
     * Verifies no-warm setup persists without invoking the warmer.
     *
     * @return void
     * @since 1.0.0
     */
    public function testNoWarmPersistsWithoutInvokingWarmer(): void
    {
        $inspector = $this->createMock(RuntimePrerequisiteInspectorInterface::class);
        $inspector->expects(self::once())->method('inspect')->willReturn($this->readyRuntime());
        $warmer = $this->createMock(ApplicationWarmerInterface::class);
        $warmer->expects(self::never())->method('warm');
        $service = new SetupService(
            new JsonConfigurationRepositoryFactory(),
            $inspector,
            $warmer,
        );

        $result = $service->apply(new SetupRequest(
            $this->directory . '/configuration.json',
            ['cache_path' => '/srv/cache'],
            false,
        ));

        self::assertTrue($result->succeeded());
        self::assertFalse($result->warmRequested());
        self::assertNull($result->maintenance());
        self::assertFileExists($this->directory . '/configuration.json');
    }

    /**
     * Verifies warming exceptions become stable setup diagnostics.
     *
     * @return void
     * @since 1.0.0
     */
    public function testConvertsWarmerExceptionToResultError(): void
    {
        $inspector = $this->createStub(RuntimePrerequisiteInspectorInterface::class);
        $inspector->method('inspect')->willReturn($this->readyRuntime());
        $warmer = $this->createMock(ApplicationWarmerInterface::class);
        $warmer->expects(self::once())
            ->method('warm')
            ->willThrowException(new \RuntimeException('snapshot storage is unavailable'));
        $service = new SetupService(
            new JsonConfigurationRepositoryFactory(),
            $inspector,
            $warmer,
        );

        $result = $service->apply(new SetupRequest(
            $this->directory . '/configuration.json',
            ['cache_path' => '/srv/cache', 'modules' => ['KJV']],
            true,
        ));

        self::assertFalse($result->succeeded());
        self::assertNull($result->maintenance());
        self::assertSame(
            ['Scripture warming failed: snapshot storage is unavailable'],
            $result->errors(),
        );
    }

    /**
     * Creates a compatible native runtime report.
     *
     * @return RuntimePrerequisiteReport
     * @since 1.0.0
     */
    private function readyRuntime(): RuntimePrerequisiteReport
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

    /**
     * Restores one process environment variable.
     *
     * @param string $name Variable name.
     * @param string|false $value Previous value.
     *
     * @return void
     * @since 1.0.0
     */
    private function restoreEnvironment(string $name, string|false $value): void
    {
        if (is_string($value)) {
            putenv($name . '=' . $value);

            return;
        }

        putenv($name);
    }
}
