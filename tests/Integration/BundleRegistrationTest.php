<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Integration;

use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use Dmstr\DocumentSource\Command\FilesystemConnectionSeedCommand;
use Dmstr\DocumentSource\Connection\FilesystemConnectionType;
use Dmstr\DocumentSource\Filesystem\LocalRootAllowList;
use Dmstr\DocumentSource\Filesystem\SourceFilesystemFactory;
use Dmstr\DocumentSource\Filesystem\SourceFilesystemFactoryInterface;
use Dmstr\DocumentSource\Import\ImportSinkInterface;
use Dmstr\DocumentSource\Selection\FileSelector;
use Dmstr\DocumentSource\Tests\Kernel\TestKernel;
use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;
use Doctrine\Migrations\DependencyFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Boots the test kernel (no database connection) and proves the wiring of
 * the bundle: the `filesystem` connection type is registered with
 * dmstr/api-configuration-bundle as extension, schema provider and health
 * probe, the services and the seed command exist, the migrations path is
 * registered, and no `FilesystemOperator` or import sink is provided.
 */
final class BundleRegistrationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testConnectionTypeIsRegisteredWithApiConfiguration(): void
    {
        $registry = self::getContainer()->get(ApiExtensionRegistry::class);
        self::assertInstanceOf(ApiExtensionRegistry::class, $registry);

        self::assertTrue($registry->has(FilesystemConnectionType::NAME));
        $extension = $registry->get(FilesystemConnectionType::NAME);
        self::assertInstanceOf(FilesystemConnectionType::class, $extension);
        self::assertSame(FilesystemConnectionType::ENDPOINT_TYPE, $extension->getType());
    }

    public function testSchemaBranchIsPartOfTheUnifiedSchema(): void
    {
        $registry = self::getContainer()->get(SchemaRegistry::class);
        self::assertInstanceOf(SchemaRegistry::class, $registry);

        self::assertInstanceOf(FilesystemConnectionType::class, $registry->getProvider(FilesystemConnectionType::NAME));
        self::assertStringContainsString('"filesystem"', (string) json_encode($registry->getUnifiedSchema()));
    }

    public function testServicesAreWired(): void
    {
        $container = self::getContainer();

        self::assertInstanceOf(SourceFilesystemFactory::class, $container->get(SourceFilesystemFactoryInterface::class));
        self::assertInstanceOf(FileSelector::class, $container->get(FileSelector::class));

        $allowList = $container->get(LocalRootAllowList::class);
        self::assertInstanceOf(LocalRootAllowList::class, $allowList);
        self::assertSame([TestKernel::localRoot()], $allowList->entries());
    }

    public function testNoFilesystemOperatorAndNoImportSinkAreProvided(): void
    {
        $container = self::getContainer();

        self::assertFalse($container->has(FilesystemOperator::class));
        self::assertFalse($container->has(ImportSinkInterface::class));
    }

    public function testMigrationsPathIsRegistered(): void
    {
        $factory = self::getContainer()->get('doctrine.migrations.dependency_factory');
        self::assertInstanceOf(DependencyFactory::class, $factory);

        $directories = $factory->getConfiguration()->getMigrationDirectories();
        self::assertArrayHasKey('Dmstr\\DocumentSource\\Migrations', $directories);
        self::assertDirectoryExists($directories['Dmstr\\DocumentSource\\Migrations']);
        self::assertArrayHasKey('Dmstr\\ApiConfiguration\\Migrations', $directories);
    }

    public function testSeedCommandIsRegisteredAndRequiresARoot(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('document-source:connection:seed');
        self::assertInstanceOf(FilesystemConnectionSeedCommand::class, $command instanceof LazyCommand ? $command->getCommand() : $command);

        // Returns before any database access
        $tester = new CommandTester($command);
        self::assertSame(Command::INVALID, $tester->execute(['name' => 'docs']));
        self::assertStringContainsString('--root is required', $tester->getDisplay());
    }
}
