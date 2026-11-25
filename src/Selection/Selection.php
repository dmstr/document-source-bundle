<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Selection;

use Dmstr\DocumentSource\Exception\SourceUnavailableException;
use Dmstr\DocumentSource\Filesystem\LocalRootAllowList;
use Dmstr\DocumentSource\Filesystem\SourceFilesystem;
use Dmstr\DocumentSource\Import\SourceItem;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;

/**
 * The files of one source that match the criteria, opened by
 * {@see FileSelector::select()}.
 *
 * Iterating lists the selection root recursively and yields a
 * {@see SelectedFile} per regular file that matches the globs. Directories
 * are not yielded; symlinks are skipped by the adapter (SKIP_LINKS). For
 * `local` the real path of every matching file is checked as well, because
 * the adapter's skip only covers the link entries themselves: a file whose
 * real path leaves the selection root, a non-regular file (FIFO, socket,
 * device) and a name that cannot be told apart from another file (a
 * backslash in the name) are collected in {@see rejected()}
 * instead of being yielded. A listing error throws
 * {@see SourceUnavailableException}; {@see isComplete()} is true only after
 * a full pass.
 *
 * {@see read()} reads one file, guarded again (the file may have changed
 * since the listing), and never throws for item-level problems.
 *
 * @implements \IteratorAggregate<int, SelectedFile>
 */
final class Selection implements \IteratorAggregate
{
    /** @var list<RejectedFile> */
    private array $rejected = [];
    private bool $complete = false;

    /**
     * @param string|null $localBase real path of the selection root for `local`, null for remote adapters
     */
    public function __construct(
        public readonly SourceFilesystem $filesystem,
        public readonly SelectionCriteria $criteria,
        private readonly ?string $localBase,
    ) {
    }

    /**
     * @return \Generator<int, SelectedFile>
     *
     * @throws SourceUnavailableException when the listing breaks off
     */
    public function getIterator(): \Generator
    {
        $this->rejected = [];
        $this->complete = false;
        $root = $this->criteria->root;
        $prefix = '' === $root ? '' : $root.'/';
        /** @var array<string, true> $listed */
        $listed = [];

        try {
            foreach ($this->filesystem->operator->listContents($root, true) as $attributes) {
                if (!$attributes instanceof FileAttributes) {
                    continue;
                }
                $path = $attributes->path();
                if ('' !== $prefix && !str_starts_with($path, $prefix)) {
                    $this->rejected[] = new RejectedFile($path, RejectedFile::OUTSIDE_ROOT);
                    continue;
                }
                $key = substr($path, \strlen($prefix));

                // The local adapter turns backslashes in names into slashes, so
                // `a\b.md` is listed as `a/b.md`; a second listing of the same
                // path is such a name and is never read in place of the first.
                if (!SourceItem::isValidSourceKey($key) || isset($listed[$path])) {
                    $this->rejected[] = new RejectedFile($path, RejectedFile::UNSUPPORTED_NAME);
                    continue;
                }
                $listed[$path] = true;
                if (!$this->criteria->matches($key)) {
                    continue;
                }

                $rejection = $this->localRejection($path);
                if (null !== $rejection) {
                    $this->rejected[] = new RejectedFile($path, $rejection);
                    continue;
                }

                yield new SelectedFile($key, $path, $attributes->fileSize(), $attributes->lastModified());
            }
        } catch (FilesystemException $e) {
            throw new SourceUnavailableException(\sprintf('Listing the selection root "%s" failed: %s', '' === $root ? '/' : $root, $e->getMessage()), 0, $e);
        }

        $this->complete = true;
    }

    /**
     * Files the last pass did not yield because reading them is unsafe.
     *
     * @return list<RejectedFile>
     */
    public function rejected(): array
    {
        return $this->rejected;
    }

    /** True once the last pass listed the selection root completely. */
    public function isComplete(): bool
    {
        return $this->complete;
    }

