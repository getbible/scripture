<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Maintenance;

/**
 * Immutable durable state used by interval-based maintenance decisions.
 *
 * @since 0.3.0
 */
final class MaintenanceState
{
    /**
     * Stable serialized state format.
     *
     * @since 0.3.0
     */
    public const FORMAT = 'getbible.scripture.maintenance/v1';

    /**
     * Creates one maintenance state value.
     *
     * @param \DateTimeImmutable|null $lastAttemptAt Last attempted maintenance time.
     * @param \DateTimeImmutable|null $lastSuccessAt Last completely successful maintenance time.
     * @param \DateTimeImmutable|null $lastFailureAt Last failed maintenance time.
     * @param string|null $lastError Last failure summary.
     * @param int $consecutiveFailures Number of failures since the last success.
     *
     * @since 0.3.0
     */
    private function __construct(
        private ?\DateTimeImmutable $lastAttemptAt,
        private ?\DateTimeImmutable $lastSuccessAt,
        private ?\DateTimeImmutable $lastFailureAt,
        private ?string $lastError,
        private int $consecutiveFailures,
    ) {
        if ($consecutiveFailures < 0) {
            throw new \InvalidArgumentException('Consecutive maintenance failures cannot be negative.');
        }
    }

    /**
     * Creates empty state for a deployment with no prior maintenance.
     *
     * @return self
     * @since 0.3.0
     */
    public static function empty(): self
    {
        return new self(null, null, null, null, 0);
    }

    /**
     * Hydrates and validates serialized state.
     *
     * @param array<string, mixed> $state Serialized state.
     *
     * @return self
     * @since 0.3.0
     */
    public static function fromArray(array $state): self
    {
        if (($state['format'] ?? null) !== self::FORMAT
            || !is_int($state['consecutive_failures'] ?? null)
        ) {
            throw new \UnexpectedValueException('Maintenance state has an unsupported structure.');
        }

        $lastError = $state['last_error'] ?? null;

        if ($lastError !== null && !is_string($lastError)) {
            throw new \UnexpectedValueException('Maintenance state last_error must be a string or null.');
        }

        return new self(
            self::date($state['last_attempt_at'] ?? null, 'last_attempt_at'),
            self::date($state['last_success_at'] ?? null, 'last_success_at'),
            self::date($state['last_failure_at'] ?? null, 'last_failure_at'),
            $lastError,
            $state['consecutive_failures'],
        );
    }

    /**
     * Returns state after a completely successful maintenance run.
     *
     * @param \DateTimeImmutable $completedAt Completion time.
     *
     * @return self
     * @since 0.3.0
     */
    public function succeededAt(\DateTimeImmutable $completedAt): self
    {
        return new self($completedAt, $completedAt, $this->lastFailureAt, null, 0);
    }

    /**
     * Returns state after a failed or partially failed maintenance run.
     *
     * @param \DateTimeImmutable $completedAt Completion time.
     * @param string $error Concise failure summary.
     *
     * @return self
     * @since 0.3.0
     */
    public function failedAt(\DateTimeImmutable $completedAt, string $error): self
    {
        $error = trim($error);

        if ($error === '') {
            throw new \InvalidArgumentException('A failed maintenance run requires an error summary.');
        }

        return new self(
            $completedAt,
            $this->lastSuccessAt,
            $completedAt,
            $error,
            $this->consecutiveFailures + 1,
        );
    }

    /**
     * Reports whether refresh is due at a given time.
     *
     * @param \DateTimeImmutable $now Current time.
     * @param \DateInterval $interval Required interval after the last success.
     *
     * @return bool
     * @since 0.3.0
     */
    public function isDue(\DateTimeImmutable $now, \DateInterval $interval): bool
    {
        $next = $this->nextDueAt($interval);

        return $next === null || $next <= $now;
    }

    /**
     * Returns the next due time or null when refresh has never succeeded.
     *
     * @param \DateInterval $interval Required interval after the last success.
     *
     * @return \DateTimeImmutable|null
     * @since 0.3.0
     */
    public function nextDueAt(\DateInterval $interval): ?\DateTimeImmutable
    {
        return $this->lastSuccessAt?->add($interval);
    }

    /**
     * Returns the last maintenance attempt.
     *
     * @return \DateTimeImmutable|null
     * @since 0.3.0
     */
    public function lastAttemptAt(): ?\DateTimeImmutable
    {
        return $this->lastAttemptAt;
    }

    /**
     * Returns the last completely successful maintenance time.
     *
     * @return \DateTimeImmutable|null
     * @since 0.3.0
     */
    public function lastSuccessAt(): ?\DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }

    /**
     * Returns the last failed maintenance time.
     *
     * @return \DateTimeImmutable|null
     * @since 0.3.0
     */
    public function lastFailureAt(): ?\DateTimeImmutable
    {
        return $this->lastFailureAt;
    }

    /**
     * Returns the last failure summary.
     *
     * @return string|null
     * @since 0.3.0
     */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Returns the number of consecutive failures since the last success.
     *
     * @return int
     * @since 0.3.0
     */
    public function consecutiveFailures(): int
    {
        return $this->consecutiveFailures;
    }

    /**
     * Returns a serialization-safe state map.
     *
     * @return array{
     *     format: string,
     *     last_attempt_at: string|null,
     *     last_success_at: string|null,
     *     last_failure_at: string|null,
     *     last_error: string|null,
     *     consecutive_failures: int
     * }
     * @since 0.3.0
     */
    public function toArray(): array
    {
        return [
            'format' => self::FORMAT,
            'last_attempt_at' => $this->lastAttemptAt?->format(DATE_ATOM),
            'last_success_at' => $this->lastSuccessAt?->format(DATE_ATOM),
            'last_failure_at' => $this->lastFailureAt?->format(DATE_ATOM),
            'last_error' => $this->lastError,
            'consecutive_failures' => $this->consecutiveFailures,
        ];
    }

    /**
     * Parses an optional serialized timestamp.
     *
     * @param mixed $value Candidate timestamp.
     * @param string $field Field name.
     *
     * @return \DateTimeImmutable|null
     * @since 0.3.0
     */
    private static function date(mixed $value, string $field): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || trim($value) === '') {
            throw new \UnexpectedValueException(sprintf('Maintenance state %s is invalid.', $field));
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $exception) {
            throw new \UnexpectedValueException(
                sprintf('Maintenance state %s is not a valid timestamp.', $field),
                0,
                $exception,
            );
        }
    }
}
