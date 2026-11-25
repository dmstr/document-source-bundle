<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Import;

/**
 * One file of a source, ready for {@see ImportSinkInterface::import()}.
 * Built by {@see \Dmstr\DocumentSource\Selection\Selection::read()}.
 *
 * The content is a seekable byte stream (php://temp, spills to disk above
 * 2 MB) holding the file's bytes exactly as read: no decoding, no
 * normalisation, NUL bytes and invalid UTF-8 included. The content hash of
 * the document is sha256 of these bytes. {@see contents()} and
 * {@see stream()} both rewind, so the item can be read more than once.
 *
 * Immutable apart from the stream position; the withers return copies that
 * share the stream.
 */
final readonly class SourceItem
{
    /** @var resource */
    private mixed $stream;

    /**
     * @param string               $sourceKey   path relative to the selection root, forward slashes, no leading slash, no `.`/`..` segments
     * @param resource             $stream      readable, seekable stream of the file's bytes
     * @param string               $filename    base name of the file, e.g. `report.pdf`
     * @param array<string, mixed> $metadata    document metadata (e.g. `title`, `description`, `tags`)
     * @param string|null          $citationUrl URL answers cite (rendered URL template), or null
     * @param int|null             $lastModified Unix timestamp of the last modification, when the source reports one
     *
     * @throws \InvalidArgumentException for a malformed source key or a non-stream
     */
    public function __construct(
        public string $sourceKey,
        mixed $stream,
        public string $filename,
        public array $metadata = [],
        public ?string $citationUrl = null,
        public ?int $lastModified = null,
    ) {
        self::assertSourceKey($sourceKey);
        if (!\is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            throw new \InvalidArgumentException('SourceItem needs a stream resource.');
        }
        $this->stream = $stream;
    }

    /**
     * An item from bytes in memory (tests, generated content).
     *
     * @param array<string, mixed> $metadata
     */
    public static function fromString(string $sourceKey, string $bytes, ?string $filename = null, array $metadata = [], ?string $citationUrl = null): self
    {
        $stream = fopen('php://temp', 'w+b');
        if (false === $stream) {
            throw new \RuntimeException('Cannot open a php://temp stream.');
        }
        fwrite($stream, $bytes);
        rewind($stream);

        return new self($sourceKey, $stream, $filename ?? basename($sourceKey), $metadata, $citationUrl);
    }

    /**
     * Whether $key is a well-formed source key: non-empty, relative, forward
     * slashes, no empty, `.` or `..` segments, no NUL byte.
     */
    public static function isValidSourceKey(string $key): bool
    {
        if ('' === $key || str_contains($key, "\0") || str_contains($key, '\\')) {
            return false;
        }
        foreach (explode('/', $key) as $segment) {
            if ('' === $segment || '.' === $segment || '..' === $segment) {
                return false;
            }
        }

        return true;
    }

    /**
     * The stream, rewound to the start. The caller must not close it while
     * the item (or a copy from a wither) is still in use.
     *
     * @return resource
     */
    public function stream(): mixed
    {
        rewind($this->stream);

        return $this->stream;
    }

    /** All bytes of the file, exactly as read. */
    public function contents(): string
    {
        rewind($this->stream);
        $bytes = stream_get_contents($this->stream);
        if (false === $bytes) {
            throw new \RuntimeException(\sprintf('Cannot read the content of "%s".', $this->sourceKey));
        }

        return $bytes;
    }

    /** Size of the content in bytes. */
    public function size(): int
    {
        $stat = fstat($this->stream);

        return false === $stat ? \strlen($this->contents()) : (int) $stat['size'];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self($this->sourceKey, $this->stream, $this->filename, $metadata, $this->citationUrl, $this->lastModified);
    }

    public function withCitationUrl(?string $citationUrl): self
    {
        return new self($this->sourceKey, $this->stream, $this->filename, $this->metadata, $citationUrl, $this->lastModified);
    }

    private static function assertSourceKey(string $key): void
    {
        if (!self::isValidSourceKey($key)) {
            throw new \InvalidArgumentException(\sprintf('Malformed source key "%s": expected a relative path with forward slashes and no empty, "." or ".." segments.', str_replace("\0", '\0', $key)));
        }
    }
}
