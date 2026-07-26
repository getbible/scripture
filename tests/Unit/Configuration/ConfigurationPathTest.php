<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Configuration;

use GetBible\Scripture\Configuration\ConfigurationPath;
use GetBible\Scripture\Configuration\JsonConfigurationRepositoryFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies explicit configuration-path resolution and repository creation.
 *
 * @since 1.0.0
 */
final class ConfigurationPathTest extends TestCase
{
    /**
     * Verifies explicit paths are trimmed and normalized.
     *
     * @return void
     * @since 1.0.0
     */
    public function testNormalizesExplicitPath(): void
    {
        self::assertSame('/srv/application/scripture.json', ConfigurationPath::resolve(
            ' /srv/application/scripture.json/ ',
        ));
    }

    /**
     * Verifies the environment path is used only when no explicit path exists.
     *
     * @return void
     * @since 1.0.0
     */
    public function testResolvesEnvironmentAndExplicitPrecedence(): void
    {
        $previous = getenv(ConfigurationPath::ENVIRONMENT_VARIABLE);
        putenv(ConfigurationPath::ENVIRONMENT_VARIABLE . '=/environment/scripture.json');

        try {
            self::assertSame('/environment/scripture.json', ConfigurationPath::resolve());
            self::assertSame('/explicit/scripture.json', ConfigurationPath::resolve(
                '/explicit/scripture.json',
            ));

            $repository = (new JsonConfigurationRepositoryFactory())->create();

            self::assertSame('/environment/scripture.json', $repository->path());
        } finally {
            $this->restoreEnvironment(ConfigurationPath::ENVIRONMENT_VARIABLE, $previous);
        }
    }

    /**
     * Verifies absent optional persistence remains an explicit null repository.
     *
     * @return void
     * @since 1.0.0
     */
    public function testFactorySupportsNonPersistentConfiguration(): void
    {
        $previous = getenv(ConfigurationPath::ENVIRONMENT_VARIABLE);
        putenv(ConfigurationPath::ENVIRONMENT_VARIABLE);

        try {
            $repository = (new JsonConfigurationRepositoryFactory())->create();

            self::assertNull($repository->path());
            self::assertFalse($repository->exists());
            self::assertSame([], $repository->load());
        } finally {
            $this->restoreEnvironment(ConfigurationPath::ENVIRONMENT_VARIABLE, $previous);
        }
    }

    /**
     * Verifies unsafe or ambiguous paths are rejected.
     *
     * @param string $path Invalid path.
     * @param string $message Expected diagnostic fragment.
     *
     * @return void
     * @since 1.0.0
     */
    #[DataProvider('invalidPathProvider')]
    public function testRejectsInvalidPaths(string $path, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        ConfigurationPath::resolve($path);
    }

    /**
     * Supplies invalid path cases.
     *
     * @return iterable<string, array{string, string}>
     * @since 1.0.0
     */
    public static function invalidPathProvider(): iterable
    {
        yield 'relative' => ['configuration.json', 'must be absolute'];
        yield 'root' => ['/', 'must identify a file'];
        yield 'nul byte' => ["/srv/configuration\0.json", 'cannot contain NUL'];
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
