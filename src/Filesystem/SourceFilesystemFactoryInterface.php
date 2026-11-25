<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Filesystem;

use Dmstr\DocumentSource\Exception\InvalidSourceConfigurationException;
use Dmstr\DocumentSource\Exception\PathNotAllowedException;

/**
 * Builds the source filesystem of one `filesystem` connection. The single
 * place where source operators come from; a test can alias this interface
 * to a fake.
 */
interface SourceFilesystemFactoryInterface
{
    /**
     * @param array<string, mixed> $config the connection's configJson, DECRYPTED
     *                                     ({@see \Dmstr\ApiConfiguration\Security\ConfigSecrets::decrypt()});
     *                                     keys starting with `_` are ignored
     *
     * @throws InvalidSourceConfigurationException unknown adapter or malformed configuration
     * @throws PathNotAllowedException             `local` root missing or outside the allow-list
     */
    public function create(array $config): SourceFilesystem;
}
