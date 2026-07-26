<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

/**
 * Produces a stable inventory from a PHPUnit Clover coverage report.
 *
 * The inventory deliberately reports coverage without imposing an arbitrary
 * percentage. Release policy can supply minimum statement and method
 * percentages as the optional second and third arguments.
 */

if ($argc < 2 || $argc > 4) {
    fwrite(
        STDERR,
        "Usage: php scripts/coverage-inventory.php CLOVER_XML [MIN_STATEMENT_PERCENT] [MIN_METHOD_PERCENT]\n",
    );
    exit(2);
}

$reportPath = $argv[1];
$minimumStatements = percentage($argv[2] ?? '0', 'statement');
$minimumMethods = percentage($argv[3] ?? '0', 'method');

if (!is_file($reportPath) || !is_readable($reportPath)) {
    fwrite(STDERR, sprintf("Coverage report is not readable: %s\n", $reportPath));
    exit(2);
}

libxml_use_internal_errors(true);
$report = simplexml_load_file($reportPath);

if (!$report instanceof SimpleXMLElement) {
    $errors = array_map(
        static fn (LibXMLError $error): string => trim($error->message),
        libxml_get_errors(),
    );
    fwrite(STDERR, "Coverage report is invalid XML.\n");

    foreach ($errors as $error) {
        fwrite(STDERR, sprintf("- %s\n", $error));
    }

    exit(2);
}

$projectMetrics = $report->xpath('/coverage/project/metrics');
$fileNodes = $report->xpath('/coverage/project/package/file | /coverage/project/file');

if ($projectMetrics === false || count($projectMetrics) !== 1 || $fileNodes === false) {
    fwrite(STDERR, "Coverage report does not contain the expected Clover project metrics.\n");
    exit(2);
}

$metrics = $projectMetrics[0]->attributes();

if ($metrics === null) {
    fwrite(STDERR, "Coverage report project metrics are missing.\n");
    exit(2);
}

$statements = integerMetric($metrics, 'statements');
$coveredStatements = integerMetric($metrics, 'coveredstatements');
$methods = integerMetric($metrics, 'methods');
$coveredMethods = integerMetric($metrics, 'coveredmethods');
$statementPercent = ratio($coveredStatements, $statements);
$methodPercent = ratio($coveredMethods, $methods);
$files = [];

foreach ($fileNodes as $fileNode) {
    $attributes = $fileNode->attributes();
    $fileMetrics = $fileNode->metrics->attributes();

    if ($attributes === null || $fileMetrics === null) {
        continue;
    }

    $name = (string) ($attributes['name'] ?? '');

    if ($name === '') {
        continue;
    }

    $fileStatements = integerMetric($fileMetrics, 'statements');
    $fileCoveredStatements = integerMetric($fileMetrics, 'coveredstatements');
    $files[] = [
        'path' => relativePath($name),
        'statements' => $fileStatements,
        'covered_statements' => $fileCoveredStatements,
        'statement_percent' => ratio($fileCoveredStatements, $fileStatements),
        'methods' => integerMetric($fileMetrics, 'methods'),
        'covered_methods' => integerMetric($fileMetrics, 'coveredmethods'),
    ];
}

usort(
    $files,
    static fn (array $left, array $right): int => $left['path'] <=> $right['path'],
);

$inventory = [
    'generated_at' => gmdate(DATE_ATOM),
    'totals' => [
        'files' => count($files),
        'statements' => $statements,
        'covered_statements' => $coveredStatements,
        'statement_percent' => $statementPercent,
        'methods' => $methods,
        'covered_methods' => $coveredMethods,
        'method_percent' => $methodPercent,
    ],
    'minimums' => [
        'statement_percent' => $minimumStatements,
        'method_percent' => $minimumMethods,
    ],
    'files' => $files,
];

$buildDirectory = dirname($reportPath);

if (!is_dir($buildDirectory) && !mkdir($buildDirectory, 0775, true) && !is_dir($buildDirectory)) {
    fwrite(STDERR, sprintf("Unable to create coverage output directory: %s\n", $buildDirectory));
    exit(2);
}

$json = json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$jsonPath = $buildDirectory . '/coverage-inventory.json';

if (file_put_contents($jsonPath, $json . PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, sprintf("Unable to write coverage inventory: %s\n", $jsonPath));
    exit(2);
}

$summary = sprintf(
    "Coverage inventory: %d files, %.2f%% statements (%d/%d), %.2f%% methods (%d/%d).\n",
    count($files),
    $statementPercent,
    $coveredStatements,
    $statements,
    $methodPercent,
    $coveredMethods,
    $methods,
);

fwrite(STDOUT, $summary);

$summaryPath = getenv('GITHUB_STEP_SUMMARY');

if (is_string($summaryPath) && $summaryPath !== '') {
    $markdown = sprintf(
        "### Coverage inventory\n\n"
        . "| Measure | Covered | Total | Percent |\n"
        . "|---|---:|---:|---:|\n"
        . "| Statements | %d | %d | %.2f%% |\n"
        . "| Methods | %d | %d | %.2f%% |\n"
        . "| Files in report | %d | — | — |\n",
        $coveredStatements,
        $statements,
        $statementPercent,
        $coveredMethods,
        $methods,
        $methodPercent,
        count($files),
    );
    file_put_contents($summaryPath, $markdown, FILE_APPEND | LOCK_EX);
}

if ($statementPercent < $minimumStatements || $methodPercent < $minimumMethods) {
    fwrite(
        STDERR,
        sprintf(
            "Coverage minimum not met: statements %.2f%%/%.2f%%, methods %.2f%%/%.2f%%.\n",
            $statementPercent,
            $minimumStatements,
            $methodPercent,
            $minimumMethods,
        ),
    );
    exit(1);
}

/**
 * Parses and validates a percentage argument.
 */
function percentage(string $value, string $label): float
{
    if (!is_numeric($value)) {
        fwrite(STDERR, sprintf("Minimum %s coverage is not numeric: %s\n", $label, $value));
        exit(2);
    }

    $percentage = (float) $value;

    if ($percentage < 0.0 || $percentage > 100.0) {
        fwrite(STDERR, sprintf("Minimum %s coverage must be between 0 and 100.\n", $label));
        exit(2);
    }

    return $percentage;
}

/**
 * Reads a non-negative integer from a Clover metrics element.
 */
function integerMetric(SimpleXMLElement $metrics, string $name): int
{
    $value = (string) ($metrics[$name] ?? '');

    if ($value === '' || !ctype_digit($value)) {
        fwrite(STDERR, sprintf("Coverage metric is missing or invalid: %s\n", $name));
        exit(2);
    }

    return (int) $value;
}

/**
 * Calculates a stable percentage for one coverage measure.
 */
function ratio(int $covered, int $total): float
{
    return $total === 0 ? 100.0 : round(($covered / $total) * 100, 2);
}

/**
 * Makes project files readable in generated inventory artifacts.
 */
function relativePath(string $path): string
{
    $projectRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR;

    return str_starts_with($path, $projectRoot)
        ? substr($path, strlen($projectRoot))
        : $path;
}
