<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Unit\Seed;

use Dmstr\DocumentSource\Tests\Support\TestConfigSecrets;
use Dmstr\DocumentSource\Command\FilesystemConnectionSeedCommand;
use Dmstr\DocumentSource\Connection\FilesystemConnectionType;
use Dmstr\DocumentSource\Filesystem\LocalRootAllowList;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

/**
 * The pure parts of `document-source:connection:seed`: the configJson it
 * builds is valid against the schema, and the comparison that makes the
 * seed idempotent ignores key order and compares decrypted values.
 */
final class FilesystemConnectionSeedConfigTest extends TestCase
{
    public function testBuiltConfigIsValidAgainstTheSchema(): void
    {
        $config = FilesystemConnectionSeedCommand::buildConfig('/mnt/uploads/acme');

        self::assertSame([
            'filesystem' => true,
            'type' => 'filesystem',
            'endpoint_type' => 'file',
            'adapter' => 'local',
            'root' => '/mnt/uploads/acme',
        ], $config);

        $schema = json_decode((string) file_get_contents(new FilesystemConnectionType(new LocalRootAllowList())->getSchemaPath()));
        self::assertTrue((new Validator())->validate(json_decode((string) json_encode($config)), $schema)->isValid());
    }

    public function testComparisonIgnoresKeyOrder(): void
    {
        $desired = FilesystemConnectionSeedCommand::buildConfig('/mnt/uploads/acme');
        $stored = array_reverse($desired, true);

        self::assertTrue(FilesystemConnectionSeedCommand::isSameConfig($stored, $desired));
        self::assertFalse(FilesystemConnectionSeedCommand::isSameConfig(['root' => '/mnt/uploads/other'] + $desired, $desired));
    }

    public function testComparisonOfDecryptedStoredConfig(): void
    {
        $secrets = TestConfigSecrets::create();
        $desired = FilesystemConnectionSeedCommand::buildConfig('/mnt/uploads/acme');

        self::assertTrue(FilesystemConnectionSeedCommand::isSameConfig($secrets->decrypt($secrets->encrypt($desired)), $desired));
    }
}
