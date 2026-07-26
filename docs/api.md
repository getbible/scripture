# Public API

## Composition

```php
$configuration = Configuration::fromEnvironment([
    'module_path' => '/var/lib/getbible/sword',
    'cache_path' => '/var/cache/getbible/scripture',
]);

$container = ContainerFactory::create($configuration);
$scripture = $container->get(ScriptureInterface::class);
```

To load the versioned JSON document written by `scripture:setup`:

```php
$container = ContainerFactory::create(
    configuration: null,
    configurationPath: '/etc/getbible/scripture.json',
);
$scripture = $container->get(ScriptureInterface::class);
```

Applications already using Joomla DI can register `ScriptureServiceProvider`
with their own container and pre-register `Configuration` when they need custom
values.

## Programmatic setup

Applications can persist validated settings and warm installed translations
through the same service used by the interactive command:

```php
use GetBible\Scripture\Setup\SetupRequest;
use GetBible\Scripture\Setup\SetupServiceInterface;

/** @var SetupServiceInterface $setup */
$setup = $container->get(SetupServiceInterface::class);

$result = $setup->apply(new SetupRequest(
    configurationPath: '/etc/getbible/scripture.json',
    values: [
        'module_path' => '/var/lib/getbible/sword',
        'cache_path' => '/var/cache/getbible/scripture',
        'modules' => ['KJV'],
        'auto_refresh' => true,
    ],
    warm: true,
));

if (!$result->succeeded()) {
    throw new RuntimeException(
        json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
    );
}
```

`SetupServiceInterface` never invokes PIE, Composer, a system package manager,
or a module download. It changes only the application-owned JSON configuration
and optionally warms modules already available to the native runtime.

## Scripture service

```php
$translations = $scripture->translations();
$translation = $scripture->translation('KJV');
$verse = $scripture->verse('KJV', 'John', 3, 16);
$range = $scripture->verses('KJV', 'John', 3, 16, 18);
$scripture->refreshTranslation('KJV');
$scripture->provisioningCapabilities();
$scripture->installTranslations(['KJV', 'WEB']);
$scripture->installAllTranslations();
$scripture->refreshSelectedModules(['KJV']);
$scripture->refreshModules();
$scripture->removeTranslation('KJV');
$scripture->initialize();
$scripture->refresh();
$scripture->refreshIfDue();
$scripture->maintenanceStatus();
```

`translations()` lists installed Bible modules. `translation()` opens the
current valid snapshot or performs one full warm-up. A forced refresh re-exports
the installed module and activates a new generation only after validation.

Provisioning methods are stable even when the injected native backend cannot
perform them. Inspect `provisioningCapabilities()` first. The default
getBibleSword ABI v1 adapter accurately reports every mutating capability as
unavailable and throws `ProvisioningUnavailableException` when called.

`initialize()`, `refresh()`, and `refreshIfDue()` return `MaintenanceResult`
objects with ordered snapshot outcomes, the optional native provisioning
result, operation errors, timestamps, and interval status. These operations
continue across independent module failures and report partial failure through
`succeeded()`.

## Translation

```php
$translation->moduleName();
$translation->metadata();
$translation->books();
$translation->book('John');
$translation->bookByPosition(testament: 2, book: 4);
$translation->verses('John', 3, 16, 18);
$translation->configEntriesNamed('DistributionLicense');
$translation->rawExportPath();
$translation->activatedAt();
$translation->expiresAt();
$translation->generationId();
```

Configuration entries are ordered and repeated keys are preserved.

## Book

```php
$book->testament();
$book->position();
$book->name();
$book->abbreviation();
$book->versification();
$book->chapters();
$book->chapter(3);
```

`position()` is the SWORD `VerseKey` book position within its versification and
testament. It is not assumed to be a universal 1-66 number.

## Chapter

```php
$chapter->number();
$chapter->verse(16);
$chapter->verses();
$chapter->verses(16, 18);
$chapter->introductions();
```

Ranges are inclusive. A reversed or invalid range throws
`InvalidReferenceException`.

## Verse

```php
$verse->scope();
$verse->key();
$verse->raw();
$verse->rendered();
$verse->stripped();
$verse->annotationSegments();
$verse->officialAttributes();
$verse->contractRecord();
```

`raw()`, `rendered()`, and `stripped()` return verified `ByteValue` objects.
`rendered()` and `stripped()` may be `null` only when the native producer could
not create projections.

## Byte values

```php
$bytes = $value->bytes();
$utf8 = $value->utf8();
$requiredUtf8 = $value->requireUtf8();
$hash = $value->sha256();
$size = $value->size();
$originalEnvelope = $value->toArray();
```

`requireUtf8()` throws when no exact UTF-8 projection exists. It never attempts a
lossy conversion.

## Events

The default Joomla dispatcher emits:

- `onGetBibleScriptureWarmStarted`
- `onGetBibleScriptureWarmCompleted`
- `onGetBibleScriptureWarmFailed`
- `onGetBibleScriptureRefreshStarted`
- `onGetBibleScriptureRefreshCompleted`
- `onGetBibleScriptureRefreshFailed`
- `onGetBibleScriptureProvisioningStarted`
- `onGetBibleScriptureProvisioningCompleted`
- `onGetBibleScriptureProvisioningFailed`
- `onGetBibleScriptureMaintenanceStarted`
- `onGetBibleScriptureMaintenanceCompleted`
- `onGetBibleScriptureMaintenanceFailed`

Event arguments contain the relevant module, operation, result, snapshot, or
exception for that lifecycle boundary. Listeners must not mutate an active
generation. Listener exceptions are contained so an observer cannot turn a
committed core operation into a reported failure.
