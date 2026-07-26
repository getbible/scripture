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
native installation. `ModuleProvisionerInterface` is injectable, but the
default ABI-v1 implementation reports the capability as unavailable.

## Required production workflow

The planned native provisioner must:

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

## Proposed additive native API

Provisioning should be added without changing ABI v1 extraction symbols:

```text
gbs_list_remote_modules_v2
gbs_sync_repository_v2
gbs_install_module_v2
gbs_update_module_v2
gbs_remove_module_v2
```

The PHP extension should expose a separate installer/manager object rather than
adding network side effects to `GetBible\Sword\Engine`.

## Public lifecycle target

After the native boundary is available:

```php
$scripture->initialize(); // explicit install of configured Bible modules
$scripture->refresh();    // explicit remote sync + atomic update
$scripture->refreshIfDue();
```

The default interval will remain `P1M`, overridable through configuration.
