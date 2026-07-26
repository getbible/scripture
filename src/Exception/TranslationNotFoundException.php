<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Exception;

/**
 * Reports a requested translation that is not installed or is not a Bible.
 *
 * @since 0.1.0
 */
final class TranslationNotFoundException extends ScriptureException
{
}
