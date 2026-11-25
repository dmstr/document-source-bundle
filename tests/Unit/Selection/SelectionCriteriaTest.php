<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Unit\Selection;

use Dmstr\DocumentSource\Exception\InvalidSelectionException;
use Dmstr\DocumentSource\Selection\SelectionCriteria;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SelectionCriteriaTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, list<string>, string, bool}>
     */
    public static function matchCases(): iterable
    {
        yield 'no globs selects everything' => [[], [], 'a/b/c.bin', true];
        yield 'star stays in the segment' => [['*.md'], [], 'a.md', true];
        yield 'star does not cross a slash' => [['*.md'], [], 'docs/a.md', false];
        yield 'double star at the start, top level' => [['**/*.md'], [], 'a.md', true];
        yield 'double star at the start, deep' => [['**/*.md'], [], 'x/y/a.md', true];
        yield 'double star in the middle' => [['docs/**/*.pdf'], [], 'docs/2024/q1/r.pdf', true];
        yield 'double star in the middle, direct child' => [['docs/**/*.pdf'], [], 'docs/r.pdf', true];
        yield 'trailing double star' => [['docs/**'], [], 'docs/a/b.txt', true];
        yield 'other directory' => [['docs/**'], [], 'other/a.txt', false];
        yield 'alternatives' => [['**/*.{md,txt}'], [], 'a/b.txt', true];
        yield 'question mark' => [['?.md'], [], 'a.md', true];
        yield 'dot files are ordinary' => [['**/*.md'], [], '.hidden/x.md', true];
        yield 'exclude wins' => [['**/*.md'], ['drafts/**'], 'drafts/a.md', false];
        yield 'exclude only' => [[], ['**/*.tmp'], 'a/b.tmp', false];
        yield 'exclude only, other file' => [[], ['**/*.tmp'], 'a/b.pdf', true];
        yield 'regex characters are literal' => [['a+(b).md'], [], 'a+(b).md', true];
        yield 'invalid UTF-8 in a name is matched byte-wise' => [['**/*.md'], [], "x/\xff.md", true];
    }

    /**
     * @param list<string> $include
     * @param list<string> $exclude
     */
    #[DataProvider('matchCases')]
    public function testMatches(array $include, array $exclude, string $path, bool $expected): void
    {
        self::assertSame($expected, new SelectionCriteria('', $include, $exclude)->matches($path));
    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function invalid(): iterable
    {
        yield 'root traversal' => ['..', [], []];
        yield 'root traversal inside' => ['docs/../../etc', [], []];
        yield 'root dot segment' => ['docs/./x', [], []];
        yield 'absolute root' => ['/etc', [], []];
        yield 'root with trailing slash' => ['docs/', [], []];
        yield 'root with empty segment' => ['docs//x', [], []];
        yield 'root with backslash' => ['docs\\..', [], []];
        yield 'root with NUL' => ["docs\0", [], []];
        yield 'include traversal' => ['', ['../**'], []];
        yield 'include traversal inside' => ['', ['docs/../../*'], []];
        yield 'exclude traversal' => ['', [], ['**/../x']];
        yield 'any double dot' => ['', ['a..b'], []];
        yield 'absolute glob' => ['', ['/etc/*'], []];
        yield 'backslash glob' => ['', ['docs\\*'], []];
        yield 'empty glob' => ['', [' '], []];
    }

    /**
     * @param list<string> $include
     * @param list<string> $exclude
     */
    #[DataProvider('invalid')]
    public function testMalformedCriteriaAreRejected(string $root, array $include, array $exclude): void
    {
        $this->expectException(InvalidSelectionException::class);
        new SelectionCriteria($root, $include, $exclude);
    }

    public function testNonStringGlobIsRejected(): void
    {
        $this->expectException(InvalidSelectionException::class);
        /* @phpstan-ignore-next-line argument.type */
        new SelectionCriteria('', [42]);
    }

    public function testPathOf(): void
    {
        self::assertSame('a.md', new SelectionCriteria()->pathOf('a.md'));
        self::assertSame('docs/x/a.md', new SelectionCriteria('docs/x')->pathOf('a.md'));
    }
}
