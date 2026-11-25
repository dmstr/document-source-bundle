<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Unit\Connection;

use Dmstr\DocumentSource\Connection\FilesystemConnectionType;
use Dmstr\DocumentSource\Filesystem\LocalRootAllowList;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Kernel-free checks of the `filesystem` branch of ApiConfiguration.configJson
 * (filesystem.schema.json), validated like ApiConfigurationValidator does
 * (opis, after json_decode(json_encode())).
 */
final class FilesystemSchemaTest extends TestCase
{
    private const array BASE = [
        'filesystem' => true,
        'type' => 'filesystem',
        'endpoint_type' => 'file',
        'adapter' => 'local',
        'root' => '/mnt/uploads/acme/handbook',
    ];

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function validConfigs(): iterable
    {
        yield 'minimal' => [self::BASE];
        yield 'allow-list entry itself' => [['root' => '/mnt/uploads'] + self::BASE];
        yield 'dots in names' => [['root' => '/mnt/uploads/v1.2/..hidden.d'] + self::BASE];
        yield 'trailing slash' => [['root' => '/mnt/uploads/acme/'] + self::BASE];
        // opis applies the `default` of a missing property before `required`
        // (as in ApiConfigurationValidator): adapter falls back to local.
        $withoutAdapter = self::BASE;
        unset($withoutAdapter['adapter']);
        yield 'missing adapter defaults to local' => [$withoutAdapter];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('validConfigs')]
    public function testValidConfigurationsPass(array $config): void
    {
        self::assertTrue($this->validate($config), json_encode($config));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigs(): iterable
    {
        $withoutRoot = self::BASE;
        unset($withoutRoot['root']);

        yield 'missing root' => [$withoutRoot];
        $withoutAdapter = self::BASE;
        unset($withoutAdapter['adapter']);
        yield 'missing adapter, relative root' => [['root' => 'relative'] + $withoutAdapter];
        yield 'relative root' => [['root' => 'mnt/uploads'] + self::BASE];
        yield 'empty root' => [['root' => ''] + self::BASE];
        yield 'traversal in root' => [['root' => '/mnt/uploads/../etc'] + self::BASE];
        yield 'trailing traversal' => [['root' => '/mnt/uploads/..'] + self::BASE];
        yield 'backslash in root' => [['root' => '/mnt/uploads\\..\\etc'] + self::BASE];
        yield 'root as number' => [['root' => 42] + self::BASE];
        yield 'unknown adapter' => [['adapter' => 'sftp'] + self::BASE];
        yield 'unknown property' => [self::BASE + ['password' => 'secret']];
        yield 'another connection type' => [['type' => 'other'] + self::BASE];
        yield 'discriminator false' => [['filesystem' => false] + self::BASE];
        yield 'endpoint type rest' => [['endpoint_type' => 'rest'] + self::BASE];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigs')]
    public function testInvalidConfigurationsAreRejected(array $config): void
    {
        self::assertFalse($this->validate($config), json_encode($config));
    }

    public function testBranchFollowsTheDiscriminatorConvention(): void
    {
        $schema = $this->schema();

        self::assertSame('http://json-schema.org/draft-07/schema#', $schema['$schema']);
        self::assertFalse($schema['additionalProperties']);
        self::assertSame(['filesystem', 'type', 'endpoint_type', 'adapter', 'root'], $schema['required']);
        foreach (['filesystem' => true, 'type' => 'filesystem', 'endpoint_type' => 'file'] as $field => $value) {
            self::assertSame($value, $schema['properties'][$field]['const'], $field);
            self::assertTrue($schema['properties'][$field]['readOnly'], $field);
        }
        self::assertSame(FilesystemConnectionType::ADAPTERS, $schema['properties']['adapter']['enum']);
    }

    public function testEveryCredentialFieldIsWriteOnly(): void
    {
        // The local branch has no credential. Fields of later adapters that
        // carry one (password, key, token, secret) must be writeOnly.
        foreach ($this->schema()['properties'] as $name => $property) {
            if (1 === preg_match('/password|secret|token|key/i', (string) $name)) {
                self::assertTrue($property['writeOnly'] ?? false, $name);
            }
        }
        self::assertArrayNotHasKey('writeOnly', $this->schema()['properties']['root']);
    }

    public function testExtensionPointsAtTheSchema(): void
    {
        $type = new FilesystemConnectionType(new LocalRootAllowList());

        self::assertSame('filesystem', $type->getName());
        self::assertSame('file', $type->getType());
        self::assertFileExists($type->getSchemaPath());
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $path = new FilesystemConnectionType(new LocalRootAllowList())->getSchemaPath();

        return json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validate(array $config): bool
    {
        $data = json_decode(json_encode($config, \JSON_THROW_ON_ERROR));
        $schema = json_decode(json_encode($this->schema(), \JSON_THROW_ON_ERROR));

        return (new Validator())->validate($data, $schema)->isValid();
    }
}
