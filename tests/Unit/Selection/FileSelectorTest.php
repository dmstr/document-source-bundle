<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Unit\Selection;

use Dmstr\DocumentSource\Tests\Support\TempTree;
use Dmstr\DocumentSource\Command\FilesystemConnectionSeedCommand;
use Dmstr\DocumentSource\Exception\SourceUnavailableException;
use Dmstr\DocumentSource\Filesystem\LocalRootAllowList;
use Dmstr\DocumentSource\Filesystem\SourceFilesystem;
use Dmstr\DocumentSource\Filesystem\SourceFilesystemFactory;
use Dmstr\DocumentSource\Selection\FileSelector;
use Dmstr\DocumentSource\Selection\ReadResult;
use Dmstr\DocumentSource\Selection\RejectedFile;
use Dmstr\DocumentSource\Selection\SelectedFile;
use Dmstr\DocumentSource\Selection\Selection;
use Dmstr\DocumentSource\Selection\SelectionCriteria;
use PHPUnit\Framework\TestCase;

/**
 * Kernel-free selection and reading on a real `local` tree under
 * sys_get_temp_dir(): globs, traversal, symlink escapes of files and
 * directories, size limit, files that vanish or change between listing and
 * reading, non-regular files, and bytes that pass through unchanged.
 */
final class FileSelectorTest extends TestCase
{
    private const int LIMIT = 1024;

    private TempTree $tree;
    private string $root;
    private string $outside;

    protected function setUp(): void
    {
        $this->tree = new TempTree('docsrc-select');
        $this->root = $this->tree->dir('uploads/source');
        $this->outside = $this->tree->dir('outside');
        $this->tree->file('outside/secret.md', 'SECRET');
        $this->tree->file('outside/deep/secret.pdf', 'SECRET');
    }

    protected function tearDown(): void
    {
        $this->tree->remove();
    }

    public function testSelectsMatchingFilesWithRelativeKeys(): void
    {
        $this->tree->file('uploads/source/a.md', 'A');
        $this->tree->file('uploads/source/docs/b.md', 'B');
        $this->tree->file('uploads/source/docs/c.pdf', 'C');
        $this->tree->file('uploads/source/docs/drafts/d.md', 'D');

        $selection = $this->select(new SelectionCriteria('', ['**/*.md'], ['docs/drafts/**']));

        self::assertSame(['a.md', 'docs/b.md'], $this->keys($selection));
        self::assertTrue($selection->isComplete());
    }

    public function testKeysAreRelativeToTheSelectionRoot(): void
    {
        $this->tree->file('uploads/source/a.md');
        $this->tree->file('uploads/source/docs/x/b.md');

        $selection = $this->select(new SelectionCriteria('docs'));
        $files = iterator_to_array($selection, false);

        self::assertSame(['x/b.md'], array_map(static fn (SelectedFile $f): string => $f->sourceKey, $files));
        self::assertSame('docs/x/b.md', $files[0]->path);
    }

    public function testSymlinkedFileLeadingOutsideIsNeverRead(): void
    {
        $this->tree->file('uploads/source/a.md', 'A');
        $this->tree->link('uploads/source/escape.md', $this->outside.'/secret.md');

        $selection = $this->select(new SelectionCriteria());

        self::assertSame(['a.md'], $this->keys($selection));
        // A forged SelectedFile does not get past read() either.
        $read = $selection->read(new SelectedFile('escape.md', 'escape.md'), self::LIMIT);
        self::assertFalse($read->isOk());
        self::assertSame(ReadResult::OUTSIDE_ROOT, $read->failureCode);
    }

    public function testSymlinkedDirectoryLeadingOutsideIsNeverListed(): void
    {
        $this->tree->file('uploads/source/a.md', 'A');
        $this->tree->link('uploads/source/linked', $this->outside);

        $selection = $this->select(new SelectionCriteria());

        self::assertSame(['a.md'], $this->keys($selection));
        $read = $selection->read(new SelectedFile('linked/secret.md', 'linked/secret.md'), self::LIMIT);
        self::assertSame(ReadResult::OUTSIDE_ROOT, $read->failureCode);
    }

    public function testSymlinkedDirectoryAsSelectionRootIsRefused(): void
    {
        $this->tree->link('uploads/source/linked', $this->outside);

        $this->expectException(SourceUnavailableException::class);
        $this->select(new SelectionCriteria('linked'));
    }

    public function testSymlinkInsideTheRootIsSkippedToo(): void
    {
        $this->tree->file('uploads/source/a.md', 'A');
        $this->tree->link('uploads/source/alias.md', $this->root.'/a.md');

        self::assertSame(['a.md'], $this->keys($this->select(new SelectionCriteria())));
    }

    public function testMissingSelectionRootIsNotAnEmptySource(): void
    {
        $this->expectException(SourceUnavailableException::class);
        $this->select(new SelectionCriteria('missing'));
    }

    public function testFileAsSelectionRootIsRefused(): void
    {
        $this->tree->file('uploads/source/a.md');

        $this->expectException(SourceUnavailableException::class);
        $this->select(new SelectionCriteria('a.md'));
    }

    public function testFileSwappedForASymlinkAfterListingIsNotRead(): void
    {
        $this->tree->file('uploads/source/a.md', 'A');
        $selection = $this->select(new SelectionCriteria());
        $files = iterator_to_array($selection, false);

        unlink($this->root.'/a.md');
        symlink($this->outside.'/secret.md', $this->root.'/a.md');

        $read = $selection->read($files[0], self::LIMIT);
        self::assertSame(ReadResult::OUTSIDE_ROOT, $read->failureCode);
    }

