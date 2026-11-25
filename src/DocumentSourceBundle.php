<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * External document sources for dmstr/api-configuration-bundle: the
 * `filesystem` connection type (schema branch, health probe, idempotent
 * seed command `document-source:connection:seed`), a factory that builds a
 * Flysystem operator per connection at runtime
 * ({@see Filesystem\SourceFilesystemFactoryInterface}), the file selection
 * by root and include/exclude globs ({@see Selection\FileSelector}) and the
 * import port the application implements ({@see Import\ImportSinkInterface}).
 *
 * The bundle never registers a `League\Flysystem\FilesystemOperator`
 * service: in the host application that service type is the document
 * store, and a source filesystem must never be autowired in its place.
 *
 * Configuration (`dmstr_document_source`):
 *
 *     dmstr_document_source:
 *         # Directories `local` connections may read below. A list, or a
 *         # comma-separated string; the default reads the env variable
 *         # DOCUMENT_SOURCE_LOCAL_ROOTS (empty or unset = no local roots).
 *         local_roots: '%env(csv:DOCUMENT_SOURCE_LOCAL_ROOTS)%'
 */
final class DocumentSourceBundle extends AbstractBundle
{
    public const string LOCAL_ROOTS_ENV = 'DOCUMENT_SOURCE_LOCAL_ROOTS';

    protected string $extensionAlias = 'dmstr_document_source';

    public function configure(DefinitionConfigurator $definition): void
    {
        // A variable node: the default is an env placeholder that resolves to
        // a list at runtime (csv processor), an app may also configure a list.
        // @phpstan-ignore-next-line
        $definition->rootNode()
            ->children()
                ->variableNode('local_roots')
                    ->info('Directories below which `local` connections may read (list or comma-separated string); default: env DOCUMENT_SOURCE_LOCAL_ROOTS')
                    ->defaultValue('%env(csv:'.self::LOCAL_ROOTS_ENV.')%')
                ->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // The bundle owns its migrations path (it ships no migration yet).
        $builder->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'Dmstr\\DocumentSource\\Migrations' => __DIR__.'/../migrations',
            ],
        ]);
    }

    /**
     * @param array{local_roots: mixed} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            // Unset env variable = no local roots (fail closed), not a container error.
            ->set('env('.self::LOCAL_ROOTS_ENV.')', '')
            ->set('dmstr_document_source.local_roots', $config['local_roots']);

        $container->import(__DIR__.'/../config/services.yaml');
    }
}
