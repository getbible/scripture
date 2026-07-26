<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Setup;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Configuration\JsonConfigurationRepositoryFactory;
use GetBible\Scripture\Setup\RuntimePrerequisiteReport;
use GetBible\Scripture\Setup\SetupRequest;
use GetBible\Scripture\Setup\SetupService;
use PHPUnit\Framework\TestCase;

/**
 * Verifies setup validation, persistence, and warming coordination.
 *
 * @since 1.0.0
 */
final class SetupServiceTest extends TestCase
{
    /**
     * Isolated test directory.
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
            . '/getbible-scripture-setup-service-'
            . bin2hex(random_bytes(8));
    }

    /**
     * Removes isolated setup files.
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
     * Verifies setup persists validated settings and warms through its seam.
     *
     * @return void
     * @since 1.0.0
     */
    public function testPersistsAndWarmsConfiguration(): void
    {
        $warmer = new RecordingApplicationWarmer();
        $service = new SetupService(
            new JsonConfigurationRepositoryFactory(),
            new FixedRuntimeInspector($this->readyRuntime()),
            $warmer,
        );
        $path = $this->directory . '/configuration.json';

        $result = $service->apply(new SetupRequest(
            $path,
            [
                'module_path' => '/srv/sword',
                'cache_path' => '/srv/cache',
                'modules' => ['KJV'],
            ],
            true,
        ));

        self::assertTrue($result->succeeded());
        self::assertSame(['KJV'], $warmer->modules);
        self::assertSame('/srv/sword', $warmer->configuration?->modulePath());
        self::assertSame(['KJV'], $service->configuration($path)->modules());
    }

    /**
     * Verifies missing native prerequisites never call the warmer.
     *
     * @return void
     * @since 1.0.0
     */
    public function testDoesNotWarmIncompatibleRuntime(): void
    {
        $warmer = new RecordingApplicationWarmer();
        $service = new SetupService(
            new JsonConfigurationRepositoryFactory(),
            new FixedRuntimeInspector(new RuntimePrerequisiteReport(
                false,
                false,
                null,
                null,
                null,
                null,
            )),
            $warmer,
        );

        $result = $service->apply(new SetupRequest(
            $this->directory . '/configuration.json',
            [
                'cache_path' => '/srv/cache',
                'modules' => ['KJV'],
            ],
            true,
        ));

        self::assertFalse($result->succeeded());
        self::assertNull($warmer->configuration);
        self::assertSame(
            ['The active PHP runtime is not ready for Scripture warming.'],
            $result->errors(),
        );
    }

    /**
     * Verifies request settings override environment and persisted JSON.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRequestOverridesEnvironmentAndPersistedConfiguration(): void
    {
        $path = $this->directory . '/configuration.json';
        $factory = new JsonConfigurationRepositoryFactory();
        $factory->create($path)->save(Configuration::fromEnvironment([
            'cache_path' => '/persisted/cache',
        ]));
        $previous = getenv('GETBIBLE_SCRIPTURE_CACHE_PATH');
        putenv('GETBIBLE_SCRIPTURE_CACHE_PATH=/environment/cache');

        try {
            $service = new SetupService(
                $factory,
                new FixedRuntimeInspector($this->readyRuntime()),
                new RecordingApplicationWarmer(),
            );
            $result = $service->apply(new SetupRequest(
                $path,
                ['cache_path' => '/request/cache'],
                false,
            ));

            self::assertTrue($result->succeeded());
            self::assertSame('/request/cache', $result->configuration()->cachePath());
        } finally {
            if (is_string($previous)) {
                putenv('GETBIBLE_SCRIPTURE_CACHE_PATH=' . $previous);
            } else {
                putenv('GETBIBLE_SCRIPTURE_CACHE_PATH');
            }
        }
    }

    /**
     * Creates a compatible runtime report.
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
}
