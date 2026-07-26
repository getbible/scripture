# Installation and setup

## Choose the delivery model

For an end-user product, distribute a complete runtime containing PHP, the
native extension, Composer dependencies, and the application's policy-approved
SWORD modules. The recipient should start the product without compiling a PHP
extension or running dependency-manager commands. See
[shipping and deployment](distribution.md).

Developers adding the library to an existing Composer application install the
native prerequisite first:

```bash
pie install getbible/sword
php --ri getbiblesword
composer require getbible/scripture
```

The released `getbible/sword` PIE package contains the pinned getBibleSword and
CrossWire SWORD sources. PIE builds and enables the extension for the selected
PHP installation.

## Why Composer does not install the extension

`ext-getbiblesword` is a Composer platform dependency. Platform dependencies
describe the PHP runtime that is executing Composer; Composer validates them but
does not download or enable extensions. The official explanation is in
[Composer platform dependencies](https://getcomposer.org/doc/articles/composer-platform-dependencies.md).

Adding a `post-install-cmd` to this library would not help. Composer executes
scripts from the root application only and does not execute scripts declared by
a dependency. See
[Composer scripts](https://getcomposer.org/doc/articles/scripts.md#what-is-a-script).

The root application can provide friendly Composer aliases without granting a
dependency permission to execute automatically:

```json
{
    "scripts": {
        "scripture:doctor": "@php vendor/bin/getbible-scripture scripture:doctor --json",
        "scripture:setup": "@php vendor/bin/getbible-scripture scripture:setup"
    }
}
```

The application operator can then run:

```bash
composer scripture:doctor
composer scripture:setup
```

These aliases work because they belong to the root application's
`composer.json`; scripts declared by `getbible/scripture` are not inherited by
the consuming project.

A Composer plugin is also unsuitable for native bootstrap. Plugins require
explicit approval by the root project, may be disabled, and run with the full
privileges of the Composer user. They cannot safely assume a compiler, operating
system package manager, administrator access, or permission to change the active
PHP configuration. Composer documents that security boundary in
[installing untrusted packages safely](https://getcomposer.org/doc/faqs/how-to-install-untrusted-packages-safely.md).

PIE is the dedicated extension installer. Its supported installation methods
and container usage are documented by the
[PHP Installer for Extensions](https://php.github.io/pie/).

## Interactive application setup

Once Composer has installed the library, inspect the runtime:

```bash
vendor/bin/getbible-scripture scripture:doctor
```

Create an application-owned configuration interactively:

```bash
vendor/bin/getbible-scripture scripture:setup
```

Setup asks for:

- an explicit configuration-file path;
- the installed SWORD module root;
- the writable Scripture cache root;
- the refresh interval and lock timeout;
- installed translation identifiers;
- automatic snapshot refresh policy; and
- whether configured translations should be warmed immediately.

The configuration path can instead be supplied with `--config` or
`GETBIBLE_SCRIPTURE_CONFIG_PATH`. There is no implicit configuration-file
location. Setup validates every value, refuses a symlink target, writes through
an atomic same-directory replacement, and restricts a new configuration file to
mode `0600`.

For a reproducible non-interactive deployment:

```bash
vendor/bin/getbible-scripture scripture:setup \
  --config=/etc/getbible/scripture.json \
  --module-path=/var/lib/getbible/sword \
  --cache-path=/var/cache/getbible/scripture \
  --refresh-interval=P1M \
  --lock-timeout=30 \
  --module=KJV \
  --auto-refresh \
  --json

vendor/bin/getbible-scripture scripture:doctor \
  --config=/etc/getbible/scripture.json \
  --json
```

Use `--no-auto-refresh` to disable query-triggered snapshot rotation and
`--no-warm` to save valid settings without warming installed translations.
Non-interactive setup fails when no explicit configuration path is available.

Setup never invokes PIE, Composer, a system package manager, privilege
escalation, a subprocess, or a network request. It configures and warms modules
that are already installed. Doctor is read-only.

## Programmatic initialization

Applications can construct the same services directly:

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
    'refresh_interval' => 'P1M',
    'lock_timeout' => 30,
    'modules' => ['KJV'],
    'auto_refresh' => true,
    'provisioning_enabled' => false,
    'install_all' => false,
]);

$container = ContainerFactory::create($configuration);

/** @var ScriptureInterface $scripture */
$scripture = $container->get(ScriptureInterface::class);
$result = $scripture->initialize(['KJV']);

if (!$result->succeeded()) {
    throw new RuntimeException(
        json_encode(
            $result->toArray(),
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        ),
    );
}
```

`initialize()` validates and warms installed modules. With the released native
ABI, it does not install a missing module. Applications that inject a mutating
provisioner must explicitly enable provisioning and enforce the security and
licensing contract described in [module provisioning](provisioning.md).

## Verification

After setup:

```bash
vendor/bin/getbible-scripture scripture:doctor --json
vendor/bin/getbible-scripture scripture:status
```

`scripture:doctor` returns non-zero when configuration parsing or native
compatibility is not ready. `scripture:status` additionally reports durable
maintenance health; a normal warmed setup also exercises module and cache
access.
