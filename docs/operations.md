# Production operations

## Lifecycle semantics

The public service exposes three explicit maintenance operations:

```php
$scripture->initialize();
$scripture->refresh();
$scripture->refreshIfDue();
```

`initialize()` installs missing configured translations only when runtime policy
enables provisioning and the injected backend advertises the required
capability. It then opens or warms validated snapshots for every target.

`refresh()` invokes remote module refresh only when `provisioning_enabled` is
true. It always rebuilds snapshots for installed targets. Under getBibleSword
ABI v1, leave provisioning disabled and manage the SWORD module root through a
separate trusted deployment process.

`refreshIfDue()` compares durable last-success state with `refresh_interval`.
The default is one calendar month. A failed or partially failed run does not
advance last-success time, so the next scheduler invocation retries.

All operations:

- take one bounded whole-run maintenance lock;
- isolate snapshot failures by module;
- preserve the last active snapshot on export or validation failure;
- write maintenance state through a synchronized temporary file and atomic
  rename;
- emit Joomla lifecycle events; and
- return a structured `MaintenanceResult`.

## CLI

Composer exposes the Joomla Console application as
`vendor/bin/getbible-scripture`.

```bash
vendor/bin/getbible-scripture scripture:status
vendor/bin/getbible-scripture scripture:initialize --module=KJV --module=WEB
vendor/bin/getbible-scripture scripture:initialize --all
vendor/bin/getbible-scripture scripture:refresh --module=KJV
vendor/bin/getbible-scripture scripture:refresh --if-due
```

Every command writes deterministic JSON. Initialization and refresh return exit
code `0` only when the complete result succeeds, and `1` for partial or complete
failure. Bootstrap and uncaught application failures use a non-zero Joomla
Console exit code.

`--all` remains an explicit operation. It also requires
`GETBIBLE_SCRIPTURE_PROVISIONING_ENABLED=true` and a native backend that
advertises all-module installation.

## Environment

A production deployment can use:

```dotenv
GETBIBLE_SCRIPTURE_MODULE_PATH=/var/lib/getbible/sword
GETBIBLE_SCRIPTURE_CACHE_PATH=/var/cache/getbible/scripture
GETBIBLE_SCRIPTURE_MODULES=KJV,WEB
GETBIBLE_SCRIPTURE_REFRESH_INTERVAL=P1M
GETBIBLE_SCRIPTURE_AUTO_REFRESH=true
GETBIBLE_SCRIPTURE_PROVISIONING_ENABLED=false
GETBIBLE_SCRIPTURE_INSTALL_ALL=false
GETBIBLE_SCRIPTURE_LOCK_TIMEOUT=30
```

The PHP-FPM and scheduler users must share read access to the SWORD root and
read/write access to the cache root. Run maintenance as the same operating
system identity as the application whenever possible.

## Cron

The command contains its own bounded interprocess lock, so overlapping cron
runs safely serialize:

```cron
17 3 * * * cd /srv/getbible-app && /usr/bin/php vendor/bin/getbible-scripture scripture:refresh --if-due >> /var/log/getbible-scripture.log 2>&1
```

Run the cron entry daily or weekly; durable interval state decides whether the
monthly work is due.

## systemd

`/etc/systemd/system/getbible-scripture.service`:

```ini
[Unit]
Description=GetBible Scripture maintenance
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/srv/getbible-app
EnvironmentFile=/etc/getbible/scripture.env
ExecStart=/usr/bin/php vendor/bin/getbible-scripture scripture:refresh --if-due
PrivateTmp=true
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/var/cache/getbible/scripture
ReadOnlyPaths=/var/lib/getbible/sword
```

`/etc/systemd/system/getbible-scripture.timer`:

```ini
[Unit]
Description=Run GetBible Scripture maintenance daily

[Timer]
OnCalendar=*-*-* 03:17:00
Persistent=true
RandomizedDelaySec=15m
Unit=getbible-scripture.service

[Install]
WantedBy=timers.target
```

Enable the timer:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now getbible-scripture.timer
systemctl list-timers getbible-scripture.timer
```

If the future native provisioner writes to the SWORD root, change its systemd
path allowance from `ReadOnlyPaths` to the narrow required `ReadWritePaths`.

## Joomla Scheduled Tasks

`ScheduledRefreshHandler` is registered in the same Joomla DI container as the
Scripture service:

```php
use GetBible\Scripture\Integration\Joomla\ScheduledRefreshHandler;

$handler = $container->get(ScheduledRefreshHandler::class);
$result = $handler();
```

A Joomla CMS scheduler plugin can call this service from its task execution
callback, log `MaintenanceResult::toArray()`, and map `succeeded()` to the CMS
task success/failure status. The handler performs interval gating itself, so
the CMS task can run daily without forcing a monthly refresh on every run.

## Health checks

Use:

```bash
vendor/bin/getbible-scripture scripture:status
```

The JSON includes:

- whether maintenance is due;
- the next due time;
- last attempt, success, and failure timestamps;
- consecutive failures and the last error;
- configured and installed translations;
- runtime provisioning policy; and
- exact native backend capabilities.

Alert on a non-null `last_error`, increasing `consecutive_failures`, or a due
state that remains unchanged across multiple scheduler invocations.
