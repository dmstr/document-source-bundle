<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Selection;

/**
 * A file the selection yielded; read it with {@see Selection::read()}.
 */
final readonly class SelectedFile
{
    public function __construct(
        /** Path relative to the selection root, forward slashes, no leading slash: the document's source key. */
        public string $sourceKey,
        /** Path relative to the connection root, as the operator expects it. */
        public string $path,
        /** Size from the listing in bytes; {@see Selection::read()} checks the size again before reading. */
        public ?int $listedSize = null,
        /** Unix timestamp of the last modification from the listing. */
        public ?int $lastModified = null,
    ) {
    }
}
