<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Configuration;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Configuration\JsonConfigurationRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies persisted configuration rejects untrusted document shapes.
 *
 * @since 1.0.0
 */
final class JsonConfigurationValidationTest extends TestCase
{
    /**
     * Temporary configuration file.
     *
     * @var string
     * @since 1.0.0
     */
    private string $path;

    /**
     * Creates an unused temporary configuration path.
     *
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir()
            . '/getbible-scripture-untrusted-'
            . bin2hex(random_bytes(8))
            . '.json';
    }

    /**
     * Removes the temporary configuration file.
     *
     * @return void
     * @since 1.0.0
     */
    protected function tearDown(): void
    {
        if (is_file($this->path) || is_link($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * Verifies malformed JSON and unsupported shapes fail closed.
     *
     * @param string $payload Untrusted file payload.
     * @param string $message Expected diagnostic fragment.
     *
     * @return void
     * @since 1.0.0
     */
    #[DataProvider('invalidDocumentProvider')]
    public function testRejectsInvalidDocuments(string $payload, string $message): void
    {
        self::assertNotFalse(file_put_contents($this->path, $payload));

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        (new JsonConfigurationRepository($this->path))->load();
    }

    /**
     * Supplies untrusted configuration documents.
     *
     * @return iterable<string, array{string, string}>
     * @since 1.0.0
     */
    public static function invalidDocumentProvider(): iterable
    {
        yield 'invalid JSON' => ['{', 'not valid JSON'];
        yield 'top-level list' => ['[]', 'document format is invalid'];
        yield 'settings list' => [
            '{"format":"getbible.scripture.configuration/v1","settings":[]}',
            'settings map is invalid',
        ];
        yield 'unknown setting' => [
            '{"format":"getbible.scripture.configuration/v1","settings":{"secret":"value"}}',
            'contains an unknown setting',
        ];
        yield 'nested setting' => [
            '{"format":"getbible.scripture.configuration/v1","settings":{"modules":[["KJV"]]}}',
            'must contain only strings',
        ];
        yield 'associative module map' => [
            '{"format":"getbible.scripture.configuration/v1","settings":{"modules":{"one":"KJV"}}}',
            'must be a list of strings',
        ];
        yield 'scalar modules' => [
            '{"format":"getbible.scripture.configuration/v1","settings":{"modules":"KJV"}}',
            'must be a list of strings',
        ];
        yield 'array cache path' => [
            '{"format":"getbible.scripture.configuration/v1","settings":{"cache_path":["/srv/cache"]}}',
            'must be a string',
        ];
        yield 'textual boolean' => [
            '{"format":"getbible.scripture.configuration/v1","settings":{"auto_refresh":"yes"}}',
            'must be a boolean',
        ];
        yield 'textual integer' => [
            '{"format":"getbible.scripture.configuration/v1","settings":{"lock_timeout":"30"}}',
            'must be an integer',
        ];
        yield 'integer module path' => [
            '{"format":"getbible.scripture.configuration/v1","settings":{"module_path":1}}',
            'must be a string or null',
        ];
        yield 'invalid normalized value' => [
            '{"format":"getbible.scripture.configuration/v1","settings":{"lock_timeout":0}}',
            'must be greater than zero',
        ];
    }

    /**
     * Verifies symbolic-link configuration sources are never trusted.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsSymbolicLinkConfiguration(): void
    {
        $target = $this->path . '.target';
        self::assertNotFalse(file_put_contents(
            $target,
            '{"format":"getbible.scripture.configuration/v1","settings":{}}',
        ));
        self::assertTrue(symlink($target, $this->path));

        try {
            $repository = new JsonConfigurationRepository($this->path);

            self::assertFalse($repository->exists());
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('not a readable regular file');
            $repository->load();
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    /**
     * Verifies persistence never replaces a caller-controlled symbolic link.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRefusesToSaveThroughSymbolicLink(): void
    {
        $target = $this->path . '.target';
        self::assertNotFalse(file_put_contents($target, 'unchanged'));
        self::assertTrue(symlink($target, $this->path));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Refusing to replace a symbolic-link');

            (new JsonConfigurationRepository($this->path))->save(
                Configuration::fromEnvironment(['cache_path' => '/srv/cache']),
            );
        } finally {
            self::assertSame('unchanged', file_get_contents($target));

            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    /**
     * Verifies directories are never accepted as configuration documents.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsDirectoryConfigurationPath(): void
    {
        self::assertTrue(mkdir($this->path, 0700));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('not a readable regular file');

            (new JsonConfigurationRepository($this->path))->load();
        } finally {
            rmdir($this->path);
        }
    }

    /**
     * Verifies configuration is never written beneath a symbolic-link parent.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsSymbolicLinkConfigurationDirectory(): void
    {
        $target = $this->path . '.directory';
        self::assertTrue(mkdir($target, 0700));
        self::assertTrue(symlink($target, $this->path));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('not a writable regular directory');

            (new JsonConfigurationRepository($this->path . '/configuration.json'))->save(
                Configuration::fromEnvironment(['cache_path' => '/srv/cache']),
            );
        } finally {
            if (is_link($this->path)) {
                unlink($this->path);
            }

            rmdir($target);
        }
    }

    /**
     * Verifies repository construction requires an already-normalized path.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsUnnormalizedRepositoryPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not normalized');

        new JsonConfigurationRepository($this->path . '/');
    }
}
