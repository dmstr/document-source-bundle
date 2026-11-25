<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Selection;

use Dmstr\DocumentSource\Exception\SourceUnavailableException;
use Dmstr\DocumentSource\Filesystem\LocalRootAllowList;
use Dmstr\DocumentSource\Filesystem\SourceFilesystem;
use League\Flysystem\FilesystemException;

/**
 * Opens a {@see Selection}: checks the selection root once, before anything
 * is listed, so a missing or escaping root fails the run instead of looking
 * like an empty source (which would remove every document of the scope).
 *
 * Usage in a sync run:
 *
 *     $selection = $selector->select($sourceFilesystem, $criteria);
 *     foreach ($selection as $file) {            // SelectedFile
 *         $seen[] = $file->sourceKey;
 *         $read = $selection->read($file, $maxBytes);
 *         if (!$read->isOk()) { ...failed item, run not clean...; continue; }
 *         $outcome = $sink->import($scope, $read->item);
 *     }
 *     // $selection->isComplete() is true once the listing ran to its end;
 *     // a broken listing throws SourceUnavailableException instead.
 *     $sink->deleteVanished($scope, $seen, $clean && $selection->isComplete());
 */
final class FileSelector
{
    /**
     * @throws SourceUnavailableException when the selection root does not exist, is not a directory or escapes the connection root
     */
    public function select(SourceFilesystem $filesystem, SelectionCriteria $criteria): Selection
    {
        if ($filesystem->isLocal()) {
            return new Selection($filesystem, $criteria, $this->localBase($filesystem, $criteria));
        }

        if ('' !== $criteria->root) {
            try {
                $exists = $filesystem->operator->directoryExists($criteria->root);
            } catch (FilesystemException $e) {
                throw new SourceUnavailableException(\sprintf('The selection root "%s" cannot be checked: %s', $criteria->root, $e->getMessage()), 0, $e);
            }
            if (!$exists) {
                throw new SourceUnavailableException(\sprintf('The selection root "%s" does not exist.', $criteria->root));
            }
        }

        return new Selection($filesystem, $criteria, null);
    }

    /**
     * Real path of the selection root; it must lie within the connection
     * root (a symlinked directory as selection root is refused).
     */
    private function localBase(SourceFilesystem $filesystem, SelectionCriteria $criteria): string
    {
        $connectionRoot = (string) $filesystem->localRoot;
        clearstatcache();
        $base = '' === $criteria->root ? realpath($connectionRoot) : realpath($connectionRoot.'/'.$criteria->root);

        if (false === $base) {
            throw new SourceUnavailableException(\sprintf('The selection root "%s" does not exist.', '' === $criteria->root ? '/' : $criteria->root));
        }
        if (!LocalRootAllowList::isWithin($base, $connectionRoot)) {
            throw new SourceUnavailableException(\sprintf('The selection root "%s" leads outside the connection root (symlink).', $criteria->root));
        }
        if (!is_dir($base)) {
            throw new SourceUnavailableException(\sprintf('The selection root "%s" is not a directory.', $criteria->root));
        }

        return $base;
    }
}
