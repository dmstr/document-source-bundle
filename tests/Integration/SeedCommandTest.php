<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Integration;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\DocumentSource\Command\FilesystemConnectionSeedCommand;
use Dmstr\DocumentSource\Connection\FilesystemConnectionType;
use Dmstr\DocumentSource\Tests\Kernel\TestDatabase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `document-source:connection:seed` against PostgreSQL: creates a missing
 * connection, leaves an equal one alone, updates a changed one in place
 * (keeping its active flag) and writes nothing for an invalid definition.
 */
#[Group('database')]
final class SeedCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        TestDatabase::migrate($kernel);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $this->entityManager->createQuery('DELETE FROM '.ApiConfiguration::class.' c WHERE c.type = :type')
            ->setParameter('type', FilesystemConnectionType::NAME)
            ->execute();
    }

    public function testCreatesAMissingConnection(): void
    {
        $tester = $this->seed('documents', '/mnt/documents');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('created', $tester->getDisplay());

        $connection = $this->find('documents');
        self::assertNotNull($connection);
        self::assertTrue($connection->isActive());
        self::assertSame(FilesystemConnectionType::ENDPOINT_TYPE, $connection->getEndpointType());
        self::assertSame(FilesystemConnectionSeedCommand::buildConfig('/mnt/documents'), $connection->getConfigJson());
    }

    public function testSecondRunWithTheSameInputChangesNothing(): void
    {
        $this->seed('documents', '/mnt/documents');
        $updatedAt = $this->find('documents')?->getUpdatedAt();

        $tester = $this->seed('documents', '/mnt/documents');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('unchanged', $tester->getDisplay());
        self::assertEquals($updatedAt, $this->find('documents')?->getUpdatedAt());
    }

    public function testChangedRootUpdatesTheConnectionAndKeepsItsActiveFlag(): void
    {
        $this->seed('documents', '/mnt/documents');
        $connection = $this->find('documents');
        self::assertNotNull($connection);
        $connection->setActive(false);
        $this->entityManager->flush();

        $tester = $this->seed('documents', '/mnt/documents/handbook');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('updated', $tester->getDisplay());
        $connection = $this->find('documents');
        self::assertNotNull($connection);
        self::assertSame('/mnt/documents/handbook', $connection->getConfigJson()['root']);
        self::assertFalse($connection->isActive());
    }

    public function testInvalidDefinitionWritesNothing(): void
    {
        $tester = $this->seed('documents', 'relative/root');

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('is invalid', $tester->getDisplay());
        self::assertNull($this->find('documents'));
    }

    private function seed(string $name, string $root): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('document-source:connection:seed'));
        $tester->execute(['name' => $name, '--root' => $root]);

        return $tester;
    }

    private function find(string $name): ?ApiConfiguration
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(ApiConfiguration::class)
            ->findOneBy(['type' => FilesystemConnectionType::NAME, 'name' => $name]);
    }
}
