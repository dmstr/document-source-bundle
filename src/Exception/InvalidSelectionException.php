<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Exception;

/**
 * Selection criteria are malformed: a root or glob with `..`, a leading
 * slash, a backslash or a NUL byte.
 */
final class InvalidSelectionException extends \InvalidArgumentException implements DocumentSourceException
{
}
