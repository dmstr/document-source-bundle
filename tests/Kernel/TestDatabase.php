<?php

// file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Tests\Kernel;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Creates the schema of the test database by running all registered
 * migrations (`doctrine:migrations:migrate`) once per PHPUnit process:
 * those of dmstr/api-configuration-bundle (table `dmstr_api_configuration`)
 * and the bundle's own path, which ships none yet. Already executed
 * migrations are skipped, so a database that is reused between runs stays
 * valid.
 */
final class TestDatabase
{
    private static bool $migrated = false;

    public static function migrate(KernelInterface $kernel): void
    {
        if (self::$migrated) {
            return;
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();

        $exitCode = $application->run(new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--no-interaction' => true,
            '--allow-no-migration' => true,
        ]), $output);

        if (0 !== $exitCode) {
            throw new \RuntimeException('Running the migrations failed: '.$output->fetch());
        }

        self::$migrated = true;
    }
}
