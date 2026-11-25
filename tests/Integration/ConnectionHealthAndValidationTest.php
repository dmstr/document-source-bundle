<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Integration;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Health\ApiConfigurationHealthChecker;
use Dmstr\DocumentSource\Command\FilesystemConnectionSeedCommand;
use Dmstr\DocumentSource\Connection\FilesystemConnectionType;
use Dmstr\DocumentSource\Tests\Support\TempTree;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The `filesystem` type through the services of dmstr/api-configuration-bundle,
 * on entities that are never persisted (no database): the unified schema
 * validates the connection, and the health checker uses the type's probe
 * and endpoint.
 */
final class ConnectionHealthAndValidationTest extends KernelTestCase
{
    private TempTree $tree;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->tree = new TempTree('docsrc-kernel');
    }

    protected function tearDown(): void
    {
        $this->tree->remove();
        parent::tearDown();
    }

    public function testValidConnectionPassesTheEntityValidation(): void
    {
        $violations = $this->validator()->validate($this->connection(FilesystemConnectionSeedCommand::buildConfig('/mnt/documents')));

        self::assertCount(0, $violations, (string) $violations);
    }

    public function testRelativeRootFailsTheEntityValidation(): void
    {
        $violations = $this->validator()->validate($this->connection(FilesystemConnectionSeedCommand::buildConfig('relative/root')));

        self::assertGreaterThan(0, \count($violations));
    }

    public function testHealthCheckUsesTheProbeAndTheFileUri(): void
    {
        $root = $this->tree->dir('handbook');

        $health = $this->checker()->check($this->connection(FilesystemConnectionSeedCommand::buildConfig($root)));

        self::assertSame('ok', $health['status']);
        self::assertSame('file://'.$root, $health['endpoint']);
        self::assertSame('local', $health['metadata']['custom']['adapter'] ?? null);
    }

    public function testHealthCheckOfAMissingRootIsNotFound(): void
    {
        $health = $this->checker()->check($this->connection(FilesystemConnectionSeedCommand::buildConfig($this->tree->path.'/missing')));

        self::assertSame('error', $health['status']);
        self::assertSame(FilesystemConnectionType::CODE_NOT_FOUND, $health['error']['code'] ?? null);
    }

    public function testHealthCheckWithoutARootReportsTheUrnEndpoint(): void
    {
        $config = FilesystemConnectionSeedCommand::buildConfig('/mnt/documents');
        unset($config['root']);

        $health = $this->checker()->check($this->connection($config));

        self::assertSame('error', $health['status']);
        self::assertSame('urn:dmstr:filesystem', $health['endpoint']);
        self::assertSame(FilesystemConnectionType::CODE_CONFIGURATION, $health['error']['code'] ?? null);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function connection(array $config): ApiConfiguration
    {
        return (new ApiConfiguration())
            ->setName('documents')
            ->setConfigJson($config)
            ->setActive(true);
    }

    private function validator(): ValidatorInterface
    {
        $validator = self::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        return $validator;
    }

    private function checker(): ApiConfigurationHealthChecker
    {
        $checker = self::getContainer()->get(ApiConfigurationHealthChecker::class);
        self::assertInstanceOf(ApiConfigurationHealthChecker::class, $checker);

        return $checker;
    }
}
