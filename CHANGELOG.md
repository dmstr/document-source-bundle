<!-- file generated with AI assistance: Claude Code - 2026-11-25 17:00:37 UTC -->

# Changelog

All notable changes to this bundle. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/) and come from the Git tags.

## [0.1.0] - 2026-11-25

Initial public release.

### Added

- The connection type `filesystem` of dmstr/api-configuration-bundle (`endpoint_type: file`, adapter `local`): schema branch `filesystem.schema.json`, health probe and a thin API client.
- The allow-list of local roots (`dmstr_document_source.local_roots`, default env `DOCUMENT_SOURCE_LOCAL_ROOTS`), compared by real path.
- The idempotent seed command `document-source:connection:seed`.
- `SourceFilesystemFactoryInterface`, which builds a read-only Flysystem operator per connection.
- `FileSelector`, `SelectionCriteria` and `Selection`: file selection by root and include/exclude globs with symlink, file type and size guards.
- The import port `ImportSinkInterface` with `SourceItem` and `ItemOutcome`.
- PHPUnit suites `unit` and `integration` (test kernel, PostgreSQL) and a GitHub Action.

### Changed

- The endpoint identifier of a `filesystem` connection without a usable root is `urn:dmstr:filesystem`. Applications that matched the previous identifier of the in-application version of this bundle must update the match; the value is only reported in health results and never stored.

### Requirements

- Licence: MIT.
- PHP >= 8.5, Symfony 7.4, dmstr/api-configuration-bundle 0.7, dmstr/openapi-json-schema-bundle 0.5, Doctrine ORM 3, league/flysystem 3.

[0.1.0]: https://github.com/dmstr/document-source-bundle/releases/tag/0.1.0
