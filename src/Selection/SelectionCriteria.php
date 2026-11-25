<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Selection;

use Dmstr\DocumentSource\Exception\InvalidSelectionException;
use Symfony\Component\Finder\Glob;

/**
 * Which files of a connection a sync reads: a root below the connection
 * root plus include and exclude globs.
 *
 * - `root`: relative to the connection root, forward slashes, `''` (the
 *   default) is the connection root itself; a leading or trailing slash is
 *   not allowed, nor are `.`/`..`/empty segments, backslashes or NUL bytes.
 * - Globs are matched against the path relative to `root` (the source key),
 *   with the syntax of symfony/finder's Glob: `*` and `?` stay within one
 *   path segment, `**` spans segments (`**\/*.md` matches Markdown files at
 *   any depth, `docs/**` everything below `docs`), `{a,b}` alternatives,
 *   `[abc]` character classes; dot files are matched like any other file.
 *   A pattern without a slash therefore only matches top-level files
 *   (`*.pdf`); use `**\/*.pdf` for every depth. Globs may not contain `..`,
 *   a leading slash, a backslash or a NUL byte.
 * - A file is selected when it matches at least one include glob (no include
 *   globs = every file) and no exclude glob.
 */
final readonly class SelectionCriteria
{
    /** @var list<string> */
    private array $includeRegexes;
    /** @var list<string> */
    private array $excludeRegexes;

    /**
     * @param list<string> $include
     * @param list<string> $exclude
     *
     * @throws InvalidSelectionException for a malformed root or glob
     */
    public function __construct(
        public string $root = '',
        public array $include = [],
        public array $exclude = [],
    ) {
        self::assertRoot($root);
        $this->includeRegexes = self::compile($include, 'include');
        $this->excludeRegexes = self::compile($exclude, 'exclude');
    }

    /**
     * Whether a path relative to the root is selected.
     */
    public function matches(string $relativePath): bool
    {
        if ([] !== $this->includeRegexes && !self::matchesAny($this->includeRegexes, $relativePath)) {
            return false;
        }

        return !self::matchesAny($this->excludeRegexes, $relativePath);
    }

    /**
     * The operator path of a key (root joined with the key).
     */
    public function pathOf(string $relativePath): string
    {
        return '' === $this->root ? $relativePath : $this->root.'/'.$relativePath;
    }

    private static function assertRoot(string $root): void
    {
        if ('' === $root) {
            return;
        }
        if (str_contains($root, "\0") || str_contains($root, '\\')) {
            throw new InvalidSelectionException('The selection root may not contain backslashes or NUL bytes.');
        }
        if (str_starts_with($root, '/') || str_ends_with($root, '/')) {
            throw new InvalidSelectionException(\sprintf('The selection root "%s" must be relative to the connection root, without a leading or trailing slash.', $root));
        }
        foreach (explode('/', $root) as $segment) {
            if ('' === $segment || '.' === $segment || '..' === $segment) {
                throw new InvalidSelectionException(\sprintf('The selection root "%s" may not contain empty, "." or ".." segments.', $root));
            }
        }
    }

    /**
     * @param array<mixed> $globs
     *
     * @return list<string>
     */
    private static function compile(array $globs, string $kind): array
    {
        $regexes = [];
        foreach ($globs as $glob) {
            if (!\is_string($glob) || '' === trim($glob)) {
                throw new InvalidSelectionException(\sprintf('Every %s glob must be a non-empty string.', $kind));
            }
            if (str_contains($glob, '..')) {
                throw new InvalidSelectionException(\sprintf('The %s glob "%s" may not contain "..".', $kind, $glob));
            }
            if (str_starts_with($glob, '/') || str_contains($glob, '\\') || str_contains($glob, "\0")) {
                throw new InvalidSelectionException(\sprintf('The %s glob "%s" must be relative to the selection root, without backslashes or NUL bytes.', $kind, str_replace("\0", '\0', $glob)));
            }
            // Byte-wise (no `u` modifier): a file name with invalid UTF-8 can
            // still be selected and then fails visibly when it is read.
            $regexes[] = Glob::toRegex($glob, false, true);
        }

        return $regexes;
    }

    /**
     * @param list<string> $regexes
     */
    private static function matchesAny(array $regexes, string $path): bool
    {
        foreach ($regexes as $regex) {
            if (1 === preg_match($regex, $path)) {
                return true;
            }
        }

        return false;
    }
}
