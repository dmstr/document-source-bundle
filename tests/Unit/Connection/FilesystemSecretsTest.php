<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Unit\Connection;

use Dmstr\DocumentSource\Tests\Support\TestConfigSecrets;
use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\ApiConfiguration\Security\SecretSchemaResolver;
use Dmstr\DocumentSource\Command\FilesystemConnectionSeedCommand;
use Dmstr\DocumentSource\Connection\FilesystemConnectionType;
use Dmstr\DocumentSource\Filesystem\LocalRootAllowList;
use PHPUnit\Framework\TestCase;

/**
 * Secret handling of `filesystem` connections through the real
 * ConfigSecrets: the local branch declares no secret, so masking and
 * encryption leave its configuration readable, while any encrypted value
 * (e.g. a secret of a later adapter stored before a schema change) is
 * still masked on output.
 */
final class FilesystemSecretsTest extends TestCase
{
    public function testLocalBranchDeclaresNoSecret(): void
    {
        $schema = json_decode((string) file_get_contents(new FilesystemConnectionType(new LocalRootAllowList())->getSchemaPath()), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame([], SecretSchemaResolver::collectSecretPaths($schema));
    }

    public function testMaskAndEncryptKeepTheLocalConfiguration(): void
    {
        $secrets = TestConfigSecrets::create();
        $config = FilesystemConnectionSeedCommand::buildConfig('/mnt/uploads/acme');

        self::assertSame($config, $secrets->mask($config));
        self::assertSame($config, $secrets->encrypt($config));
        self::assertSame($config, $secrets->decrypt($secrets->encrypt($config)));
    }

    public function testEncryptedValuesAreMaskedEvenWithoutSchemaMarker(): void
    {
        $secrets = TestConfigSecrets::create();
        $foreign = ConfigSecrets::PREFIX.'c2VjcmV0';
        $config = FilesystemConnectionSeedCommand::buildConfig('/mnt/uploads/acme') + ['legacy' => $foreign];

        $masked = $secrets->mask($config);

        self::assertSame(ConfigSecrets::MASK, $masked['legacy']);
        self::assertSame('/mnt/uploads/acme', $masked['root']);
        self::assertStringNotContainsString('c2VjcmV0', (string) json_encode($masked));
    }
}
