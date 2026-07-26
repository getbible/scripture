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

## Status

Phases 0–2 are implemented. Installed SWORD modules are production-readable,
and the package exposes capability-driven provisioning contracts with bounded
reader/writer locking and per-module outcomes. The current native ABI still
reports remote mutation as unavailable until its additive provisioning API is
released. See the
[roadmap](docs/roadmap.md) and [provisioning boundary](docs/provisioning.md).

## Requirements

- Linux with PHP 8.2, 8.3, 8.4, or 8.5.
- The `getbiblesword` PHP extension, installed through PIE.
- Joomla Framework DI, Event, Filesystem, and Registry packages, installed by
  Composer.
- A readable SWORD module root.

```bash
pie install getbible/sword
php --ri getbiblesword
composer require getbible/scripture
```

## Quick start

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

Configuration precedence is explicit array values, environment values, and
documented defaults:

| Key | Environment variable | Default |
|---|---|---|
| `module_path` | `GETBIBLE_SCRIPTURE_MODULE_PATH` | Native extension resolution |
| `cache_path` | `GETBIBLE_SCRIPTURE_CACHE_PATH` | `$XDG_CACHE_HOME/getbible/scripture` |
| `refresh_interval` | `GETBIBLE_SCRIPTURE_REFRESH_INTERVAL` | `P1M` |
| `auto_refresh` | `GETBIBLE_SCRIPTURE_AUTO_REFRESH` | `true` |
| `lock_timeout` | `GETBIBLE_SCRIPTURE_LOCK_TIMEOUT` | `30` seconds |

See [configuration](docs/configuration.md) for operational details.

## Documentation

- [Architecture](docs/architecture.md)
- [Public API](docs/api.md)
- [Configuration](docs/configuration.md)
- [Contract v1 mapping](docs/contract-v1.md)
- [Caching and refresh](docs/caching.md)
- [Module provisioning](docs/provisioning.md)
- [Roadmap](docs/roadmap.md)
- [Releasing](docs/releasing.md)

## License

GPL-2.0-only, matching the native `getBibleSword` and `getbible/sword`
foundation. Individual CrossWire modules retain their own licenses and
distribution terms.
