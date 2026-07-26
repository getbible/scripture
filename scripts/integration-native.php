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
        "Usage: php scripts/integration-native.php MODULE_ROOT CACHE_ROOT EVIDENCE_JSON\n",
    );
    exit(2);
}

[$script, $moduleRoot, $cacheRoot, $evidencePath] = $argv;
unset($script);

if (!extension_loaded('getbiblesword')) {
    fwrite(STDERR, "The getbiblesword extension is not loaded.\n");
    exit(1);
}

$configurationPath = $moduleRoot . '/mods.d/scripturefixture.conf';

if (!is_dir($moduleRoot . '/mods.d') || !is_file($configurationPath)) {
    fwrite(STDERR, sprintf("The fixture module root is invalid: %s\n", $moduleRoot));
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

$configuration = Configuration::fromEnvironment([
    'module_path' => $moduleRoot,
    'cache_path' => $cacheRoot,
    'refresh_interval' => 'P1M',
    'auto_refresh' => true,
    'modules' => ['ScriptureFixture'],
    'provisioning_enabled' => false,
    'install_all' => false,
]);
$container = ContainerFactory::create($configuration);

/** @var ScriptureInterface $scripture */
$scripture = $container->get(ScriptureInterface::class);
$initialization = $scripture->initialize(['ScriptureFixture']);

if (!$initialization->succeeded()) {
    fwrite(
        STDERR,
        json_encode($initialization->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL,
    );
    exit(1);
}

$translation = $scripture->translation('ScriptureFixture');
$genesis = $scripture->verse('ScriptureFixture', 'Gen', 1, 1);
$john = $scripture->verse('ScriptureFixture', 'John', 1, 1);
$genesisText = $genesis->stripped()?->requireUtf8() ?? $genesis->raw()->requireUtf8();
$johnText = $john->stripped()?->requireUtf8() ?? $john->raw()->requireUtf8();

if (
    !str_contains($genesisText, 'In the beginning')
    || $johnText !== 'In the beginning was the Word.'
) {
    fwrite(STDERR, "The native fixture returned unexpected Scripture text.\n");
    exit(1);
}

$metadata = $translation->metadata();
$licenseEntries = $translation->configEntriesNamed('DistributionLicense');
$license = isset($licenseEntries[0])
    ? $licenseEntries[0]->value()->requireUtf8()
    : null;

if ($metadata->classification() !== 'bible' || $license !== 'Public Domain') {
    fwrite(STDERR, "The native fixture returned unexpected module metadata.\n");
    exit(1);
}

$fixtureSourcePath = dirname(__DIR__) . '/tests/Fixtures/Native/verse.imp';
$fixtureSourceSha256 = hash_file('sha256', $fixtureSourcePath);
$expectedFixtureSourceSha256 = getenv('FIXTURE_SOURCE_SHA256');
$fixtureTreeSha256 = getenv('FIXTURE_TREE_SHA256');
$swordUtilsVersion = getenv('SWORD_UTILS_VERSION');
$repositoryCommit = getenv('GITHUB_SHA');

if (
    !is_string($fixtureSourceSha256)
    || $fixtureSourceSha256 !== $expectedFixtureSourceSha256
    || !is_string($fixtureTreeSha256)
    || preg_match('/^[a-f0-9]{64}$/D', $fixtureTreeSha256) !== 1
    || !is_string($swordUtilsVersion)
    || !str_starts_with($swordUtilsVersion, '1.9.0+dfsg-')
    || !is_string($repositoryCommit)
    || preg_match('/^[a-f0-9]{40}$/D', $repositoryCommit) !== 1
) {
    fwrite(STDERR, "The native fixture provenance evidence is invalid.\n");
    exit(1);
}

$evidence = [
    'extension_version' => phpversion('getbiblesword'),
    'native_product_version' => Engine::productVersion(),
    'native_abi_version' => Engine::abiVersion(),
    'contract' => Engine::contractIdentifier(),
    'fixture_source_sha256' => $fixtureSourceSha256,
    'fixture_tree_sha256' => $fixtureTreeSha256,
    'sword_utils_version' => $swordUtilsVersion,
    'repository_commit' => $repositoryCommit,
    'module' => $translation->moduleName(),
    'classification' => $metadata->classification(),
    'distribution_license' => $license,
    'book_count' => count($translation->books()),
    'genesis_1_1_sha256' => hash('sha256', $genesisText),
    'john_1_1_sha256' => hash('sha256', $johnText),
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
        "Validated %s through %s with two Public Domain verses.\n",
        $translation->moduleName(),
        Engine::contractIdentifier(),
    ),
);
