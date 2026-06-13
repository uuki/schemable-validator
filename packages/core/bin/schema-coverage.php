#!/usr/bin/env php
<?php
/**
 * RuleMapper Coverage Report
 *
 * Shows how much of Respect/Validation's rule set is covered by RuleMapper,
 * and which rules fall into each status bucket.
 *
 * Usage:
 *   php packages/core/bin/schema-coverage.php
 *   php packages/core/bin/schema-coverage.php --detail
 */

// Locate autoloader (project root is three levels up from packages/core/bin/)
$autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
  fwrite(STDERR, "autoload.php not found at {$autoload}\n");
  exit(1);
}
require_once $autoload;

use SchemableValidator\Schema\RuleCatalog;

$detail  = in_array('--detail', $argv, true);
$entries = RuleCatalog::entries();

// Bucket counts
$counts = [
  RuleCatalog::STATUS_MAPPED     => 0,
  RuleCatalog::STATUS_UNMAPPABLE => 0,
  RuleCatalog::STATUS_TODO       => 0,
  RuleCatalog::STATUS_EXCLUDED   => 0,
];
$byStatus = [
  RuleCatalog::STATUS_MAPPED     => [],
  RuleCatalog::STATUS_UNMAPPABLE => [],
  RuleCatalog::STATUS_TODO       => [],
  RuleCatalog::STATUS_EXCLUDED   => [],
];
foreach ($entries as $alias => $entry) {
  $s = $entry['status'];
  $counts[$s]++;
  $byStatus[$s][] = $alias;
}

// Cross-check: Respect rules not yet in catalog
$respectRules    = RuleCatalog::scanRespectRules();
$catalogedByResp = array_filter(
  array_column($entries, 'respect'),
  fn($r) => $r !== null,
);
$uncatalogued = array_values(array_diff($respectRules, $catalogedByResp));

$total        = count($respectRules);
$cataloged    = $total - count($uncatalogued);
$pct          = fn($n) => $total > 0 ? sprintf('%.1f%%', $n / $total * 100) : '—';

// ── Output ──────────────────────────────────────────────────

$hr = str_repeat('─', 56);

echo "\n";
echo "Respect/Validation — RuleMapper Coverage\n";
echo $hr . "\n";
echo sprintf("  Respect rules detected (installed version): %d\n", $total);
echo $hr . "\n";
echo sprintf("  %-14s %3d / %3d  (%s)\n", 'MAPPED',     $counts[RuleCatalog::STATUS_MAPPED],     $total, $pct($counts[RuleCatalog::STATUS_MAPPED]));
echo sprintf("  %-14s %3d / %3d  (%s)\n", 'UNMAPPABLE', $counts[RuleCatalog::STATUS_UNMAPPABLE], $total, $pct($counts[RuleCatalog::STATUS_UNMAPPABLE]));
echo sprintf("  %-14s %3d / %3d  (%s)\n", 'TODO',       $counts[RuleCatalog::STATUS_TODO],       $total, $pct($counts[RuleCatalog::STATUS_TODO]));
echo sprintf("  %-14s %3d / %3d  (%s)\n", 'EXCLUDED',   $counts[RuleCatalog::STATUS_EXCLUDED],   $total, $pct($counts[RuleCatalog::STATUS_EXCLUDED]));
echo $hr . "\n";

$actionable = $counts[RuleCatalog::STATUS_MAPPED] + $counts[RuleCatalog::STATUS_UNMAPPABLE] + $counts[RuleCatalog::STATUS_TODO];
echo sprintf("  %-14s %3d / %3d  (%s)\n", 'In scope',   $actionable, $total, $pct($actionable));
echo sprintf("  %-14s %3d / %3d  (%s)\n", 'Catalogued', $cataloged,  $total, $pct($cataloged));

if (!empty($uncatalogued)) {
  echo sprintf("  %-14s %3d / %3d  (%s)  ← run tests to fix\n",
    'Uncatalogued', count($uncatalogued), $total, $pct(count($uncatalogued)));
}

echo $hr . "\n";

if ($detail) {
  $print = function (array $names, int $indent = 4) {
    if (empty($names)) { echo str_repeat(' ', $indent) . "(none)\n"; return; }
    sort($names);
    $cols = 3;
    $rows = array_chunk($names, $cols);
    foreach ($rows as $row) {
      echo str_repeat(' ', $indent) . implode('  ', array_map(fn($n) => str_pad($n, 18), $row)) . "\n";
    }
  };

  echo "\nMAPPED — Respect + JSON Schema:\n";
  $print($byStatus[RuleCatalog::STATUS_MAPPED]);

  echo "\nUNMAPPABLE — Respect only:\n";
  $print($byStatus[RuleCatalog::STATUS_UNMAPPABLE]);

  echo "\nTODO — not yet implemented:\n";
  $print($byStatus[RuleCatalog::STATUS_TODO]);

  echo "\nEXCLUDED — out of scope:\n";
  $print($byStatus[RuleCatalog::STATUS_EXCLUDED]);

  if (!empty($uncatalogued)) {
    echo "\nUNCATALOGUED — add to RuleCatalog:\n";
    $print($uncatalogued);
  }
}

echo "\nRun with --detail for per-rule breakdown.\n\n";
