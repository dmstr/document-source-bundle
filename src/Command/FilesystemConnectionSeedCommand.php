<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Command;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\DocumentSource\Connection\FilesystemConnectionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Idempotent seed of one `filesystem` connection, e.g. from a setup script.
 *
 * The connection is keyed by its name within `type = 'filesystem'`. The
 * command builds the full configJson (discriminators included) from the
 * options: a missing connection is created (active), an existing one whose
 * configJson differs is updated in place (its active flag is kept), and an
 * existing one with an equal configJson is left alone (no flush,
 * `updatedAt` unchanged). The entity is validated with the validator before
 * anything is written, so the unified connection schema applies exactly as
 * through the API. The stored configJson is decrypted before the
 * comparison, so encrypted secrets (of adapters that declare any) do not
 * make an unchanged definition look changed.
 *
 * The command neither reads the root nor checks the allow-list: the root
 * may be mounted later. Check it with `app:api-configuration:health`.
 */
#[AsCommand(name: 'document-source:connection:seed', description: 'Create or update a filesystem connection idempotently (does not read the root)')]
final class FilesystemConnectionSeedCommand extends Command
{
    public const string RESULT_CREATED = 'created';
    public const string RESULT_UPDATED = 'updated';
    public const string RESULT_UNCHANGED = 'unchanged';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly ConfigSecrets $secrets,
    ) {
        parent::__construct();
    }

    /**
     * Builds the configJson of a `filesystem` connection. Pure; schema rules
     * (absolute root, no `..`, known adapter) are left to the validator so
     * the seed and the API report the same violations.
     *
     * @return array<string, mixed>
     */
    public static function buildConfig(string $root, string $adapter = FilesystemConnectionType::ADAPTER_LOCAL): array
    {
        return [
            FilesystemConnectionType::NAME => true,
            'type' => FilesystemConnectionType::NAME,
            'endpoint_type' => FilesystemConnectionType::ENDPOINT_TYPE,
            'adapter' => $adapter,
            'root' => $root,
        ];
    }

    /**
     * Whether a stored configJson equals the desired one. Key order does not
     * count, values are compared strictly. Pass the stored configJson
     * decrypted ({@see ConfigSecrets::decrypt()}).
     *
     * @param array<mixed> $stored
     * @param array<mixed> $desired
     */
    public static function isSameConfig(array $stored, array $desired): bool
    {
        return self::normalize($stored) === self::normalize($desired);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Connection name (the seed key among filesystem connections)')
            ->addOption('root', null, InputOption::VALUE_REQUIRED, 'Root directory; for adapter local an absolute path within DOCUMENT_SOURCE_LOCAL_ROOTS, e.g. /mnt/uploads/customer/project')
            ->addOption('adapter', null, InputOption::VALUE_REQUIRED, 'Storage adapter', FilesystemConnectionType::ADAPTER_LOCAL)
            ->setHelp(<<<'HELP'
                Creates the filesystem connection <info>name</info> or brings it in line with the
                given definition. Running it again with the same input changes nothing,
                so it is safe in setup scripts. The root is neither read nor checked
                against the allowed directories here; check it with
                <info>app:api-configuration:health</info>.

                  <info>%command.full_name% uploads --root=/mnt/uploads</info>
                  <info>%command.full_name% acme-handbook --root=/mnt/uploads/acme/handbook</info>
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');

        $root = trim(\is_string($input->getOption('root')) ? $input->getOption('root') : '');
        if ('' === $root) {
            $io->error('--root is required.');

            return Command::INVALID;
        }
        $adapter = \is_string($input->getOption('adapter')) ? $input->getOption('adapter') : FilesystemConnectionType::ADAPTER_LOCAL;
        $config = self::buildConfig($root, $adapter);

        $existing = $this->entityManager->getRepository(ApiConfiguration::class)
            ->findBy(['type' => FilesystemConnectionType::NAME, 'name' => $name]);

        if (\count($existing) > 1) {
            $io->error(\sprintf('%d filesystem connections are named "%s"; resolve the duplicates first.', \count($existing), $name));

            return Command::FAILURE;
        }

        $connection = $existing[0] ?? null;

        if (null !== $connection && self::isSameConfig($this->secrets->decrypt($connection->getConfigJson()), $config)) {
            $io->writeln(\sprintf('Filesystem connection "%s" unchanged (%s).', $name, $connection->getId()));

            return Command::SUCCESS;
        }

        if (null === $connection) {
            $result = self::RESULT_CREATED;
            $connection = (new ApiConfiguration())
                ->setName($name)
                ->setConfigJson($config)
                ->setActive(true);
        } else {
            $result = self::RESULT_UPDATED;
            $connection->setConfigJson($config);
        }

        $violations = $this->validator->validate($connection);
        if (\count($violations) > 0) {
            if (self::RESULT_UPDATED === $result) {
                // Drop the rejected change from the managed entity, so a later
                // flush in the same process cannot write it.
                $this->entityManager->refresh($connection);
            }

            $messages = [];
            foreach ($violations as $violation) {
                $path = $violation->getPropertyPath();
                $messages[] = ('' !== $path ? $path.': ' : '').$violation->getMessage();
            }
            $io->error(array_merge([\sprintf('Filesystem connection "%s" is invalid:', $name)], $messages));

            return Command::FAILURE;
        }

        if (self::RESULT_CREATED === $result) {
            $this->entityManager->persist($connection);
        }
        $this->entityManager->flush();

        $io->success(\sprintf('Filesystem connection "%s" %s (%s).', $name, $result, $connection->getId()));

        return Command::SUCCESS;
    }

    private static function normalize(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::normalize(...), $value);
    }
}
