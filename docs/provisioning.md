# Module provisioning

## Current boundary

`getbible/sword` 0.1.0 embeds getBibleSword 0.3.0 ABI v1. It can list and export
local modules. It cannot:

- enumerate remote repositories;
- download or install a module;
- accept repository disclaimers;
- update or remove a module;
- verify a remote package;
- lock and atomically replace a module root; or
- query a single remote reference.

GetBible Scripture does not disguise a direct archive download as equivalent
native installation. `ModuleProvisionerInterface` is injectable, and
`ProvisioningCoordinatorInterface` provides the stable application boundary.
The default ABI-v1 implementation reports exact unavailable capabilities.

```php
$capabilities = $scripture->provisioningCapabilities();

if ($capabilities->canInstallSelected()) {
    $result = $scripture->installTranslations(['KJV', 'WEB']);
}
```

The public boundary supports selected and all-module installation, selected or
all-installed refresh, and removal. Each completed backend operation returns
ordered `ModuleProvisioningResult` objects with changed, skipped, or failed
status. Partial failure is therefore never hidden in a single boolean.

The library takes a bounded exclusive application lock around provisioning.
Native extraction takes the matching shared lock. This prevents cooperating
Scripture processes from reading the module root during mutation. An injected
native backend must still provide repository validation, staged installation,
atomic activation, and rollback guarantees.

## Provisioner security contract

Every mutating provisioner must:

1. select an explicit repository;
2. enforce HTTPS/TLS policy;
3. refresh and validate its catalog;
4. expose module license and disclaimer data;
5. record explicit acceptance where required;
6. download into a size-bounded staging area;
7. verify checksums or signed provenance when available;
8. reject traversal, links, devices, and unsafe archive entries;
9. acquire an exclusive interprocess module-root lock;
10. install into a versioned root;
11. validate the installed root with native `streamModules()`;
12. build Scripture snapshots;
13. atomically activate the root and snapshots together; and
14. retain the previous generation for rollback.

All-module installation must remain an explicit operation because translations
have independent licenses and can consume substantial disk and network
resources.

## Public lifecycle

```php
$scripture->initialize(); // Provision when supported, then warm snapshots.
$scripture->refresh();    // Refresh sources when supported, then rebuild.
$scripture->refreshIfDue();
```

These methods are implemented by `MaintenanceServiceInterface`. Under ABI v1,
initialization warms installed modules and reports missing modules precisely.
Remote mutation occurs only when configuration explicitly enables it and the
injected backend advertises the matching capability.

The default interval is `P1M`, overridable through configuration. Durable
last-success state ensures a failure never postpones the next retry.
