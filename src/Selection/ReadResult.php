<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Selection;

use Dmstr\DocumentSource\Import\SourceItem;

/**
 * Result of {@see Selection::read()}: the item, or a readable failure
 * (vanished between listing and reading, over the size limit, unreadable,
 * outside the root). A failure belongs into the report as a failed item;
 * the run continues, the key still counts as seen, and the run is no
 * longer clean.
 */
final readonly class ReadResult
{
    public const string VANISHED = 'vanished';
    public const string TOO_LARGE = 'too_large';
    public const string UNREADABLE = 'unreadable';
    public const string OUTSIDE_ROOT = 'outside_root';

    private function __construct(
        public string $sourceKey,
        public ?SourceItem $item,
        /** One of the constants of this class for a failure, null on success. */
        public ?string $failureCode,
        /** Readable reason for the report, null on success. */
        public ?string $failure,
    ) {
    }

    public static function ok(SourceItem $item): self
    {
        return new self($item->sourceKey, $item, null, null);
    }

    public static function failed(string $sourceKey, string $code, string $reason): self
    {
        return new self($sourceKey, null, $code, $reason);
    }

    /**
     * @phpstan-assert-if-true !null $this->item
     */
    public function isOk(): bool
    {
        return null !== $this->item;
    }
}
