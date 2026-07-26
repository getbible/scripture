# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-07-26

### Added

- Initial Composer package structure.
- Joomla DI composition root and service provider.
- Native `getbible/sword` engine adapter.
- Independent streaming validator for `getbiblesword.ndjson/v1`.
- Lossless contract and Scripture domain objects.
- Atomic, lazy translation snapshot generation.
- Configurable monthly snapshot rotation and Joomla lifecycle events.
- Explicit provisioning capability discovery.
- Selected/all installation, refresh, and removal contracts.
- Ordered per-module provisioning outcomes.
- Bounded shared/exclusive module-root lifecycle locking.
- Provisioning lifecycle events and atomic process-cache invalidation.
- Durable initialization, refresh, and interval-gated refresh services.
- Per-module maintenance failure isolation and durable failure state.
- Joomla Console initialization, refresh, and status commands.
- Joomla Scheduled Tasks callable integration.
- Production cron, systemd, health-check, and scheduler documentation.
- Interactive, atomic application configuration through `scripture:setup`.
- Read-only runtime and deployment diagnostics through `scripture:doctor`.
- Reader leases, bounded generation retention, stale-staging cleanup, and
  corruption recovery for immutable snapshots.
- Real released-extension integration against CrossWire KJV Revelation 1 and
  22, plus retained machine-readable evidence.
- Coverage inventory, lowest-dependency, security-audit, and clean Composer
  distribution checks.
- Validated tag-driven GitHub release automation.
- Product shipping, installation, and deployment documentation.

### Changed

- Project license aligned with the GPL-2.0-only native dependency.
- Native extension compatibility is restricted to the tested `0.1.x` line.
- Runtime configuration can be persisted in a versioned JSON document while
  retaining explicit environment overrides.
- Public documentation focuses on supported integration and operational
  constraints.

### Security

- Module identifiers, snapshot pointers, indexes, entry records, and filesystem
  paths receive boundary validation before use.
- Snapshot recovery verifies bound hashes and preserves leased readers before
  activation or cleanup.

[Unreleased]: https://github.com/getbible/scripture/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/getbible/scripture/releases/tag/v1.0.0
