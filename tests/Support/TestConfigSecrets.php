<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Support;

use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\ApiConfiguration\Security\SecretSchemaResolver;
use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use Dmstr\ApiPlatformUtils\Service\CredentialEncryption;
use Dmstr\DocumentSource\Connection\FilesystemConnectionType;
use Dmstr\DocumentSource\Filesystem\LocalRootAllowList;
use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * A real ConfigSecrets for the kernel-free tests: its schema registry knows
 * only the `filesystem` schema, and it encrypts with a fixed test key.
 */
final class TestConfigSecrets
{
    /**
     * INSECURE, PUBLICLY KNOWN TEST KEY (32 raw bytes, as CredentialEncryption
     * expects it); tests only. The test kernel uses the same value.
     */
    public const string KEY = 'insecure-test-key-0123456789abcd';

    private function __construct()
    {
    }

    public static function create(): ConfigSecrets
    {
        $registry = new SchemaRegistry(new ArrayAdapter(), new NullLogger());
        $registry->register(new FilesystemConnectionType(new LocalRootAllowList()));

        return new ConfigSecrets(
            new SecretSchemaResolver($registry, new ApiExtensionRegistry()),
            static fn (): CredentialEncryption => new CredentialEncryption(self::KEY),
        );
    }
}
