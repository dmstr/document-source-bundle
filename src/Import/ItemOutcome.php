<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Import;

/**
 * Result of importing one item ({@see ImportSinkInterface::import()}):
 * created, replaced, skipped (unchanged and embedded), unpublished (the
 * source marks the file as not published; it is not imported)
 * or failed with a reason. The status strings are the counter keys of the
 * sync report.
 */
final readonly class ItemOutcome
{
    public const string CREATED = 'created';
    public const string REPLACED = 'replaced';
    public const string SKIPPED = 'skipped';
    public const string UNPUBLISHED = 'unpublished';
    public const string FAILED = 'failed';

    public const array STATUSES = [self::CREATED, self::REPLACED, self::SKIPPED, self::UNPUBLISHED, self::FAILED];

    private function __construct(
        /** One of {@see STATUSES}. */
        public string $status,
        /** UUID of the created, replaced, skipped or conflicting document when known. */
        public ?string $documentId,
        /** Readable reason of a failed (or unpublished) item; null otherwise. */
        public ?string $reason,
    ) {
    }

    public static function created(string $documentId): self
    {
        return new self(self::CREATED, $documentId, null);
    }

    public static function replaced(string $documentId): self
    {
        return new self(self::REPLACED, $documentId, null);
    }

    public static function skipped(string $documentId): self
    {
        return new self(self::SKIPPED, $documentId, null);
    }

    public static function unpublished(?string $reason = null): self
    {
        return new self(self::UNPUBLISHED, null, $reason);
    }

    public static function failed(string $reason, ?string $documentId = null): self
    {
        return new self(self::FAILED, $documentId, $reason);
    }

    public function isFailed(): bool
    {
        return self::FAILED === $this->status;
    }
}
