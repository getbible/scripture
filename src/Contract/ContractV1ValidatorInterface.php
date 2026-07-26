<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Contract;

/**
 * Validates one complete streamed getBibleSword NDJSON v1 operation.
 *
 * @since 0.1.0
 */
interface ContractV1ValidatorInterface
{
    /**
     * Validates a readable stream from its current position to EOF.
     *
     * @param resource $stream Readable binary stream.
     * @param RecordObserverInterface|null $observer Optional validated-record observer.
     *
     * @return ValidationResult
     * @since 0.1.0
     */
    public function validate(mixed $stream, ?RecordObserverInterface $observer = null): ValidationResult;
}
