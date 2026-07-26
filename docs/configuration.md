# Configuration

## Sources and precedence

`Configuration::fromEnvironment()` applies:

1. explicit array values;
2. environment variables;
3. defaults.

A userland Composer package cannot register PHP INI directives. The package does
not claim custom `ini_set()` support.

## Configuration file

`scripture:setup` writes an application-owned JSON document using the contract
identifier `getbible.scripture.configuration/v1`. Supply its absolute path with
`--config` or `GETBIBLE_SCRIPTURE_CONFIG_PATH`; no implicit file location is
used.

Setup precedence is:

1. setup command options;
2. matching `GETBIBLE_SCRIPTURE_*` environment variables;
3. values already stored in the JSON document;
4. defaults.

Normal `scripture:initialize`, `scripture:refresh`, and `scripture:status`
commands load the document when `GETBIBLE_SCRIPTURE_CONFIG_PATH` is set. Those
maintenance commands do not accept `--config`.

Programmatic applications can load the same document explicitly:

```php
$container = ContainerFactory::create(
    configuration: null,
    configurationPath: '/etc/getbible/scripture.json',
);
```

The repository accepts only allowlisted settings, refuses a symlink target,
persists through an atomic same-directory replacement, gives a new parent
directory mode `0700`, and gives a new file mode `0600`.

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

### `lock_timeout`

Environment: `GETBIBLE_SCRIPTURE_LOCK_TIMEOUT`

A positive integer number of seconds, default `30`. Native extraction uses a
shared application lock and provisioning uses the matching exclusive lock. A
bounded timeout prevents PHP workers from waiting indefinitely behind a failed
or overloaded maintenance process.

### `modules`

Environment: `GETBIBLE_SCRIPTURE_MODULES`

An explicit PHP list or comma-separated environment value containing exact
translation module identifiers. Maintenance CLI options override this list for
one run. An empty list means every currently installed Bible translation.

### `provisioning_enabled`

Environment: `GETBIBLE_SCRIPTURE_PROVISIONING_ENABLED`

Defaults to false. When false, initialization and refresh may read installed
modules and rebuild snapshots but cannot mutate the SWORD root. When true, the
injected backend must still advertise each requested capability.

### `install_all`

Environment: `GETBIBLE_SCRIPTURE_INSTALL_ALL`

Defaults to false. Enabling it makes parameterless `initialize()` request every
policy-approved translation. Configuration rejects this setting unless
`provisioning_enabled` is also true.

## Durable maintenance state

Maintenance state is stored at `<cache_path>/maintenance/state.json` through a
synchronized temporary file and atomic rename. It records the last attempt,
complete success, failure, error summary, and consecutive failure count.

`refreshIfDue()` advances its interval only after a completely successful run.
