<?php

declare(strict_types=1);

/**
 * Check that code coverage meets the minimum threshold (default 82%).
 * Reads the Clover XML report produced by PHPUnit and exits with 1 if below threshold.
 *
 * Usage: php .scripts/check-coverage.php [coverage.xml] [min-percent]
 *
 * Target 95%. Minimum 90%: remaining uncovered is DBAL 2/4 and reflection fallbacks.
 * Override: php .scripts/check-coverage.php coverage.xml 95
 */
$coverageFile = $argv[1] ?? __DIR__ . '/../coverage.xml';
$minPercent   = isset($argv[2]) ? (float) $argv[2] : 90.0;

if (!is_file($coverageFile)) {
    fwrite(\STDERR, "Coverage file not found: {$coverageFile}\n");
    exit(1);
}

$xml = @simplexml_load_file($coverageFile);
if ($xml === false) {
    fwrite(\STDERR, "Invalid or empty coverage XML: {$coverageFile}\n");
    exit(1);
}

// Clover format: /coverage/project/metrics[@elements and @coveredelements] or statements/coveredstatements
$metrics = $xml->xpath('//project/metrics');
if ($metrics === []) {
    $metrics = $xml->xpath('//metrics');
}
if ($metrics === []) {
    fwrite(\STDERR, "No metrics found in coverage XML.\n");
    exit(1);
}

$total   = 0;
$covered = 0;
foreach ($metrics as $m) {
    $attrs             = (array) $m->attributes();
    $attrs             = $attrs['@attributes'] ?? $attrs;
    $statements        = (int) ($attrs['statements'] ?? $attrs['elements'] ?? 0);
    $coveredStatements = (int) ($attrs['coveredstatements'] ?? $attrs['coveredelements'] ?? 0);
    $total += $statements;
    $covered += $coveredStatements;
}

if ($total === 0) {
    fwrite(\STDERR, "No statements in coverage report (no code included?).\n");
    exit(1);
}

$percent = $covered / $total * 100.0;
$percent = round($percent, 2);

echo sprintf("Code coverage: %s%% (%d/%d statements). Minimum required: %s%%\n", $percent, $covered, $total, $minPercent);

if ($percent < $minPercent) {
    fwrite(\STDERR, sprintf("Coverage %.2f%% is below the required %s%%.\n", $percent, $minPercent));
    exit(1);
}

echo "Coverage threshold met.\n";
exit(0);
