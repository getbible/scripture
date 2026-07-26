# Configuration

## Sources and precedence

`Configuration::fromEnvironment()` applies:

1. explicit array values;
2. environment variables;
3. defaults.

A userland Composer package cannot register PHP INI directives. The package does
not claim custom `ini_set()` support.

## Settings

### `module_path`

Environment: `GETBIBLE_SCRIPTURE_MODULE_PATH`

An absolute SWORD installation root. When omitted, the native extension resolves
its configured `getbiblesword.module_path`, `SWORD_PATH`, or effective-user
default. Production applications should provide an explicit path.

### `cache_path`

Environment: `GETBIBLE_SCRIPTURE_CACHE_PATH`

The durable snapshot root. The default is:

1. `$XDG_CACHE_HOME/getbible/scripture`;
2. `$HOME/.cache/getbible/scripture`; or
3. the operating-system temporary directory plus
   `/getbible-scripture-<effective-user-id>`.

The PHP worker must be able to create directories, lock files, staged exports,
indexes, and atomic pointer files below this path.

### `refresh_interval`

Environment: `GETBIBLE_SCRIPTURE_REFRESH_INTERVAL`

An ISO-8601 duration accepted by `DateInterval`, default `P1M`.

Examples:

```text
P1D
P1W
P1M
P3M
```

### `auto_refresh`

Environment: `GETBIBLE_SCRIPTURE_AUTO_REFRESH`

Accepted true values are `1`, `true`, `yes`, and `on`. Accepted false values are
`0`, `false`, `no`, and `off`. The default is true.

Automatic refresh means a stale installed-module snapshot is rebuilt when it is
next requested. It does not mean that a PHP process creates a background timer,
and it does not download newer module files under ABI v1.

## Monthly rotation

The active snapshot records its generation time. When auto refresh is enabled
and that time plus the configured interval is in the past, the next query warms
a replacement generation under the interprocess lock.

For predictable maintenance windows, call `refreshTranslation()` from a Joomla
Scheduled Task, cron command, or systemd timer.
