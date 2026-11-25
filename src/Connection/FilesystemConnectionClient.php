<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Connection;

use Dmstr\ApiConfiguration\ApiClient\ApiClientInterface;
use Dmstr\ApiConfiguration\ApiClient\AuthenticationFailedException;

/**
 * Thin ApiClientInterface adapter for `filesystem` connections, created by
 * {@see FilesystemConnectionType::createClient()}. It exists for the
 * platform commands that go through the extension registry
 * (`app:api:test-connection`); its health info is the probe of the connection type,
 * the project-management methods are no-ops. Reading files goes through
 * {@see \Dmstr\DocumentSource\Filesystem\SourceFilesystemFactoryInterface}.
 */
final class FilesystemConnectionClient implements ApiClientInterface
{
    /**
     * @param array<string, mixed> $config the connection's decrypted configJson
     */
    public function __construct(
        private readonly array $config,
        private readonly FilesystemConnectionType $type,
    ) {
    }

    public function authenticate(): void
    {
        $health = $this->type->probe($this->config);
        if ('ok' !== $health['status']) {
            throw new AuthenticationFailedException(\is_string($health['message'] ?? null) ? $health['message'] : 'Filesystem probe failed.');
        }
    }

    public function getType(): string
    {
        return FilesystemConnectionType::ENDPOINT_TYPE;
    }

    public function getApiName(): string
    {
        return FilesystemConnectionType::NAME;
    }

    /**
     * @return array<string, mixed>
     */
    public function getHealthInfo(): array
    {
        return $this->type->probe($this->config);
    }

    public function getEndpoint(): string
    {
        return $this->type->getEndpoint($this->config);
    }

    public function getProjects(): array
    {
        return [];
    }

    public function getTodos(string $projectId): array
    {
        return [];
    }

    public function getTodo(string $projectId, string $todoId): ?array
    {
        return null;
    }
}
