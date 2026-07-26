# Contributing

## Development

Install the native extension first, then install Composer dependencies:

```bash
pie install getbible/sword
composer install
composer check
```

Unit tests use an injected engine and can run without loading the extension:

```bash
composer install --ignore-platform-req=ext-getbiblesword
composer check
```

Generate the same coverage inventory retained by CI:

```bash
XDEBUG_MODE=coverage composer coverage
composer coverage-inventory
```

Every public class and method must have intentional test evidence. New
filesystem, concurrency, native-boundary, configuration, or recovery behavior
also requires a failure-path test.

## Engineering rules

- Preserve GPL-2.0-only SPDX headers on source files.
- Keep domain objects immutable and free of container lookups.
- Inject infrastructure through interfaces.
- Never load a complete native module stream into one PHP string.
- Validate the entire v1 stream before activating a snapshot.
- Treat decoded Base64 bytes as authoritative.
- Preserve unknown additive record fields.
- Never treat lexical annotation markup as trusted HTML.
- Do not introduce network activity in constructors.
- Do not modify an active SWORD module root while native extraction is reading
  it.

Contract changes require matching tests and documentation. A breaking public API
change requires an appropriate semantic version increment.
