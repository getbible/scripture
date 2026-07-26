# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

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

### Changed

- Project license aligned with the GPL-2.0-only native dependency.
