<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Unit\Connection;

use Dmstr\DocumentSource\Tests\Support\TempTree;
use Dmstr\ApiConfiguration\ApiClient\AuthenticationFailedException;
use Dmstr\DocumentSource\Command\FilesystemConnectionSeedCommand;
use Dmstr\DocumentSource\Connection\FilesystemConnectionClient;
use Dmstr\DocumentSource\Connection\FilesystemConnectionType;
use Dmstr\DocumentSource\Filesystem\LocalRootAllowList;
use PHPUnit\Framework\TestCase;

/**
 * Kernel-free health probe of `local` filesystem connections against a
 * temporary tree: ok, missing, outside the allow-list, unreadable, not a
 * directory, unusable configuration. The probe never throws.
 */
final class FilesystemHealthProbeTest extends TestCase
{
    private TempTree $allowed;
    private TempTree $outside;
    private FilesystemConnectionType $type;

    protected function setUp(): void
    {
        $this->allowed = new TempTree('docsrc-allowed');
        $this->outside = new TempTree('docsrc-outside');
        $this->type = new FilesystemConnectionType(new LocalRootAllowList([$this->allowed->path]));
    }

    protected function tearDown(): void
    {
        $this->allowed->remove();
        $this->outside->remove();
    }

    public function testReadableRootWithinTheAllowListIsOk(): void
    {
        $root = $this->allowed->dir('acme');
        $this->allowed->file('acme/deep/a.md');

        $health = $this->type->probe(FilesystemConnectionSeedCommand::buildConfig($root));

        self::assertSame('ok', $health['status']);
        self::assertTrue($health['authenticated']);
        self::assertSame('local', $health['metadata']['custom']['adapter']);
        self::assertSame($root, $health['metadata']['custom']['realRoot']);
        self::assertArrayNotHasKey('fileCount', $health['metadata']['custom'], 'the probe does not list');
    }

    public function testMissingRootIsNotFound(): void
    {
        $health = $this->type->probe(FilesystemConnectionSeedCommand::buildConfig($this->allowed->path.'/missing'));

        self::assertSame('error', $health['status']);
        self::assertSame(FilesystemConnectionType::CODE_NOT_FOUND, $health['error']['code']);
    }

    public function testRootOutsideTheAllowListIsNotAllowed(): void
    {
        $health = $this->type->probe(FilesystemConnectionSeedCommand::buildConfig($this->outside->path));

        self::assertSame(FilesystemConnectionType::CODE_NOT_ALLOWED, $health['error']['code']);
    }

    public function testSymlinkedRootLeadingOutsideIsNotAllowed(): void
    {
        $link = $this->allowed->link('escape', $this->outside->path);

        $health = $this->type->probe(FilesystemConnectionSeedCommand::buildConfig($link));

        self::assertSame(FilesystemConnectionType::CODE_NOT_ALLOWED, $health['error']['code']);
    }

    public function testTraversalOutOfTheAllowListIsNotAllowed(): void
    {
        $health = $this->type->probe(FilesystemConnectionSeedCommand::buildConfig($this->allowed->path.'/../'.basename($this->outside->path)));

        self::assertSame(FilesystemConnectionType::CODE_NOT_ALLOWED, $health['error']['code']);
    }

    public function testFileAsRootIsNotFound(): void
    {
        $file = $this->allowed->file('a.md');

        $health = $this->type->probe(FilesystemConnectionSeedCommand::buildConfig($file));

        self::assertSame(FilesystemConnectionType::CODE_NOT_FOUND, $health['error']['code']);
    }

    public function testUnreadableRootIsNotReadable(): void
    {
        $root = $this->allowed->dir('locked');
        chmod($root, 0o000);
        if (is_readable($root)) {
            chmod($root, 0o777);
            self::markTestSkipped('Running as root: permissions are not enforced.');
        }

        $health = $this->type->probe(FilesystemConnectionSeedCommand::buildConfig($root));
        chmod($root, 0o777);

        self::assertSame(FilesystemConnectionType::CODE_NOT_READABLE, $health['error']['code']);
    }

    public function testEmptyAllowListAllowsNothing(): void
    {
        $type = new FilesystemConnectionType(new LocalRootAllowList([]));

        $health = $type->probe(FilesystemConnectionSeedCommand::buildConfig($this->allowed->path));

        self::assertSame(FilesystemConnectionType::CODE_NOT_ALLOWED, $health['error']['code']);
    }

    public function testUnusableConfigurationIsAConfigurationError(): void
    {
        self::assertSame(FilesystemConnectionType::CODE_CONFIGURATION, $this->type->probe(['type' => 'filesystem'])['error']['code']);
        self::assertSame(FilesystemConnectionType::CODE_CONFIGURATION, $this->type->probe(['adapter' => 'sftp', 'root' => '/x'])['error']['code']);
        self::assertSame(FilesystemConnectionType::CODE_CONFIGURATION, $this->type->probe(['root' => 'relative'])['error']['code']);
    }

    public function testEndpointIsAFileUri(): void
    {
        self::assertSame('file:///mnt/uploads/a%20b', $this->type->getEndpoint(['root' => '/mnt/uploads/a b']));
        self::assertSame('urn:dmstr:filesystem', $this->type->getEndpoint([]));
    }

    public function testClientDelegatesToTheProbe(): void
    {
        $client = $this->type->createClient(FilesystemConnectionSeedCommand::buildConfig($this->allowed->path) + ['_apiConfigurationId' => 'x']);

        self::assertInstanceOf(FilesystemConnectionClient::class, $client);
        self::assertSame('file', $client->getType());
        self::assertSame('filesystem', $client->getApiName());
        self::assertSame('ok', $client->getHealthInfo()['status']);
        $client->authenticate();

        $this->expectException(AuthenticationFailedException::class);
        $this->type->createClient(FilesystemConnectionSeedCommand::buildConfig($this->outside->path))->authenticate();
    }
}
