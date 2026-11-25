<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Unit\Filesystem;

use Dmstr\DocumentSource\Tests\Support\TempTree;
use Dmstr\DocumentSource\Command\FilesystemConnectionSeedCommand;
use Dmstr\DocumentSource\Exception\InvalidSourceConfigurationException;
use Dmstr\DocumentSource\Exception\PathNotAllowedException;
use Dmstr\DocumentSource\Filesystem\LocalRootAllowList;
use Dmstr\DocumentSource\Filesystem\SourceFilesystemFactory;
use PHPUnit\Framework\TestCase;

final class SourceFilesystemFactoryTest extends TestCase
{
    private TempTree $tree;
    private SourceFilesystemFactory $factory;

    protected function setUp(): void
    {
        $this->tree = new TempTree('docsrc-factory');
        $this->factory = new SourceFilesystemFactory(new LocalRootAllowList([$this->tree->path.'/uploads']));
        $this->tree->dir('uploads');
    }

    protected function tearDown(): void
    {
        $this->tree->remove();
    }

    public function testLocalConnectionBecomesAnOperatorOnTheRealRoot(): void
    {
        $this->tree->file('uploads/acme/a.md', 'hello');

        $fs = $this->factory->create(FilesystemConnectionSeedCommand::buildConfig($this->tree->path.'/uploads/acme') + ['_apiConfigurationId' => 'ignored']);

        self::assertTrue($fs->isLocal());
        self::assertSame('local', $fs->adapter);
        self::assertSame($this->tree->path.'/uploads/acme', $fs->localRoot);
        self::assertSame('hello', $fs->operator->read('a.md'));
    }

    public function testRootOutsideTheAllowListIsRefused(): void
    {
        $this->tree->dir('other');

        $this->expectException(PathNotAllowedException::class);
        $this->factory->create(FilesystemConnectionSeedCommand::buildConfig($this->tree->path.'/other'));
    }

    public function testMissingRootIsRefusedAndNotCreated(): void
    {
        try {
            $this->factory->create(FilesystemConnectionSeedCommand::buildConfig($this->tree->path.'/uploads/missing'));
            self::fail('A missing root was accepted.');
        } catch (PathNotAllowedException) {
            self::assertDirectoryDoesNotExist($this->tree->path.'/uploads/missing');
        }
    }

    public function testFileAsRootIsRefused(): void
    {
        $file = $this->tree->file('uploads/a.md');

        $this->expectException(InvalidSourceConfigurationException::class);
        $this->factory->create(FilesystemConnectionSeedCommand::buildConfig($file));
    }

    public function testUnknownAdapterAndMissingRootAreConfigurationErrors(): void
    {
        foreach ([['adapter' => 's3', 'root' => '/x'], ['adapter' => 'local'], ['adapter' => ['local']]] as $config) {
            try {
                $this->factory->create($config);
                self::fail('Accepted '.json_encode($config));
            } catch (InvalidSourceConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testOperatorRefusesTraversal(): void
    {
        $this->tree->file('secret.txt', 'secret');
        $fs = $this->factory->create(FilesystemConnectionSeedCommand::buildConfig($this->tree->path.'/uploads'));

        $this->expectException(\League\Flysystem\FilesystemException::class);
        $fs->operator->read('../secret.txt');
    }
}
