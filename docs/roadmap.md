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

## Phase 2 — production provisioning boundary (`0.2.0`)

- Explicit provisioning capability discovery.
- Selected/all-translation installation, refresh, and removal contracts.
- Per-module operation results and lifecycle events.
- Bounded shared/exclusive application locking around native readers and writers.
- Atomic catalog and snapshot invalidation after mutation.
- Exact ABI v1 unavailable adapter instead of unsafe archive downloading.

The matching additive getBibleSword provisioning ABI and `getbible/sword`
installer object are external integration gates. Once released, their adapter
can replace `AbiV1ModuleProvisioner` without changing the public Scripture API.

## Phase 3 — automated maintenance (`0.3.0`)

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
