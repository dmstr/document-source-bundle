<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Selection;

/**
 * A listed file the selection did not yield because it is unsafe to read:
 * its real path leaves the selection root (symlink escape), it is not a
 * regular file (FIFO, socket, device) or its name is unusable as a key
 * (a backslash, which the local adapter lists as a slash, so the name
 * collides with another path).
 * Not a failure of the run: the file is never read, and it does not count
 * as seen, like a symlink the adapter skips.
 */
final readonly class RejectedFile
{
    public const string OUTSIDE_ROOT = 'outside_root';
    public const string NOT_A_REGULAR_FILE = 'not_a_regular_file';
    public const string UNSUPPORTED_NAME = 'unsupported_name';

    public function __construct(
        /** Path relative to the connection root, as listed. */
        public string $path,
        /** One of the constants of this class. */
        public string $reason,
    ) {
    }
}
