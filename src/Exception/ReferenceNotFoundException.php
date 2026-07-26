<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Exception;

/**
 * Reports a book, chapter, or verse absent from the selected translation.
 *
 * @since 0.1.0
 */
final class ReferenceNotFoundException extends ScriptureException
{
}
