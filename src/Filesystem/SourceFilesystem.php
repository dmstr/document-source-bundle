<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Filesystem;

use League\Flysystem\FilesystemOperator;

/**
 * The read side of one source connection, built per connection at runtime
 * by {@see SourceFilesystemFactoryInterface}; never a container service.
 *
 * Paths given to the operator are relative to the connection root. For the
 * `local` adapter {@see $localRoot} is the real path of that root, and the
 * selection checks the real path of every file against it (symlink guard);
 * remote adapters have no local root and no symlinks.
 */
final readonly class SourceFilesystem
{
    public function __construct(
        public FilesystemOperator $operator,
        /** Adapter name of the connection (currently always `local`). */
        public string $adapter,
        /** Real path of the connection root for `local`, null for remote adapters. */
        public ?string $localRoot = null,
    ) {
    }

    public function isLocal(): bool
    {
        return null !== $this->localRoot;
    }
}
