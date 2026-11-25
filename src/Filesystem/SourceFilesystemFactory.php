<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Filesystem;

use Dmstr\DocumentSource\Connection\FilesystemConnectionType;
use Dmstr\DocumentSource\Exception\InvalidSourceConfigurationException;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Builds a Flysystem operator per `filesystem` connection at runtime.
 *
 * `local`: the root is resolved through the {@see LocalRootAllowList}
 * (real path, must lie within an entry) and served by a
 * LocalFilesystemAdapter with SKIP_LINKS (symlinks are not listed) and lazy
 * root creation (the adapter never creates the root on a read-only mount).
 * The operator is only ever read from.
 *
 * The result is never registered as `League\Flysystem\FilesystemOperator`:
 * that service type is the application's document store.
 */
final class SourceFilesystemFactory implements SourceFilesystemFactoryInterface
{
    public function __construct(
        private readonly LocalRootAllowList $allowList,
    ) {
    }

    public function create(array $config): SourceFilesystem
    {
        $adapter = $config['adapter'] ?? FilesystemConnectionType::ADAPTER_LOCAL;

        return match ($adapter) {
            FilesystemConnectionType::ADAPTER_LOCAL => $this->createLocal($config),
            default => throw new InvalidSourceConfigurationException(\sprintf(
                'Unsupported filesystem adapter "%s"; supported: %s.',
                \is_scalar($adapter) ? (string) $adapter : get_debug_type($adapter),
                implode(', ', FilesystemConnectionType::ADAPTERS),
            )),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createLocal(array $config): SourceFilesystem
    {
        $root = $config['root'] ?? null;
        if (!\is_string($root) || '' === $root) {
            throw new InvalidSourceConfigurationException('The local filesystem connection has no "root".');
        }

        $realRoot = $this->allowList->resolve($root);
        if (!is_dir($realRoot)) {
            throw new InvalidSourceConfigurationException(\sprintf('The local root "%s" is not a directory.', $root));
        }

        $adapter = new LocalFilesystemAdapter(
            $realRoot,
            linkHandling: LocalFilesystemAdapter::SKIP_LINKS,
            lazyRootCreation: true,
        );

        return new SourceFilesystem(
            new Filesystem($adapter, ['allow_relative_path_traversal' => false]),
            FilesystemConnectionType::ADAPTER_LOCAL,
            $realRoot,
        );
    }
}
