# Roadmap

## Phase 0 — foundation

- GPL-compatible package identity.
- Composer and Joomla Framework dependencies.
- DI composition root.
- Configuration, exceptions, contracts, events, CI, and documentation.

## Phase 1 — installed-module MVP (`0.1.0`)

- Native engine adapter.
- Independent streaming v1 validator.
- Atomic indexed generations.
- Lazy Translation, Book, Chapter, Verse, range, metadata, annotation, and
  attribute APIs.
- Monthly configurable snapshot rotation.
- Deterministic unit fixtures and KJV integration evidence.
- Packagist registration and first release.

## Phase 2 — native provisioning

- Additive getBibleSword provisioning ABI.
- Matching `getbible/sword` PHP installer object.
- Repository, license, disclaimer, TLS, locking, staging, and rollback policy.
- Explicit one/all-translation installation.
- Module update and removal.

## Phase 3 — automated maintenance

- `initialize()`, `refresh()`, and `refreshIfDue()` over the native provisioner.
- CLI maintenance commands.
- Joomla Scheduled Tasks integration.
- Systemd and cron examples.
- Per-module results and lifecycle events.

## Phase 4 — production hardening and `1.0.0`

- Bounded APCu/LRU adapters and benchmarks.
- Reader-aware generation cleanup.
- Corruption and crash recovery.
- Concurrency, fuzz, and hostile-module tests.
- Multilingual and alternate-versification corpus.
- Stable public API and schema review.

## Release gates

The first stable release requires:

- the upstream v1 schema classification review to be accepted;
- no failed contract, static-analysis, style, or unit checks;
- real KJV Revelation 1 and 22 parity;
- at least one full installed translation query test; and
- maintainer approval of module licensing behavior.