    /**
     * Reads one selected file into a {@see SourceItem}.
     *
     * Order of the checks: (local) real path still within the selection
     * root and a regular file; size via fileSize() against $maxBytes BEFORE
     * anything is read; open; (local) the opened file is the checked one
     * (device and inode); copy at most $maxBytes + 1 bytes into a temporary
     * stream, so a file that grows after the size check is still refused.
     * The bytes are not interpreted: binary content, NUL bytes and invalid
     * UTF-8 pass through unchanged.
     *
     * @param int $maxBytes size limit in bytes (the application's non-HTTP upload limit)
     */
    public function read(SelectedFile $file, int $maxBytes): ReadResult
    {
        if ($maxBytes < 0) {
            throw new \InvalidArgumentException('The size limit must not be negative.');
        }

        $key = $file->sourceKey;
        $real = null;
        if (null !== $this->localBase) {
            clearstatcache();
            $real = realpath($this->absolute($file->path));
            if (false === $real) {
                return ReadResult::failed($key, ReadResult::VANISHED, 'The file vanished between listing and reading.');
            }
            if (!LocalRootAllowList::isWithin($real, $this->localBase)) {
                return ReadResult::failed($key, ReadResult::OUTSIDE_ROOT, 'The file leads outside the selection root (symlink) and is not read.');
            }
            if (!is_file($real)) {
                return ReadResult::failed($key, ReadResult::UNREADABLE, 'The path is not a regular file.');
            }
        }

        try {
            $size = $this->filesystem->operator->fileSize($file->path);
        } catch (FilesystemException $e) {
            return $this->failedAccess($file, $e);
        }
        if ($size > $maxBytes) {
            return ReadResult::failed($key, ReadResult::TOO_LARGE, \sprintf('The file (%d bytes) exceeds the size limit of %d bytes.', $size, $maxBytes));
        }

        try {
            $source = $this->filesystem->operator->readStream($file->path);
        } catch (FilesystemException $e) {
            return $this->failedAccess($file, $e);
        }

        try {
            if (null !== $real && !$this->isSameFile($source, $real)) {
                return ReadResult::failed($key, ReadResult::OUTSIDE_ROOT, 'The file was replaced while it was opened and is not read.');
            }

            $buffer = fopen('php://temp', 'w+b');
            if (false === $buffer) {
                return ReadResult::failed($key, ReadResult::UNREADABLE, 'No temporary stream available.');
            }
            $copied = @stream_copy_to_stream($source, $buffer, $maxBytes + 1);
            if (false === $copied) {
                fclose($buffer);

                return ReadResult::failed($key, ReadResult::UNREADABLE, 'The file could not be read.');
            }
            if ($copied > $maxBytes) {
                fclose($buffer);

                return ReadResult::failed($key, ReadResult::TOO_LARGE, \sprintf('The file exceeds the size limit of %d bytes.', $maxBytes));
            }
        } finally {
            if (\is_resource($source)) {
                fclose($source);
            }
        }

        rewind($buffer);

        return ReadResult::ok(new SourceItem($key, $buffer, basename($file->path), [], null, $file->lastModified));
    }

    /**
     * Why a matching local file is not yielded, or null when it is safe. A
     * file that vanished since the listing is yielded; read() reports it.
     */
    private function localRejection(string $path): ?string
    {
        if (null === $this->localBase) {
            return null;
        }
        $real = realpath($this->absolute($path));
        if (false === $real) {
            return null;
        }
        if (!LocalRootAllowList::isWithin($real, $this->localBase)) {
            return RejectedFile::OUTSIDE_ROOT;
        }
        if (!is_file($real)) {
            return RejectedFile::NOT_A_REGULAR_FILE;
        }

        return null;
    }

    /**
     * Whether the opened stream is the file at $real (device and inode).
     *
     * @param resource $stream
     */
    private function isSameFile($stream, string $real): bool
    {
        $opened = @fstat($stream);
        clearstatcache();
        $checked = @stat($real);
        $current = realpath($real);

        return false !== $opened && false !== $checked && $current === $real
            && $opened['dev'] === $checked['dev'] && $opened['ino'] === $checked['ino'];
    }

    private function failedAccess(SelectedFile $file, FilesystemException $e): ReadResult
    {
        clearstatcache();
        try {
            $exists = null !== $this->localBase
                ? file_exists($this->absolute($file->path))
                : $this->filesystem->operator->fileExists($file->path);
        } catch (FilesystemException) {
            $exists = true;
        }

        return $exists
            ? ReadResult::failed($file->sourceKey, ReadResult::UNREADABLE, 'The file could not be read: '.$e->getMessage())
            : ReadResult::failed($file->sourceKey, ReadResult::VANISHED, 'The file vanished between listing and reading.');
    }

    private function absolute(string $path): string
    {
        return rtrim((string) $this->filesystem->localRoot, '/').'/'.$path;
    }
}
