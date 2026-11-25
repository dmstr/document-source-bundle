<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Connection;

use Dmstr\ApiConfiguration\ApiClient\ApiClientInterface;
use Dmstr\ApiConfiguration\Extension\ApiExtensionInterface;
use Dmstr\ApiConfiguration\Health\HealthProbeInterface;
use Dmstr\DocumentSource\Exception\PathNotAllowedException;
use Dmstr\DocumentSource\Filesystem\LocalRootAllowList;
use Dmstr\OpenApiJsonSchema\Interface\SchemaProviderInterface;

/**
 * The `filesystem` connection type of dmstr/api-configuration-bundle.
 *
 * One class serves three registries, tagged explicitly in the bundle's
 * services.yaml: as SchemaProviderInterface it contributes the
 * `filesystem` branch (filesystem.schema.json) to the unified
 * `ApiConfiguration.configJson` anyOf, which also drives validation and
 * the encryption and masking of `writeOnly` keys; as HealthProbeInterface
 * it answers `GET /api_configurations/{id}/health` (below the API prefix) and
 * `app:api-configuration:health`; as ApiExtensionInterface it registers the
 * type with `endpoint_type: file` (the registry knows only `rest` and
 * `file`) and creates a thin client whose health info is the same probe.
 *
 * The probe of a `local` connection checks that the root exists, is a
 * readable directory and lies within the allow-list; it opens the root
 * directory but never lists it, let alone recursively. It never throws and
 * reports the codes CONFIGURATION, NOT_ALLOWED, NOT_FOUND and NOT_READABLE.
 */
final class FilesystemConnectionType implements ApiExtensionInterface, SchemaProviderInterface, HealthProbeInterface
{
    public const string NAME = 'filesystem';
    public const string ENDPOINT_TYPE = 'file';

    public const string ADAPTER_LOCAL = 'local';
    /** Adapters this version supports. */
    public const array ADAPTERS = [self::ADAPTER_LOCAL];

    public const string CODE_CONFIGURATION = 'CONFIGURATION';
    public const string CODE_NOT_ALLOWED = 'NOT_ALLOWED';
    public const string CODE_NOT_FOUND = 'NOT_FOUND';
    public const string CODE_NOT_READABLE = 'NOT_READABLE';

    public function __construct(
        private readonly LocalRootAllowList $allowList,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getType(): string
    {
        return self::ENDPOINT_TYPE;
    }

    public function getSchemaPath(): string
    {
        return __DIR__.'/filesystem.schema.json';
    }

    /**
     * Performs no I/O; the probe runs in getHealthInfo().
     *
     * @param array<string, mixed> $config
     */
    public function createClient(array $config): ApiClientInterface
    {
        return new FilesystemConnectionClient($config, $this);
    }

    /**
     * A `file://` URI of the configured root (RFC 3986, segments
     * percent-encoded); `urn:dmstr:filesystem` without a usable root.
     *
     * @param array<string, mixed> $config
     */
    public function getEndpoint(array $config): string
    {
        $root = $config['root'] ?? null;
        if (!\is_string($root) || !str_starts_with($root, '/')) {
            return 'urn:dmstr:'.self::NAME;
        }

        return 'file://'.implode('/', array_map(rawurlencode(...), explode('/', $root)));
    }

    /**
     * @param array<string, mixed> $config decrypted configuration
     *
     * @return array<string, mixed> raw health data for the platform's HealthNormalizer
     */
    public function probe(array $config): array
    {
        $adapter = $config['adapter'] ?? self::ADAPTER_LOCAL;
        if (self::ADAPTER_LOCAL !== $adapter) {
            return self::failure(self::CODE_CONFIGURATION, \sprintf(
                'Unsupported filesystem adapter "%s"; supported: %s.',
                \is_scalar($adapter) ? (string) $adapter : get_debug_type($adapter),
                implode(', ', self::ADAPTERS),
            ));
        }

        return $this->probeLocal($config);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function probeLocal(array $config): array
    {
        $root = $config['root'] ?? null;
        if (!\is_string($root) || '' === $root) {
            return self::failure(self::CODE_CONFIGURATION, 'The local filesystem connection has no "root".');
        }

        // Existence first, so a missing mount is NOT_FOUND, not NOT_ALLOWED.
        if (!str_starts_with($root, '/') || str_contains($root, "\0")) {
            return self::failure(self::CODE_CONFIGURATION, 'The local root is not an absolute path.');
        }
        if (false === realpath($root)) {
            return self::failure(self::CODE_NOT_FOUND, \sprintf('The local root "%s" does not exist.', $root));
        }

        try {
            $realRoot = $this->allowList->resolve($root);
        } catch (PathNotAllowedException $e) {
            return self::failure(self::CODE_NOT_ALLOWED, $e->getMessage());
        }

        if (!is_dir($realRoot)) {
            return self::failure(self::CODE_NOT_FOUND, \sprintf('The local root "%s" is not a directory.', $root));
        }

        // Open the directory once (no listing): proves read and search permission.
        $handle = is_readable($realRoot) ? @opendir($realRoot) : false;
        if (false === $handle) {
            return self::failure(self::CODE_NOT_READABLE, \sprintf('The local root "%s" is not readable by the application.', $root));
        }
        closedir($handle);

        return [
            'status' => 'ok',
            'authenticated' => true,
            'message' => \sprintf('Local root "%s" is readable and within the allowed directories.', $root),
            'metadata' => [
                'apiName' => self::NAME,
                'custom' => [
                    'adapter' => self::ADAPTER_LOCAL,
                    'root' => $root,
                    'realRoot' => $realRoot,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function failure(string $code, string $message): array
    {
        return [
            'status' => 'error',
            'authenticated' => false,
            'message' => $message,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
