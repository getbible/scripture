<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Contract;

use GetBible\Scripture\Exception\ContractException;

/**
 * Immutable SWORD enumeration containing its numeric code and stable name.
 *
 * @since 0.1.0
 */
final class EnumValue
{
    /**
     * Native unsigned byte code.
     *
     * @var int
     * @since 0.1.0
     */
    private int $code;

    /**
     * Stable producer name.
     *
     * @var string
     * @since 0.1.0
     */
    private string $name;

    /**
     * Creates an enum value.
     *
     * @param int $code Native code.
     * @param string $name Stable name.
     *
     * @since 0.1.0
     */
    private function __construct(int $code, string $name)
    {
        $this->code = $code;
        $this->name = $name;
    }

    /**
     * Validates and creates an enum from a contract object.
     *
     * @param array<array-key, mixed> $value Candidate enum.
     * @param string $context Human-readable context.
     *
     * @return self
     * @since 0.1.0
     */
    public static function fromArray(array $value, string $context): self
    {
        $value = StructuredData::object($value, $context);
        $code = $value['code'] ?? null;
        $name = $value['name'] ?? null;

        if (!is_int($code) || $code < 0 || $code > 255 || !is_string($name)) {
            throw new ContractException(sprintf('%s is not a valid SWORD enumeration.', $context));
        }

        return new self($code, $name);
    }

    /**
     * Returns the native unsigned byte code.
     *
     * @return int
     * @since 0.1.0
     */
    public function code(): int
    {
        return $this->code;
    }

    /**
     * Returns the stable producer name.
     *
     * @return string
     * @since 0.1.0
     */
    public function name(): string
    {
        return $this->name;
    }
}
