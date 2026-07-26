<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Contract;

/**
 * Receives validated records with their exact source-file coordinates.
 *
 * @since 0.1.0
 */
interface RecordObserverInterface
{
    /**
     * Observes one record after its record-level validation succeeds.
     *
     * @param array<string, mixed> $record Decoded record.
     * @param int $offset Byte offset of the serialized line.
     * @param int $length Serialized line length including LF.
     *
     * @return void
     * @since 0.1.0
     */
    public function onRecord(array $record, int $offset, int $length): void;
}