    public function testFileVanishingBetweenListingAndReadingIsAFailedItem(): void
    {
        $this->tree->file('uploads/source/a.md', 'A');
        $this->tree->file('uploads/source/b.md', 'B');
        $selection = $this->select(new SelectionCriteria());

        // Listing order is not guaranteed, so the file listed first removes
        // the other one; a lazy listing may skip it, otherwise it vanished.
        $results = [];
        foreach ($selection as $file) {
            if ([] === $results) {
                $other = 'a.md' === $file->sourceKey ? 'b.md' : 'a.md';
                unlink($this->root.'/'.$other);
            }
            $results[] = $selection->read($file, self::LIMIT);
        }

        self::assertTrue($selection->isComplete());
        self::assertTrue($results[0]->isOk());
        if (isset($results[1])) {
            self::assertFalse($results[1]->isOk());
            self::assertSame(ReadResult::VANISHED, $results[1]->failureCode);
        }

        $vanished = $selection->read(new SelectedFile('gone.md', 'gone.md'), self::LIMIT);
        self::assertSame(ReadResult::VANISHED, $vanished->failureCode);
        self::assertSame('gone.md', $vanished->sourceKey);
    }

    public function testOversizedFileIsAFailedItemAndTheRunContinues(): void
    {
        $this->tree->file('uploads/source/big.pdf', str_repeat('x', self::LIMIT + 1));
        $this->tree->file('uploads/source/fits.pdf', str_repeat('x', self::LIMIT));
        $selection = $this->select(new SelectionCriteria());

        $results = [];
        foreach ($selection as $file) {
            $results[$file->sourceKey] = $selection->read($file, self::LIMIT);
        }

        self::assertSame(ReadResult::TOO_LARGE, $results['big.pdf']->failureCode);
        self::assertStringContainsString((string) self::LIMIT, (string) $results['big.pdf']->failure);
        self::assertTrue($results['fits.pdf']->isOk());
        self::assertSame(self::LIMIT, $results['fits.pdf']->item?->size());
    }

    public function testFileGrowingAfterTheSizeCheckIsStillRefused(): void
    {
        $this->tree->file('uploads/source/a.txt', 'small');
        $selection = $this->select(new SelectionCriteria());
        $file = iterator_to_array($selection, false)[0];

        // The listing saw 5 bytes; the limit applies to what is read.
        file_put_contents($this->root.'/a.txt', str_repeat('y', 64));

        self::assertSame(ReadResult::TOO_LARGE, $selection->read($file, 32)->failureCode);
    }

    public function testBytesPassThroughUnchanged(): void
    {
        $binary = "%PDF-1.7\n\x00\x01\x02\xff\xfe binary \x00 with NUL";
        $invalidUtf8 = "# Title\n\xc3\x28 invalid \xa0\xa1 UTF-8\n";
        $this->tree->file('uploads/source/binary.md', $binary);
        $this->tree->file('uploads/source/invalid.md', $invalidUtf8);
        $this->tree->file('uploads/source/empty.md', '');
        $selection = $this->select(new SelectionCriteria('', ['**/*.md']));

        $items = [];
        foreach ($selection as $file) {
            $read = $selection->read($file, self::LIMIT);
            self::assertTrue($read->isOk(), (string) $read->failure);
            $items[$file->sourceKey] = $read->item;
        }

        self::assertSame($binary, $items['binary.md']?->contents());
        self::assertSame($invalidUtf8, $items['invalid.md']?->contents());
        self::assertSame('', $items['empty.md']?->contents());
        self::assertSame(hash('sha256', $binary), hash('sha256', (string) $items['binary.md']?->contents()));
        self::assertSame('binary.md', $items['binary.md']?->filename);
        // Readable twice: the stream is rewound.
        self::assertSame($binary, stream_get_contents($items['binary.md']->stream()));
    }

    public function testNonRegularFileIsRejected(): void
    {
        if (!\function_exists('posix_mkfifo')) {
            self::markTestSkipped('ext-posix is not available.');
        }
        $this->tree->file('uploads/source/a.md');
        posix_mkfifo($this->root.'/pipe.md', 0o600);

        $selection = $this->select(new SelectionCriteria());

        self::assertSame(['a.md'], $this->keys($selection));
        self::assertEquals([new RejectedFile('pipe.md', RejectedFile::NOT_A_REGULAR_FILE)], $selection->rejected());
    }

    public function testNameWithBackslashIsRejected(): void
    {
        $this->tree->file('uploads/source/a\\b.md');
        $this->tree->file('uploads/source/a/b.md', 'other file');

        $selection = $this->select(new SelectionCriteria());

        // The adapter lists `a\b.md` as `a/b.md`: one key, read from the real
        // `a/b.md`, the colliding name is rejected.
        self::assertSame(['a/b.md'], $this->keys($selection));
        self::assertEquals([new RejectedFile('a/b.md', RejectedFile::UNSUPPORTED_NAME)], $selection->rejected());
    }

    public function testNegativeLimitIsAProgrammingError(): void
    {
        $this->tree->file('uploads/source/a.md');
        $selection = $this->select(new SelectionCriteria());

        $this->expectException(\InvalidArgumentException::class);
        $selection->read(new SelectedFile('a.md', 'a.md'), -1);
    }

    private function select(SelectionCriteria $criteria): Selection
    {
        return new FileSelector()->select($this->filesystem(), $criteria);
    }

    private function filesystem(): SourceFilesystem
    {
        $factory = new SourceFilesystemFactory(new LocalRootAllowList([$this->tree->path.'/uploads']));

        return $factory->create(FilesystemConnectionSeedCommand::buildConfig($this->root));
    }

    /**
     * @return list<string> sorted source keys
     */
    private function keys(Selection $selection): array
    {
        $keys = array_map(static fn (SelectedFile $f): string => $f->sourceKey, iterator_to_array($selection, false));
        sort($keys);

        return $keys;
    }
}
