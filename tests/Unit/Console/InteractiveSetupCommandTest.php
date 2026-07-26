<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Console;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Console\SetupCommand;
use GetBible\Scripture\Setup\RuntimePrerequisiteReport;
use GetBible\Scripture\Setup\SetupRequest;
use GetBible\Scripture\Setup\SetupResult;
use GetBible\Scripture\Setup\SetupServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Verifies guided setup questions and command validation.
 *
 * @since 1.0.0
 */
final class InteractiveSetupCommandTest extends TestCase
{
    /**
     * Verifies interactive answers become one explicit setup request.
     *
     * @return void
     * @since 1.0.0
     */
    public function testInteractiveAnswersAreApplied(): void
    {
        $path = '/tmp/getbible-scripture-interactive.json';
        $current = Configuration::fromEnvironment([
            'cache_path' => '/current/cache',
            'modules' => ['OLD'],
        ]);
        $runtime = $this->readyRuntime();
        $captured = null;
        $service = $this->createMock(SetupServiceInterface::class);
        $service->expects(self::once())
            ->method('configuration')
            ->with($path)
            ->willReturn($current);
        $service->method('inspect')->willReturn($runtime);
        $service->expects(self::once())
            ->method('apply')
            ->willReturnCallback(
                function (SetupRequest $request) use (&$captured, $path, $runtime): SetupResult {
                    $captured = $request;
                    $configuration = Configuration::fromEnvironment($request->values());

                    return new SetupResult(
                        $path,
                        $configuration,
                        $runtime,
                        $request->warm(),
                        null,
                    );
                },
            );
        $answers = [
            '/answers/sword',
            '/answers/cache',
            'P2D',
            '50',
            'KJV, WEB',
            false,
            false,
        ];
        $helper = $this->createMock(QuestionHelper::class);
        $helper->expects(self::exactly(7))
            ->method('ask')
            ->willReturnCallback(static function () use (&$answers): mixed {
                return array_shift($answers);
            });
        $command = new SetupCommand($service);
        $command->setHelperSet(new HelperSet(['question' => $helper]));
        $input = new ArrayInput(['--config' => $path]);
        $input->setInteractive(true);
        $output = new BufferedOutput();

        $exitCode = $command->execute($input, $output);
        $display = $output->fetch();

        self::assertSame(0, $exitCode, $display);

        if (!$captured instanceof SetupRequest) {
            self::fail('Interactive setup did not submit a setup request.');
        }

        self::assertFalse($captured->warm());
        self::assertSame([
            'module_path' => '/answers/sword',
            'cache_path' => '/answers/cache',
            'refresh_interval' => 'P2D',
            'lock_timeout' => '50',
            'modules' => 'KJV, WEB',
            'auto_refresh' => false,
        ], $captured->values());
        self::assertStringContainsString('Scripture setup completed.', $display);
    }

