<!-- file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC -->

# CLAUDE.md

This is a public open-source package maintained under github.com/dmstr.

Symfony bundle for external document sources: the connection type `filesystem` of dmstr/api-configuration-bundle (local mounts below an allow-list) with health probe and idempotent seed command, Flysystem operators built per connection at runtime, file selection by root and include/exclude globs with symlink and size guards, and the import port `ImportSinkInterface` that the application implements. See `README.md` for the concepts.

## Language policy

**Everything in this repository is in English**: commit messages, code, comments, documentation, test names and test data, and all communication about the repository (pull request titles and descriptions, code review, issues, and chat with the coding assistant). These packages are public and English-only; do not reference private projects, internal trackers or company internals.

## Requirements and tests

PHP >= 8.5 (see `composer.json`). The PHPUnit suite (configuration `phpunit.dist.xml`) has two test suites:

- `unit` (`tests/Unit`): no kernel, no database. Run it with `vendor/bin/phpunit --testsuite unit`. Filesystem tests work on temporary trees (`tests/Support/TempTree.php`); `tests/Support/TestConfigSecrets.php` is a real `ConfigSecrets` with an insecure test key.
- `integration` (`tests/Integration`): boots the test kernel `tests/Kernel/TestKernel.php` (dmstr/api-configuration-bundle with what it needs to boot, the system temp directory as allow-list). Tests in the group `database` need a PostgreSQL server from `DATABASE_URL`; the schema is created by running the migrations. Without a server, run `vendor/bin/phpunit --testsuite integration --exclude-group database`.

CI is the GitHub Action `.github/workflows/tests.yml` (PHP 8.5, PostgreSQL service container), which runs both suites. Test helpers live only under `tests/`.

## Invariants

- The migrations path `__DIR__.'/../migrations'` (namespace `Dmstr\DocumentSource\Migrations`) in `DocumentSourceBundle` never changes: applications store migration class names.
- The bundle never registers a `League\Flysystem\FilesystemOperator` service and never provides an `ImportSinkInterface` implementation.
- The bundle registers no permissions and no secured operations.
- The health probe never throws and never lists a directory.
