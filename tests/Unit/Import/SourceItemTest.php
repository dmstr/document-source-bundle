<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Unit\Import;

use Dmstr\DocumentSource\Import\ItemOutcome;
use Dmstr\DocumentSource\Import\SourceItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SourceItemTest extends TestCase
{
    public function testFromStringKeepsBytesAndDefaults(): void
    {
        $bytes = "a\x00b\xff";
        $item = SourceItem::fromString('docs/a.md', $bytes);

        self::assertSame('docs/a.md', $item->sourceKey);
        self::assertSame('a.md', $item->filename);
        self::assertSame([], $item->metadata);
        self::assertNull($item->citationUrl);
        self::assertSame($bytes, $item->contents());
        self::assertSame($bytes, $item->contents());
        self::assertSame(4, $item->size());
    }

    public function testWithersReturnCopiesSharingTheContent(): void
    {
        $item = SourceItem::fromString('a.md', 'x');
        $copy = $item->withMetadata(['topic' => 'T'])->withCitationUrl('https://wiki.example.org/a');

        self::assertSame([], $item->metadata);
        self::assertSame(['topic' => 'T'], $copy->metadata);
        self::assertSame('https://wiki.example.org/a', $copy->citationUrl);
        self::assertSame('x', $copy->contents());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'leading slash' => ['/a.md'];
        yield 'trailing slash' => ['a/'];
        yield 'traversal' => ['../a.md'];
        yield 'dot segment' => ['./a.md'];
        yield 'empty segment' => ['a//b.md'];
        yield 'backslash' => ['a\\b.md'];
        yield 'NUL' => ["a\0.md"];
    }

    #[DataProvider('malformedKeys')]
    public function testMalformedSourceKeysAreRejected(string $key): void
    {
        self::assertFalse(SourceItem::isValidSourceKey($key));
        $this->expectException(\InvalidArgumentException::class);
        SourceItem::fromString($key, 'x');
    }

    public function testNonStreamIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceItem('a.md', 'not a stream', 'a.md');
    }

    public function testOutcomes(): void
    {
        self::assertSame(['created', 'replaced', 'skipped', 'unpublished', 'failed'], ItemOutcome::STATUSES);
        self::assertSame('created', ItemOutcome::created('id-1')->status);
        self::assertSame('id-1', ItemOutcome::replaced('id-1')->documentId);
        self::assertSame('skipped', ItemOutcome::skipped('id-1')->status);
        self::assertSame('unpublished', ItemOutcome::unpublished()->status);
        $failed = ItemOutcome::failed('Same content as document id-2.', 'id-2');
        self::assertTrue($failed->isFailed());
        self::assertSame('Same content as document id-2.', $failed->reason);
        self::assertFalse(ItemOutcome::created('id-1')->isFailed());
    }
}
