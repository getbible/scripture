# GetBible Scripture

[![CI](https://github.com/getbible/scripture/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/getbible/scripture/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.2--8.5-777bb4.svg)](composer.json)
[![Contract](https://img.shields.io/badge/contract-getbiblesword.ndjson%2Fv1-blue.svg)](docs/contract-v1.md)
[![License](https://img.shields.io/badge/license-GPL--2.0--only-blue.svg)](LICENSE)

`getbible/scripture` is the object-oriented Bible application layer for the
native [`getbible/sword`](https://github.com/getbible/sword) PHP extension. It
validates the complete `getbiblesword.ndjson/v1` stream and exposes immutable,
lazy `Translation`, `Book`, `Chapter`, and `Verse` objects without loading an
entire Bible object graph into every PHP process.

The package is deliberately Bible-only. Non-Bible SWORD modules remain visible
in the low-level catalog but are not exposed as Scripture translations.

## Capabilities

Installed SWORD Bible modules are exposed through validated, immutable
snapshots. The package provides bounded reader/writer locking, durable refresh
state, per-module outcomes, Joomla Console commands, and a Joomla Scheduled
Tasks bridge.

The released native ABI lists and exports installed modules. It does not
download, update, or remove CrossWire modules. Those operations remain disabled
unless the application injects a provisioner that explicitly advertises and
implements them. See the [provisioning boundary](docs/provisioning.md).

## Getting started

### Product-distributed runtime

People receiving an application should receive PHP, the native extension, the
Composer dependencies, and policy-approved SWORD modules as one tested runtime.
They should not need a compiler, PIE, or Composer on the production host. The
[shipping and deployment guide](docs/distribution.md) provides a concrete
container build and the equivalent installer contract for application
distributors.

### Existing Composer project

Direct library integration requires:

- Linux with PHP 8.2, 8.3, 8.4, or 8.5.
- The `getbiblesword` PHP extension, installed through PIE.
- Joomla Framework Console, DI, Event, Filesystem, and Registry packages,
  installed by Composer.
- A readable SWORD module root.

```bash
pie install 'getbible/sword:^0.1.1'
php --ri getbiblesword
composer require getbible/scripture
```

PIE owns the native-install confirmation and any administrator prompt. Version
`0.1.1` is the first extension release whose source asset follows PIE's normal
discovery convention. An already-installed `0.1.0` extension remains compatible
with this package, but its published source asset cannot be installed by that
one-command path.

The extension check is intentionally strict. Composer treats PHP extensions as
platform requirements; it verifies that `ext-getbiblesword` is loaded but does
not install it. Composer also does not execute scripts declared by dependency
packages. See [installation and setup](docs/installation.md) for the exact
boundary and supported deployment choices.

### Interactive setup

After Composer installation, inspect the runtime and configure the library:

```bash
vendor/bin/getbible-scripture scripture:doctor
vendor/bin/getbible-scripture scripture:setup
```

`scripture:setup` validates the loaded native runtime, module and cache paths,
installed Bible modules, refresh policy, and permissions. It does not invoke
PIE and cannot download CrossWire modules through ABI v1.

Applications can perform the same persisted configuration update through
`SetupServiceInterface::apply()`, including immediate warm-up. See
[programmatic setup](docs/api.md#programmatic-setup).

### Programmatic setup

```php
<?php

declare(strict_types=1);

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\DependencyInjection\ContainerFactory;
use GetBible\Scripture\Service\ScriptureInterface;

require __DIR__ . '/vendor/autoload.php';

$configuration = Configuration::fromEnvironment([
    'module_path' => '/var/lib/getbible/sword',
    'cache_path' => '/var/cache/getbible/scripture',
]);

$container = ContainerFactory::create($configuration);

/** @var ScriptureInterface $scripture */
$scripture = $container->get(ScriptureInterface::class);

$initialization = $scripture->initialize(['KJV']);

if (!$initialization->succeeded()) {
    throw new RuntimeException(
        json_encode(
            $initialization->toArray(),
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        ),
    );
}

$kjv = $scripture->translation('KJV');
$john316 = $kjv->book('John')->chapter(3)->verse(16);
$text = $john316->stripped();

if ($text === null) {
    throw new RuntimeException('This module does not provide stripped projections.');
}

echo $text->requireUtf8(), PHP_EOL;

foreach ($kjv->verses('John', 3, 16, 18) as $verse) {
    $text = $verse->stripped()?->requireUtf8() ?? $verse->raw()->requireUtf8();

    printf(
        "%s %d:%d %s\n",
        $verse->scope()->bookName()->requireUtf8(),
        $verse->scope()->chapter(),
        $verse->scope()->verse(),
        $text,
    );
}
```

The first request for an installed translation performs one full native export,
validates it, and creates an immutable indexed generation. Later requests open
that generation and hydrate only the requested objects.

Run interval-based maintenance from the application scheduler:

```php
$maintenance = $scripture->refreshIfDue();

if (!$maintenance->succeeded()) {
    // Send $maintenance->toArray() to the application's operational log.
}
```

No constructor performs network or full-module work. Both methods return
structured per-module results and continue safely across independent failures.

## Available data layers

Every verse retains:

- authoritative raw bytes;
- SWORD default-rendered bytes;
- stripped text bytes;
- the exact verse key and scope;
- lossless lexical annotation segments;
- SWORD's ordered three-level official attribute map; and
- the original contract record.

Every byte value verifies canonical Base64, decoded size, and SHA-256 before it
is exposed. The optional `utf8` member is treated only as a verified convenience
projection.

## Configuration

Configuration precedence depends on the entry point. Explicit configuration
objects always win. Interactive or programmatic setup applies request values,
then environment values, then persisted JSON, then documented defaults.
Normal container loading applies environment values over persisted JSON.

| Key | Environment variable | Default |
|---|---|---|
| `module_path` | `GETBIBLE_SCRIPTURE_MODULE_PATH` | Native extension resolution |
| `cache_path` | `GETBIBLE_SCRIPTURE_CACHE_PATH` | `$XDG_CACHE_HOME/getbible/scripture` |
| `refresh_interval` | `GETBIBLE_SCRIPTURE_REFRESH_INTERVAL` | `P1M` |
| `auto_refresh` | `GETBIBLE_SCRIPTURE_AUTO_REFRESH` | `true` |
| `lock_timeout` | `GETBIBLE_SCRIPTURE_LOCK_TIMEOUT` | `30` seconds |
| `modules` | `GETBIBLE_SCRIPTURE_MODULES` | All installed translations |
| `provisioning_enabled` | `GETBIBLE_SCRIPTURE_PROVISIONING_ENABLED` | `false` |
| `install_all` | `GETBIBLE_SCRIPTURE_INSTALL_ALL` | `false` |

See [configuration](docs/configuration.md) for operational details.

## Documentation

- [Architecture](docs/architecture.md)
- [Installation and setup](docs/installation.md)
- [Public API](docs/api.md)
- [Configuration](docs/configuration.md)
- [Contract v1 mapping](docs/contract-v1.md)
- [Caching and refresh](docs/caching.md)
- [Module provisioning](docs/provisioning.md)
- [Production operations](docs/operations.md)
- [Shipping and deployment](docs/distribution.md)
- [Releasing](docs/releasing.md)

## License

GPL-2.0-only, matching the native `getBibleSword` and `getbible/sword`
foundation. Individual CrossWire modules retain their own licenses and
distribution terms.
