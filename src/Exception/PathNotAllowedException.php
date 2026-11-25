<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Exception;

/**
 * A local path does not exist, or its real path lies outside every entry
 * of the allow-list (or outside the root it must stay below).
 */
final class PathNotAllowedException extends \RuntimeException implements DocumentSourceException
{
}
