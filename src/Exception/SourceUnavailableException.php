<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Exception;

/**
 * The selection root is missing, escapes the connection root, or the
 * listing broke off. The listing is then incomplete: a run that sees this
 * exception is not clean and must not remove vanished documents.
 */
final class SourceUnavailableException extends \RuntimeException implements DocumentSourceException
{
}
