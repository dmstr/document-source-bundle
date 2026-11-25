<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Unit\Filesystem;

use Dmstr\DocumentSource\Tests\Support\TempTree;
use Dmstr\DocumentSource\Exception\PathNotAllowedException;
use Dmstr\DocumentSource\Filesystem\LocalRootAllowList;
use PHPUnit\Framework\TestCase;

final class LocalRootAllowListTest extends TestCase
{
    private TempTree $tree;

    protected function setUp(): void
    {
        $this->tree = new TempTree('docsrc-allow');
    }

    protected function tearDown(): void
    {
        $this->tree->remove();
    }

    public function testEntryAndPathsBelowItAreAllowed(): void
    {
        $allowed = $this->tree->dir('uploads');
        $below = $this->tree->dir('uploads/acme/handbook');
        $list = new LocalRootAllowList([$allowed]);

        self::assertSame($allowed, $list->resolve($allowed));
        self::assertSame($below, $list->resolve($below));
        self::assertSame($below, $list->resolve($allowed.'/acme/./handbook/'));
    }

    public function testSiblingWithTheSamePrefixIsNotAllowed(): void
    {
        $allowed = $this->tree->dir('uploads');
        $sibling = $this->tree->dir('uploads2');

        $this->expectException(PathNotAllowedException::class);
        new LocalRootAllowList([$allowed])->resolve($sibling);
    }

    public function testTraversalOutOfAnEntryIsNotAllowed(): void
    {
        $allowed = $this->tree->dir('uploads');
        $this->tree->dir('etc');

        $this->expectException(PathNotAllowedException::class);
        new LocalRootAllowList([$allowed])->resolve($allowed.'/../etc');
    }

    public function testSymlinkOutOfAnEntryIsNotAllowed(): void
    {
        $allowed = $this->tree->dir('uploads');
        $outside = $this->tree->dir('secret');
        $link = $this->tree->link('uploads/link', $outside);

        $this->expectException(PathNotAllowedException::class);
        new LocalRootAllowList([$allowed])->resolve($link);
    }

    public function testSymlinkedEntryIsComparedByItsRealPath(): void
    {
        $real = $this->tree->dir('data/uploads');
        $alias = $this->tree->link('alias', $real);
        $this->tree->dir('data/uploads/acme');

        $list = new LocalRootAllowList([$alias]);

        self::assertSame($real.'/acme', $list->resolve($real.'/acme'));
        self::assertSame($real.'/acme', $list->resolve($alias.'/acme'));
    }

    public function testCommaSeparatedStringAndInvalidEntries(): void
    {
        $a = $this->tree->dir('a');
        $b = $this->tree->dir('b');

        $list = new LocalRootAllowList(' '.$a.' , relative/dir,,'.$b.',');

        self::assertSame([$a, $b], $list->entries());
        self::assertSame($b, $list->resolve($b));
    }

    public function testEmptyListAllowsNothing(): void
    {
        $this->expectException(PathNotAllowedException::class);
        new LocalRootAllowList(null)->resolve($this->tree->path);
    }

    public function testMissingAndRelativePathsAreRefused(): void
    {
        $list = new LocalRootAllowList([$this->tree->path]);

        foreach ([$this->tree->path.'/missing', 'relative', '', $this->tree->path."\0x"] as $path) {
            try {
                $list->resolve($path);
                self::fail('Accepted '.$path);
            } catch (PathNotAllowedException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testIsWithin(): void
    {
        self::assertTrue(LocalRootAllowList::isWithin('/mnt/uploads', '/mnt/uploads'));
        self::assertTrue(LocalRootAllowList::isWithin('/mnt/uploads/a', '/mnt/uploads'));
        self::assertTrue(LocalRootAllowList::isWithin('/mnt/uploads/a', '/mnt/uploads/'));
        self::assertFalse(LocalRootAllowList::isWithin('/mnt/uploads2', '/mnt/uploads'));
        self::assertFalse(LocalRootAllowList::isWithin('/mnt', '/mnt/uploads'));
        self::assertTrue(LocalRootAllowList::isWithin('/etc', '/'));
    }
}
