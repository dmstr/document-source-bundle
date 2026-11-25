<!-- file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC -->

# dmstr/document-source-bundle

Symfony bundle for external document sources: file collections that an application imports into its document store again and again, such as a mounted directory of PDFs. It registers the connection type `filesystem` with [dmstr/api-configuration-bundle](https://github.com/dmstr/api-configuration-bundle) and provides the transport side of a sync; the document side (create, replace, skip, remove) stays in the application behind an import port.

What the bundle provides:

- the connection type `filesystem` (`endpoint_type: file`) with schema branch, health probe and the idempotent seed command `document-source:connection:seed`;
- an allow-list of local directories (`DOCUMENT_SOURCE_LOCAL_ROOTS`) that `local` connections may read below;
- `Filesystem\SourceFilesystemFactoryInterface`, which builds a read-only Flysystem operator per connection at runtime;
- `Selection\FileSelector`, which selects files by root and include/exclude globs, guards against symlink escapes and reads each file against a size limit;
- the import port `Import\ImportSinkInterface`, which the application implements.

The only storage adapter of this version is `local`. Sync definitions, a sync job and remote adapters are not part of the bundle; an application builds them on top of the selection and the import port.

## Requirements

- PHP >= 8.5
- Symfony 7.4
- dmstr/api-configuration-bundle 0.7 and dmstr/openapi-json-schema-bundle 0.5, with what they require (API Platform 4, Doctrine ORM 3, DoctrineMigrationsBundle)
- league/flysystem 3

## Installation

```bash
composer require dmstr/document-source-bundle
```

Register the bundle after dmstr/api-configuration-bundle and dmstr/openapi-json-schema-bundle:

```php
// config/bundles.php
return [
    // ...
    Dmstr\ApiConfiguration\ApiConfigurationBundle::class => ['all' => true],
    Dmstr\OpenApiJsonSchema\OpenApiJsonSchemaBundle::class => ['all' => true],
    Dmstr\DocumentSource\DocumentSourceBundle::class => ['all' => true],
];
```

The bundle prepends its migrations path (`./migrations`, namespace `Dmstr\DocumentSource\Migrations`) to the `doctrine_migrations` configuration, so DoctrineMigrationsBundle must be registered; this version ships no migration of its own. The table of the connections (`dmstr_api_configuration`) comes from the migrations of dmstr/api-configuration-bundle.

## Configuration

Package configuration is optional:

```yaml
# config/packages/dmstr_document_source.yaml
dmstr_document_source:
    # Default: '%env(csv:DOCUMENT_SOURCE_LOCAL_ROOTS)%'; a list works as well.
    local_roots: ['/mnt/documents']
```

### Allow-list of local roots

`local` connections read only below the directories of `dmstr_document_source.local_roots`, by default the env variable `DOCUMENT_SOURCE_LOCAL_ROOTS`: a comma-separated list of absolute directories, e.g. `DOCUMENT_SOURCE_LOCAL_ROOTS=/mnt/documents,/mnt/handbooks`. An unset or empty variable allows nothing. Relative and non-existing entries are ignored.

All checks compare real paths (`realpath()`): a connection root is allowed when its real path equals an entry's real path or lies below it, so neither `..` nor a symlink moves a root out of the allowed tree, and `/mnt/documents2` is not within `/mnt/documents`. Mount the directories read-only into the application and worker containers.

## The `filesystem` connection type

`Connection\FilesystemConnectionType` serves three registries of dmstr/api-configuration-bundle and dmstr/openapi-json-schema-bundle, tagged in `./config/services.yaml`: it contributes the `filesystem` branch to the unified `ApiConfiguration.configJson` schema, it is the health probe of the type, and it registers the type as extension with `endpoint_type: file`.

### Connection schema

The `filesystem` branch of `ApiConfiguration.configJson` is [`./src/Connection/filesystem.schema.json`](./src/Connection/filesystem.schema.json) (draft-07):

| Field | Type | Required | Meaning |
|---|---|---|---|
| `filesystem` | `true` | yes | discriminator flag (read-only) |
| `type` | `"filesystem"` | yes | connection type (read-only) |
| `endpoint_type` | `"file"` | yes | endpoint type (read-only); the registry knows only `rest` and `file` |
| `adapter` | `"local"` | yes, default `local` | storage adapter |
| `root` | string | yes | root directory of the source; for `local` an absolute path within the allow-list, no `..` segments, no backslashes |

```json
{"filesystem": true, "type": "filesystem", "endpoint_type": "file", "adapter": "local", "root": "/mnt/documents/handbook"}
```

The `local` branch carries no secret. A branch that carries credentials marks them `writeOnly`, so dmstr/api-configuration-bundle encrypts them at rest and returns them masked (`ConfigSecrets::mask`). The schema does not check the allow-list, because the root may be mounted after the connection is created; the factory and the health probe do.

### Health probe

`GET /api_configurations/{id}/health` of dmstr/api-configuration-bundle (below the API prefix of the application) and the command `app:api-configuration:health` check a `local` connection without listing it: the root must exist, be a directory, lie within the allow-list and be readable (the directory is opened once, never listed, let alone recursively). The probe never throws. Error codes:

- `CONFIGURATION`: no root, relative root or unknown adapter;
- `NOT_FOUND`: the root is missing or not a directory;
- `NOT_ALLOWED`: the root lies outside the allow-list, also through a symlink;
- `NOT_READABLE`: the application cannot open the directory.

The endpoint of the health result is a `file://` URI of the root (RFC 3986, segments percent-encoded), or `urn:dmstr:filesystem` when the configuration has no usable root.

## Seed command

```bash
bin/console document-source:connection:seed documents --root=/mnt/documents
bin/console document-source:connection:seed handbook --root=/mnt/documents/handbook
```

Creates the connection or brings it in line with the options (`--root`, required, and `--adapter`, default `local`), keyed by its name among `filesystem` connections. A missing connection is created active; an existing one with a different configuration is updated in place and keeps its active flag; a second run with the same input changes nothing (the stored configuration is decrypted before the comparison, `updatedAt` stays unchanged). The configuration is validated against the unified connection schema before anything is written; the root is not read, so check it with `app:api-configuration:health`.

## Source filesystems

`SourceFilesystemFactoryInterface::create(array $config): SourceFilesystem` takes the decrypted `configJson` and returns the operator of that connection. For `local` it resolves the root through the allow-list and uses `LocalFilesystemAdapter` with `SKIP_LINKS` and lazy root creation (it never creates the root). The factory and its results are never registered as `League\Flysystem\FilesystemOperator`: in a host application that service type is usually the document store, and a source must never be autowired in its place. A test can alias the interface to a fake.

## File selection

`FileSelector::select(SourceFilesystem, SelectionCriteria): Selection` opens a selection. `SelectionCriteria` holds the root below the connection root and the include and exclude globs:

- Globs follow symfony/finder's `Glob`: `*` and `?` stay within a path segment, `**` spans segments, `{a,b}` and `[abc]` work. They match the path relative to the selection root, so `*.pdf` selects top-level files only and `**/*.pdf` selects them at every depth. No include glob selects every file; an exclude glob always wins.
- Neither the root nor a glob may contain `..`, a leading slash, a backslash or a NUL byte.
- A missing selection root, or one that leads out of the connection root through a symlink, throws `SourceUnavailableException`, so an unmounted source never looks like an empty one.

Iterating a `Selection` yields a `SelectedFile` per matching regular file, with the source key (path relative to the selection root, forward slashes, no leading slash). `Selection::read(SelectedFile, int $maxBytes): ReadResult` reads one file and never throws for item-level problems:

- the real path of the file must still lie within the selection root, and it must be a regular file;
- the size is checked with `fileSize()` before anything is read, and again while copying (at most `$maxBytes + 1` bytes), so a file that grows in between is refused too;
- the opened file must be the checked one (device and inode);
- a failure is a `ReadResult` with the code `vanished`, `too_large`, `unreadable` or `outside_root` and a readable reason; the run continues;
- the content passes through as bytes: binary files, NUL bytes and invalid UTF-8 are not interpreted.

`Selection::rejected()` returns a `list<RejectedFile>` (`path`, `reason`) with the files the last pass did not yield because reading them is unsafe: `outside_root` (real path leaves the selection root), `not_a_regular_file` (FIFO, socket, device) and `unsupported_name` (unusable source key or colliding path). They are not failures of the run and do not count as seen.

The size limit is a parameter, not configuration of the bundle: the application passes its own limit.

## Import port

The application implements `Import\ImportSinkInterface` and aliases the interface to its service:

```php
interface ImportSinkInterface
{
    public function import(string $scope, SourceItem $item, ?callable $progress = null): ItemOutcome;
    public function deleteVanished(string $scope, array $seenKeys, bool $runClean): int;
}
```

`$scope` is an opaque string, e.g. the ID of a sync definition; `deleteVanished()` only removes documents of that scope and only when the run was clean (complete listing, no failed item). `ItemOutcome` is `created`, `replaced`, `skipped`, `unpublished` or `failed` with a reason. A sync run in outline:

```php
$selection = $selector->select($factory->create($decryptedConfig), new SelectionCriteria('handbook', ['**/*.pdf']));
$seen = [];
$clean = true;
foreach ($selection as $file) {
    $seen[] = $file->sourceKey;          // a failed item still counts as seen
    $read = $selection->read($file, $maxBytes);
    $outcome = $read->isOk() ? $sink->import($scope, $read->item) : null;
    $clean = $clean && null !== $outcome && !$outcome->isFailed();
}
$deleted = $sink->deleteVanished($scope, $seen, $clean && $selection->isComplete());
```

## Security notes

- Allow-list first: a `local` connection reads nothing outside `DOCUMENT_SOURCE_LOCAL_ROOTS`, compared by real path.
- Symlinks are not followed: the adapter skips link entries (`SKIP_LINKS`), and every matching file is checked by real path against the selection root, at listing time and again right before it is read. A file replaced by a symlink between listing and reading is refused.
- Files that are not regular files (FIFOs, sockets, devices) are rejected, so a FIFO cannot block a run. The local adapter lists a backslash in a name as a slash, so such a name can collide with another path: the second listing of a path is rejected as `unsupported_name`, and a lone `a\b.md` (listed as `a/b.md`) cannot be read and fails with `vanished`.
- A residual race remains for an attacker with write access to the source directory, who swaps a path component between the checks and the open; the device and inode comparison after opening narrows it. Mount sources read-only and grant write access to trusted parties only.
- Size limit before reading, and the copy stops one byte past the limit.
- Exception and failure messages name paths and reasons, never a credential of the connection.

## Testing

```bash
composer install
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite integration
```

- `unit` (`./tests/Unit`): no kernel, no database. Schema, secret masking, health probe, allow-list, factory, selection criteria, selection and reading on temporary trees under `sys_get_temp_dir()`, import port value objects and the seed configuration.
- `integration` (`./tests/Integration`): boots the minimal test kernel `./tests/Kernel/TestKernel.php`. Tests in the group `database` need a PostgreSQL server from `DATABASE_URL` (default in `./phpunit.dist.xml`); the schema is created by running the migrations. Without a server, run `vendor/bin/phpunit --testsuite integration --exclude-group database`.

The test of an unreadable root is skipped when the tests run as root, because root ignores directory permissions. CI is the GitHub Action `./.github/workflows/tests.yml` (PHP 8.5, PostgreSQL 18).

## License

MIT, see [`./LICENSE`](./LICENSE).