    /**
     * Verifies contradictory automatic-refresh options fail before applying.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsContradictoryAutoRefreshOptions(): void
    {
        $service = $this->createMock(SetupServiceInterface::class);
        $service->method('configuration')->willReturn(
            Configuration::fromEnvironment(['cache_path' => '/srv/cache']),
        );
        $service->method('inspect')->willReturn($this->readyRuntime());
        $service->expects(self::never())->method('apply');
        $input = new ArrayInput([
            '--config' => '/tmp/getbible-scripture-conflict.json',
            '--auto-refresh' => true,
            '--no-auto-refresh' => true,
            '--json' => true,
        ]);
        $input->setInteractive(false);
        $output = new BufferedOutput();

        $exitCode = (new SetupCommand($service))->execute($input, $output);
        $payload = json_decode($output->fetch(), true, 32, JSON_THROW_ON_ERROR);

        self::assertSame(1, $exitCode);
        self::assertIsArray($payload);
        self::assertSame(
            ['--auto-refresh and --no-auto-refresh cannot be combined.'],
            $payload['errors'] ?? null,
        );
    }

    /**
     * Verifies setup can request its configuration path interactively.
     *
     * @return void
     * @since 1.0.0
     */
    public function testPromptsForMissingConfigurationPath(): void
    {
        $path = '/tmp/getbible-scripture-prompted.json';
        $runtime = $this->readyRuntime();
        $captured = null;
        $service = $this->createMock(SetupServiceInterface::class);
        $service->expects(self::once())
            ->method('configuration')
            ->with($path)
            ->willReturn(Configuration::fromEnvironment(['cache_path' => '/current/cache']));
        $service->expects(self::once())
            ->method('apply')
            ->willReturnCallback(
                function (SetupRequest $request) use (&$captured, $path, $runtime): SetupResult {
                    $captured = $request;

                    return new SetupResult(
                        $path,
                        Configuration::fromEnvironment($request->values()),
                        $runtime,
                        $request->warm(),
                        null,
                    );
                },
            );
        $answers = [
            $path,
            '/answers/sword',
            '/answers/cache',
            'P3D',
            '20',
            null,
        ];
        $helper = $this->createMock(QuestionHelper::class);
        $helper->expects(self::exactly(6))
            ->method('ask')
            ->willReturnCallback(static function () use (&$answers): mixed {
                return array_shift($answers);
            });
        $command = new SetupCommand($service);
        $command->setHelperSet(new HelperSet(['question' => $helper]));
        $input = new ArrayInput([
            '--auto-refresh' => true,
            '--no-warm' => true,
            '--json' => true,
        ]);
        $input->setInteractive(true);
        $output = new BufferedOutput();

        $exitCode = $command->execute($input, $output);

        self::assertSame(0, $exitCode, $output->fetch());

        if (!$captured instanceof SetupRequest) {
            self::fail('Prompted setup did not submit a setup request.');
        }

        self::assertSame($path, $captured->configurationPath());
        self::assertTrue($captured->values()['auto_refresh'] ?? false);
        self::assertSame([], $captured->values()['modules'] ?? null);
        self::assertFalse($captured->warm());
    }

    /**
     * Verifies a non-text interactive answer becomes a stable command error.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsNonTextInteractiveAnswer(): void
    {
        $service = $this->createStub(SetupServiceInterface::class);
        $service->method('inspect')->willReturn($this->readyRuntime());
        $helper = $this->createMock(QuestionHelper::class);
        $helper->expects(self::once())->method('ask')->willReturn(42);
        $command = new SetupCommand($service);
        $command->setHelperSet(new HelperSet(['question' => $helper]));
        $input = new ArrayInput(['--json' => true]);
        $input->setInteractive(true);
        $output = new BufferedOutput();

        $exitCode = $command->execute($input, $output);
        $payload = json_decode($output->fetch(), true, 32, JSON_THROW_ON_ERROR);

        self::assertSame(1, $exitCode);
        self::assertIsArray($payload);
        self::assertSame(
            ['The "Absolute Scripture configuration file path" answer must be text.'],
            $payload['errors'] ?? null,
        );
    }

    /**
     * Verifies an incorrectly bound question helper fails with a clear error.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsUnavailableQuestionHelper(): void
    {
        $service = $this->createStub(SetupServiceInterface::class);
        $service->method('inspect')->willReturn($this->readyRuntime());
        $helpers = new HelperSet();
        $helpers->set(new FormatterHelper(), 'question');
        $command = new SetupCommand($service);
        $command->setHelperSet($helpers);
        $input = new ArrayInput(['--json' => true]);
        $input->setInteractive(true);
        $output = new BufferedOutput();

        $exitCode = $command->execute($input, $output);
        $payload = json_decode($output->fetch(), true, 32, JSON_THROW_ON_ERROR);

        self::assertSame(1, $exitCode);
        self::assertIsArray($payload);
        self::assertSame(
            ['The Joomla Console question helper is unavailable.'],
            $payload['errors'] ?? null,
        );
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
