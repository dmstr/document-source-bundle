<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Kernel;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Dmstr\ApiConfiguration\ApiConfigurationBundle;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiPlatformUtils\ApiPlatformUtilsBundle;
use Dmstr\DocumentSource\DocumentSourceBundle;
use Dmstr\DocumentSource\Tests\Support\TestConfigSecrets;
use Dmstr\OpenApiJsonSchema\OpenApiJsonSchemaBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal host application for the integration tests of the bundle.
 *
 * - The bundles dmstr/api-configuration-bundle needs to boot (FrameworkBundle,
 *   SecurityBundle, DoctrineBundle, DoctrineMigrationsBundle, API Platform,
 *   dmstr/api-platform-utils-bundle, dmstr/openapi-json-schema-bundle) and
 *   this bundle. The `ApiConfiguration` entity is mapped here, as a host
 *   application maps it; its id is a UUID, so no identity generation
 *   preference is needed.
 * - PostgreSQL from the `DATABASE_URL` environment variable (default in
 *   `phpunit.dist.xml`). The schema is created by running the migrations
 *   ({@see TestDatabase}); the container itself never connects, so tests
 *   without the `database` group run without a server.
 * - The allow-list of local roots is the system temp directory
 *   ({@see self::localRoot()}), where the tests create their trees.
 * - Credential encryption uses the insecure test key of
 *   {@see TestConfigSecrets}; the `filesystem` branch declares no secret.
 *
 * - {@see self::process()} makes the bundle's services public, as if an
 *   application injected them, so tests can fetch the ones nothing in the
 *   kernel uses (the container would remove them otherwise).
 *
 * The bundle registers no permissions and no secured operations, so the
 * kernel has neither a custom authenticator nor a permission voter.
 *
 * Cache and logs live under the system temp directory, so a test run leaves
 * no files in the repository.
 */
final class TestKernel extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    private const string BUNDLE_NAMESPACE = 'Dmstr\\DocumentSource\\';

    public const string OAUTH_REDIRECT_URI = 'https://app.example.com/api/oauth/callback';

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new ApiPlatformBundle();
        yield new ApiPlatformUtilsBundle();
        yield new OpenApiJsonSchemaBundle();
        yield new ApiConfigurationBundle();
        yield new DocumentSourceBundle();
    }

    /** The configured allow-list entry: the real path of the system temp directory. */
    public static function localRoot(): string
    {
        return (string) realpath(sys_get_temp_dir());
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (str_starts_with($id, self::BUNDLE_NAMESPACE)) {
                $definition->setPublic(true);
            }
        }
        foreach ($container->getAliases() as $id => $alias) {
            if (str_starts_with($id, self::BUNDLE_NAMESPACE)) {
                $alias->setPublic(true);
            }
        }
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->tmpDir().'/cache';
    }

    public function getLogDir(): string
    {
        return $this->tmpDir().'/log';
    }

    /**
     * Not the repository's `config/` (the bundle's service definitions): in
     * debug mode FrameworkBundle dumps `reference.php` into an existing
     * config directory. This one does not exist, so nothing is written.
     */
    private function getConfigDir(): string
    {
        return $this->tmpDir().'/config';
    }

    private function tmpDir(): string
    {
        return sys_get_temp_dir().'/dmstr-document-source-bundle-tests/'.$this->environment;
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()
            // Autowired by the OAuth services of dmstr/api-configuration-bundle
            ->set('app.oauth.redirect_uri', self::OAUTH_REDIRECT_URI);

        $container->extension('framework', [
            'test' => true,
            'secret' => 'test-secret',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'cache' => ['app' => 'cache.adapter.array'],
        ]);

        $container->extension('security', [
            'providers' => [
                'test_users' => ['memory' => ['users' => []]],
            ],
            'firewalls' => [
                'main' => [
                    'pattern' => '^/',
                    'stateless' => true,
                    'provider' => 'test_users',
                ],
            ],
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'url' => '%env(resolve:DATABASE_URL)%',
            ],
            'orm' => [
                'enable_native_lazy_objects' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'mappings' => [
                    'ApiConfiguration' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => \dirname((string) (new \ReflectionClass(ApiConfiguration::class))->getFileName()),
                        'prefix' => 'Dmstr\\ApiConfiguration\\Entity',
                    ],
                ],
            ],
        ]);

        $container->extension('doctrine_migrations', [
            'storage' => [
                'table_storage' => ['table_name' => 'doctrine_migration_versions'],
            ],
        ]);

        $container->extension('api_platform', [
            'title' => 'document-source-bundle tests',
            'formats' => [
                'jsonld' => ['application/ld+json'],
                'json' => ['application/json'],
            ],
            'enable_swagger_ui' => false,
            'enable_re_doc' => false,
        ]);

        $container->extension('dmstr_api_platform_utils', [
            // CredentialEncryption expects the 32 raw bytes, not Base64
            'credential_encryption' => ['key' => TestConfigSecrets::KEY],
        ]);

        $container->extension('dmstr_document_source', [
            'local_roots' => [self::localRoot()],
        ]);

        $services = $container->services()
            ->defaults()
            ->autowire()
            ->autoconfigure();

        // Keep the test output free of framework log lines
        $services->set('logger', NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
    }
}
