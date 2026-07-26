<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\DependencyInjection\ContainerFactory;
use GetBible\Scripture\Service\ScriptureInterface;
use GetBible\Sword\Engine;

if ($argc !== 4) {
    fwrite(
        STDERR,
        "Usage: php scripts/integration-kjv.php MODULE_ROOT CACHE_ROOT EVIDENCE_JSON\n",
    );
    exit(2);
}

[$script, $moduleRoot, $cacheRoot, $evidencePath] = $argv;
unset($script);

if (!extension_loaded('getbiblesword')) {
    fwrite(STDERR, "The getbiblesword extension is not loaded.\n");
    exit(1);
}

if (!is_dir($moduleRoot . '/mods.d') || !is_file($moduleRoot . '/mods.d/kjv.conf')) {
    fwrite(STDERR, sprintf("The KJV module root is invalid: %s\n", $moduleRoot));
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

$configuration = Configuration::fromEnvironment([
    'module_path' => $moduleRoot,
    'cache_path' => $cacheRoot,
    'refresh_interval' => 'P1M',
    'auto_refresh' => true,
    'modules' => ['KJV'],
    'provisioning_enabled' => false,
    'install_all' => false,
]);
$container = ContainerFactory::create($configuration);

/** @var ScriptureInterface $scripture */
$scripture = $container->get(ScriptureInterface::class);
$initialization = $scripture->initialize(['KJV']);

if (!$initialization->succeeded()) {
    fwrite(
        STDERR,
        json_encode($initialization->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL,
    );
    exit(1);
}

$translation = $scripture->translation('KJV');
$revelation = $translation->book('Rev');
$chapterOne = $revelation->chapter(1)->verses();
$chapterTwentyTwo = $revelation->chapter(22)->verses();

if (count($chapterOne) !== 20 || count($chapterTwentyTwo) !== 21) {
    fwrite(
        STDERR,
        sprintf(
            "Unexpected KJV Revelation verse counts: chapter 1=%d, chapter 22=%d.\n",
            count($chapterOne),
            count($chapterTwentyTwo),
        ),
    );
    exit(1);
}

$john316 = $scripture->verse('KJV', 'John', 3, 16);
$johnText = $john316->stripped()?->bytes() ?? $john316->raw()->bytes();

if ($johnText === '') {
    fwrite(STDERR, "KJV John 3:16 resolved to an empty value.\n");
    exit(1);
}

$evidence = [
    'extension_version' => phpversion('getbiblesword'),
    'native_product_version' => Engine::productVersion(),
    'native_abi_version' => Engine::abiVersion(),
    'contract' => Engine::contractIdentifier(),
    'source_archive_sha256' => getenv('KJV_SOURCE_SHA256') ?: null,
    'module' => $translation->moduleName(),
    'classification' => $translation->metadata()->classification(),
    'book_count' => count($translation->books()),
    'revelation' => [
        'name' => $revelation->name()->requireUtf8(),
        'abbreviation' => $revelation->abbreviation()->requireUtf8(),
        'chapter_1_verse_count' => count($chapterOne),
        'chapter_22_verse_count' => count($chapterTwentyTwo),
    ],
    'john_3_16_sha256' => hash('sha256', $johnText),
    'initialization' => $initialization->toArray(),
    'status' => $scripture->maintenanceStatus()->toArray(),
];
$evidenceDirectory = dirname($evidencePath);

if (
    !is_dir($evidenceDirectory)
    && !mkdir($evidenceDirectory, 0775, true)
    && !is_dir($evidenceDirectory)
) {
    fwrite(STDERR, sprintf("Unable to create evidence directory: %s\n", $evidenceDirectory));
    exit(1);
}

$json = json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (file_put_contents($evidencePath, $json . PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, sprintf("Unable to write integration evidence: %s\n", $evidencePath));
    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "Validated KJV through %s: Revelation 1 (%d verses), Revelation 22 (%d verses).\n",
        Engine::contractIdentifier(),
        count($chapterOne),
        count($chapterTwentyTwo),
    ),
);
