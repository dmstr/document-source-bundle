<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Support;

/**
 * A temporary directory tree under sys_get_temp_dir() for kernel-free
 * filesystem tests; removed again without following symlinks.
 */
final class TempTree
{
    public readonly string $path;

    public function __construct(string $prefix = 'docsrc')
    {
        $path = sys_get_temp_dir().'/'.$prefix.'-'.bin2hex(random_bytes(6));
        if (!mkdir($path, 0o777, true)) {
            throw new \RuntimeException('Cannot create '.$path);
        }
        // Real path: sys_get_temp_dir() may itself be a symlink.
        $this->path = (string) realpath($path);
    }

    /** Creates a file (and its directories) and returns its absolute path. */
    public function file(string $relative, string $bytes = 'content'): string
    {
        $absolute = $this->path.'/'.$relative;
        $dir = \dirname($absolute);
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        file_put_contents($absolute, $bytes);

        return $absolute;
    }

    public function dir(string $relative): string
    {
        $absolute = $this->path.'/'.$relative;
        if (!is_dir($absolute)) {
            mkdir($absolute, 0o777, true);
        }

        return $absolute;
    }

    /** Creates a symlink at $relative pointing to $target (absolute). */
    public function link(string $relative, string $target): string
    {
        $absolute = $this->path.'/'.$relative;
        $dir = \dirname($absolute);
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        symlink($target, $absolute);

        return $absolute;
    }

    public function remove(): void
    {
        self::removePath($this->path);
    }

    private static function removePath(string $path): void
    {
        // Links, regular files and FIFOs alike; a link is never followed.
        if (is_link($path) || !is_dir($path)) {
            @unlink($path);

            return;
        }
        @chmod($path, 0o777);
        foreach (scandir($path) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            self::removePath($path.'/'.$entry);
        }
        @rmdir($path);
    }
}
