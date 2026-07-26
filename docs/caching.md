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
$scripture->refreshTranslation('KJV');
```

Joomla CMS integrations should call the refresh service from Scheduled Tasks.
CLI applications can call it from cron or a systemd timer.

## Cleanup

The first release retains prior immutable generations so active readers cannot
lose files. Bounded generation cleanup is a later hardening phase and must use
reader-aware retention.
