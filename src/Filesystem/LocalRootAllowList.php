<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Filesystem;

use Dmstr\DocumentSource\Exception\PathNotAllowedException;

/**
 * The directories `local` connections may read below
 * (`dmstr_document_source.local_roots`, env DOCUMENT_SOURCE_LOCAL_ROOTS).
 *
 * Every check compares real paths: the candidate and each entry are
 * resolved with realpath(), so a symlink in either path cannot move a root
 * out of the allowed tree. A path is allowed when its real path equals an
 * entry's real path or lies below it. Entries that are empty, relative or
 * do not exist are ignored; an empty list allows nothing (fail closed).
 */
final class LocalRootAllowList
{
    /** @var list<string> */
    private readonly array $entries;

    /**
     * @param iterable<mixed>|string|null $roots a list, or a comma-separated string
     */
    public function __construct(iterable|string|null $roots = [])
    {
        if (null === $roots) {
            $roots = [];
        } elseif (\is_string($roots)) {
            $roots = explode(',', $roots);
        }

        $entries = [];
        foreach ($roots as $root) {
            if (!\is_string($root)) {
                continue;
            }
            $root = trim($root);
            if ('' === $root || !str_starts_with($root, '/') || str_contains($root, "\0")) {
                continue;
            }
            $entries[] = $root;
        }
        $this->entries = array_values(array_unique($entries));
    }

    /**
     * The configured entries as given (not resolved), for messages and docs.
     *
     * @return list<string>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Resolves $path and returns its real path when it is allowed.
     *
     * @throws PathNotAllowedException when the path is relative, does not exist or lies outside every entry
     */
    public function resolve(string $path): string
    {
        if ('' === $path || !str_starts_with($path, '/') || str_contains($path, "\0")) {
            throw new PathNotAllowedException(\sprintf('The local root "%s" is not an absolute path.', self::printable($path)));
        }

        $real = realpath($path);
        if (false === $real) {
            throw new PathNotAllowedException(\sprintf('The local root "%s" does not exist.', $path));
        }

        if (!$this->contains($real)) {
            throw new PathNotAllowedException(\sprintf(
                'The local root "%s" is outside the allowed directories (%s, %s).',
                $path,
                [] === $this->entries ? 'none configured' : implode(', ', $this->entries),
                'dmstr_document_source.local_roots / DOCUMENT_SOURCE_LOCAL_ROOTS',
            ));
        }

        return $real;
    }

    /**
     * Whether an already resolved real path lies within an entry.
     */
    public function contains(string $realPath): bool
    {
        foreach ($this->entries as $entry) {
            $realEntry = realpath($entry);
            if (false !== $realEntry && self::isWithin($realPath, $realEntry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether $path equals $base or lies below it. Both must be real paths
     * (absolute, normalised, no symlinks); a prefix of a sibling name does
     * not count (`/mnt/uploads2` is not within `/mnt/uploads`).
     */
    public static function isWithin(string $path, string $base): bool
    {
        if ('/' === $base) {
            return str_starts_with($path, '/');
        }

        return $path === $base || str_starts_with($path, rtrim($base, '/').'/');
    }

    private static function printable(string $path): string
    {
        return str_replace("\0", '\0', $path);
    }
}
