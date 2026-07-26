# Architecture

## Objective

GetBible Scripture is the reusable Bible application layer above
`getbible/sword`. It turns a validated, deterministic native export into fast,
immutable, lazily hydrated Scripture objects.

```text
Application
  -> ScriptureInterface
    -> MaintenanceService
      -> ProvisioningCoordinatorInterface
      -> TranslationSnapshotManager
        -> ContractV1Validator
        -> ModuleExtractorInterface
          -> SwordEngineAdapter
            -> GetBible\Sword\Engine
              -> getBibleSword 0.3.0 / SWORD 1.9.0
```

The Joomla DI container is the composition root. Domain objects never fetch
dependencies from it; each dependency is passed explicitly.

## Design boundaries

### Native extraction

`GetBible\Sword\Engine` is final and exposes only complete streamed module lists
and complete streamed module exports. `SwordEngineAdapter` isolates that final
extension class behind `ModuleExtractorInterface`.

### Contract validation

`ContractV1Validator` incrementally checks:

- LF framing;
- consecutive sequence numbers;
- record ordering state;
- required record shapes;
- canonical and hashed byte envelopes;
- ordered annotation reconstruction;
- ordered official attributes;
- artifact identifiers, chunks, sizes, and hashes;
- record and diagnostic counts;
- the exact pre-footer SHA-256; and
- a successful footer.

An observer receives validated records and exact file offsets. It never receives
unvalidated domain objects.

### Snapshots

A complete module export is written to a staging generation. Validation builds a
small JSON index containing verse offsets and module/configuration metadata. The
generation directory is renamed into its content hash, and an atomic `current.json`
pointer activates it.

```text
cache/
  translations/
    KJV/
      current.json
      warm.lock
      generations/
        <stream-sha256>/
          module.ndjson
          index.json
```

Old generations are immutable. A process that already opened one can continue
reading while a later process activates another generation.

### Domain model

`Translation`, `Book`, and `Chapter` contain compact coordinates and a shared
snapshot reader. `Verse` is hydrated only when requested. A translation object
therefore represents the complete translation without allocating every verse,
annotation, and attribute in memory.

Book identity is the module-specific tuple of versification, testament, and book
position. Names and abbreviations are lookup aliases, not universal IDs.

Introductions are indexed separately and never represented as verse zero.

### Provisioning

Native ABI v1 cannot install or update modules. `ModuleProvisionerInterface`
and `ProvisioningCoordinatorInterface` accept an application-supplied
implementation without changing the Scripture API. Capability discovery keeps
ABI limitations explicit. See [provisioning](provisioning.md).

### Automated maintenance

`MaintenanceServiceInterface` is the sole orchestration boundary for
initialization, forced refresh, interval-gated refresh, and health status. A
whole-run lock prevents overlapping scheduler executions. Within one run,
translation failures are isolated and returned as immutable per-module
outcomes.

The durable `maintenance/state.json` records the last attempt, last complete
success, last failure, failure summary, and consecutive failure count. Only a
complete success advances the interval anchor. State is written to a
synchronized temporary file and atomically renamed into place.

Joomla Console commands, Joomla Scheduled Tasks integrations, cron, and systemd
all invoke this same service. They do not reproduce lifecycle policy.

## SOLID application

- Single responsibility: native extraction, validation, indexing, querying,
  refresh policy, and events are separate services.
- Open/closed: ABI or persistence implementations can be added behind existing
  interfaces.
- Liskov substitution: test engines and native engine adapters obey the same
  streaming contract.
- Interface segregation: callers depend on query, extraction, or provisioning
  contracts instead of one large manager.
- Dependency inversion: application services depend on interfaces; Joomla DI
  creates concrete infrastructure.

## Concurrency

Snapshot warming uses an interprocess `flock`. The active generation is checked
again after acquiring the lock so only one process performs an extraction.

Every native export also holds a bounded shared module-root lifecycle lock.
Provisioning holds the matching exclusive lock, so cooperating application
processes cannot replace module files while the native engine reads them.

A separate bounded maintenance lock serializes complete scheduled runs. Lock
acquisition has a configurable timeout; it never waits indefinitely.

The native extension serializes SWORD access within one PHP process. Different
processes remain independent.

Any native module provisioner must additionally enforce repository, staging,
atomic-install, and rollback policy within its own boundary. Snapshot
immutability does not make an in-place native install safe.

## Failure behavior

A failed export, failed validation, failed index write, or failed activation
removes its staging directory and leaves the active generation unchanged.
Native exceptions retain their original status code as the previous exception.

A partially failed maintenance run preserves the prior `last_success_at`, stores
the new failure, and returns a non-zero console exit code. Healthy modules still
complete, which prevents one malformed translation from starving every other
translation.

No constructor performs network I/O, module installation, or a full translation
export.
