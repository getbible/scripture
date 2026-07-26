# Caching and refresh

## Warm-on-first-query

When a translation is requested:

1. resolve its active generation;
2. reuse it when it exists and is fresh;
3. acquire the module warm lock when it is absent or stale;
4. check the active generation again;
5. stream the complete native module into staging;
6. validate and index the staged stream;
7. rename staging into its stream-hash generation;
8. atomically replace the `current.json` pointer; and
9. open the immutable index.

Only the compact index is loaded into memory. Verse records are read by exact
offset from `module.ndjson`.

## Process cache

Each PHP process retains at most 32 open snapshot readers in least-recently-used
order. A cached reader is reused only while its generation and index digest
still match the active pointer. Eviction releases its shared generation lease.

## Generation identity

The validated footer `stream_sha256` names the generation. The generated time is
operational metadata and is not part of the native deterministic contract.

Different absolute SWORD roots can affect preserved configuration values.
Generation identity must therefore be treated as an export identity, not as an
upstream module-version number.

## Refresh types

`refreshTranslation()` re-exports an already installed module and rebuilds its
snapshot.

A module update is a different operation: it changes the source SWORD root and
must be implemented by `ModuleProvisionerInterface` under an exclusive root
lock. ABI v1 does not currently provide that operation.

## Scheduling

Instantiation does not start a background process. Reliable execution belongs
to an external scheduler:

```php
$scripture->refreshIfDue();
```

Joomla CMS integrations can inject `ScheduledRefreshHandler`. CLI applications
can run `scripture:refresh --if-due` from cron or a systemd timer. See
[production operations](operations.md).

## Cleanup

The active generation and one valid prior generation are retained. Each open
snapshot holds a shared reader lease. Cleanup skips any old generation whose
lease cannot be acquired exclusively, so an active reader never loses the files
it already opened.

Under the bounded warm lock, maintenance also removes abandoned
`.staging-*` and `.corrupt-*` directories plus `current.*.tmp` files after one
hour.

## Recovery

The active pointer binds the selected index SHA-256. The index binds the full
module file size and SHA-256, and every indexed entry binds its canonical record
SHA-256. Reads therefore detect pointer, index, stream, offset, and record
corruption before returning a domain object.

When the active pointer or generation is invalid, the manager selects a valid
cached generation and repairs the pointer atomically. If no valid generation is
available, it performs a locked native export and activates it only after full
validation. Failed recovery leaves every previously valid generation unchanged.
